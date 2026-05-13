<?php

namespace App\Apps\ImageGen\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGenerationTask;
use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateController extends Controller
{
    public function submit(Request $request, BillingService $billing): JsonResponse
    {
        $user = $request->user();

        $quality = $request->input('quality', 'medium');
        if (!in_array($quality, ['low', 'medium', 'high'])) {
            return response()->json(['error' => 'Invalid quality'], 422);
        }

        $count = max(1, min(4, (int) $request->input('count', 1)));

        if (!$billing->canAfford($user, $quality)) {
            return response()->json(['error' => '余额不足，请先兑换充值'], 402);
        }

        $channel = AiChannel::where('status', 'active')
            ->where('app_name', 'image-gen')
            ->orderByRaw('priority DESC, RANDOM()')
            ->first();

        if (!$channel) {
            return response()->json(['error' => '暂无可用渠道，请联系管理员'], 503);
        }

        try {
            $usageLog = $billing->charge($user, $quality, [
                'app_name' => 'image-gen',
                'model' => $request->input('model', 'gpt-image-2'),
                'channel_id' => $channel->id,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 402);
        }

        $mode = $request->input('mode', 'text');
        $files = [];
        if ($mode === 'image' && $request->hasFile('image')) {
            $uploadedFiles = $request->file('image');
            $uploadedFiles = is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles];
            $taskDir = storage_path('app/temp/tasks/' . bin2hex(random_bytes(8)));
            @mkdir($taskDir, 0755, true);

            foreach ($uploadedFiles as $file) {
                $path = $file->store('temp/task-inputs');
                $files[] = [
                    'path' => storage_path('app/' . $path),
                    'name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ];
            }
        }

        $taskId = bin2hex(random_bytes(16));
        $task = GenerationTask::create([
            'task_id' => $taskId,
            'user_id' => $user->id,
            'status' => 'pending',
            'mode' => $mode,
            'model' => $request->input('model', 'gpt-image-2'),
            'prompt' => $request->input('prompt', ''),
            'size' => $request->input('size', 'auto'),
            'quality' => $quality,
            'count' => $count,
            'is_public' => (bool) $request->input('public', false),
            'input_count' => count($files),
            'files' => $files,
            'items' => array_fill(0, $count, null), // 占位
        ]);

        $usageLog->update(['task_id' => $taskId]);

        // 每张图独立 Job，并发执行
        for ($i = 0; $i < $count; $i++) {
            ProcessGenerationTask::dispatch($taskId, $i, $channel->api_key);
        }

        $user->refresh();

        // 余额不足提醒
        if ($user->credits < 5 && $user->balance < 10) {
            $user->notify(new \App\Notifications\LowBalance());
        }

        return response()->json([
            'ok' => true,
            'task_id' => $taskId,
            'status' => 'pending',
            'user' => [
                'credits' => $user->credits,
                'balance' => $user->balance,
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $taskId = $request->input('task_id', '');
        if (!$taskId || !preg_match('/^[a-f0-9]{32}$/', $taskId)) {
            return response()->json(['ok' => false, 'error' => 'Invalid task_id'], 400);
        }

        $task = GenerationTask::find($taskId);
        if (!$task) {
            return response()->json(['ok' => false, 'error' => 'Task not found'], 404);
        }

        // 兜底：任务超过 10 分钟仍未结束，强制失败并退款。
        // 用于 worker 崩溃/机器断电等极端情况；Job 自身正常重试链路不会走到这里。
        $MAX_TASK_SECONDS = 15 * 60;
        if (in_array($task->status, ['pending', 'processing'], true)
            && $task->created_at
            && $task->created_at->diffInSeconds(now()) > $MAX_TASK_SECONDS) {
            $this->forceFailAndRefund($task, '任务处理超过 10 分钟未完成。');
            $task->refresh();
        }

        // 对外极简：只暴露四态 + 完成图片。不返回底层 error/technical message。
        $publicStatus = in_array($task->status, ['pending', 'processing', 'completed', 'failed'], true)
            ? $task->status
            : 'processing';

        $publicMessage = match ($publicStatus) {
            'completed' => '生成完成',
            'failed'    => '生成失败，已自动退款，请重试',
            'pending'   => '排队中',
            default     => '生成中',
        };

        // 返回已完成的 items（过滤掉 null 占位和 false 失败标记）
        $allItems = $task->items ?? [];
        $doneItems = array_values(array_filter($allItems, fn($i) => is_array($i)));
        $progress = count($doneItems) . '/' . $task->count;

        $taskPayload = [
            'task_id'      => $task->task_id,
            'status'       => $publicStatus,
            'message'      => $publicMessage,
            'items'        => $doneItems,
            'progress'     => $progress,
            'created_at'   => $task->created_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
        ];

        return response()->json([
            'ok'           => true,
            'task'         => $taskPayload,
            // 平铺字段保留，向后兼容
            'task_id'      => $taskPayload['task_id'],
            'status'       => $taskPayload['status'],
            'message'      => $taskPayload['message'],
            'items'        => $taskPayload['items'],
            'progress'     => $taskPayload['progress'],
            'created_at'   => $taskPayload['created_at'],
            'completed_at' => $taskPayload['completed_at'],
        ]);
    }

    /**
     * 强制标记任务失败并退款（幂等）。供 10 分钟兜底调用。
     */
    protected function forceFailAndRefund(GenerationTask $task, string $reason): void
    {
        $task->update([
            'status'  => 'failed',
            'message' => '生成失败，已自动退款，请重试',
            'error'   => $reason,
        ]);

        $log = \App\Models\UsageLog::where('task_id', $task->task_id)->first();
        if ($log && $task->user && is_null($log->refunded_at)) {
            if ($log->cost_credits > 0) {
                $task->user->increment('credits', $log->cost_credits);
            }
            if ($log->cost_balance > 0) {
                $task->user->increment('balance', $log->cost_balance);
            }
            $log->update(['refunded_at' => now()]);
        }
    }
}
