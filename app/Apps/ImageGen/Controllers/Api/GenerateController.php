<?php

namespace App\Apps\ImageGen\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Services\BillingService;
use App\Services\ContentFilterService;
use Illuminate\Support\Facades\Redis;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GenerateController extends Controller
{
    public function submit(Request $request, BillingService $billing): JsonResponse
    {
        $user = $request->user();

        // Per-user rate limit: 5 requests per minute
        $rateKey = 'gen_rate:' . $user->id;
        $attempts = (int) \Illuminate\Support\Facades\Cache::get($rateKey, 0);
        if ($attempts >= 5) {
            return response()->json(['error' => '生成过于频繁，请稍后再试'], 429);
        }
        \Illuminate\Support\Facades\Cache::put($rateKey, $attempts + 1, 60);

        $prompt = trim($request->input('prompt', ''));
        if ($prompt === '') {
            return response()->json(['error' => '请输入提示词'], 422);
        }

        if (!(new ContentFilterService())->isClean($prompt)) {
            return response()->json(['error' => '提示词包含违规内容，请修改后重试'], 422);
        }

        $quality = $request->input('quality', 'medium');
        if (!in_array($quality, ['low', 'medium', 'high'])) {
            return response()->json(['error' => 'Invalid quality'], 422);
        }

        $count = max(1, min(4, (int) $request->input('count', 1)));
        $model = $request->input('model', 'gpt-image-2');

        if (!$billing->canAfford($user, $model, $quality, 'image-gen', $count)) {
            return response()->json(['error' => '积分不足，请先充值'], 402);
        }

        $channel = AiChannel::where('status', 'active')
            ->where('app_name', 'image-gen')
            ->orderBy('priority', 'desc')
            ->inRandomOrder()
            ->first();

        if (!$channel) {
            return response()->json(['error' => '暂无可用渠道，请联系管理员'], 503);
        }

        try {
            $usageLog = $billing->charge($user, $quality, [
                'app_name' => 'image-gen',
                'model' => $request->input('model', 'gpt-image-2'),
                'channel_id' => $channel->id,
                'count' => $count,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 402);
        }

        $mode = $request->input('mode', 'text');
        $files = [];
        if ($mode === 'image' && $request->hasFile('image')) {
            $uploadedFiles = $request->file('image');
            $uploadedFiles = is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles];
            $storage = app(ImageStorageService::class);

            foreach ($uploadedFiles as $file) {
                $binary = file_get_contents($file->getRealPath());
                $key = $storage->store($binary, $file->getMimeType());
                $files[] = [
                    'name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'url' => $storage->url($key),
                ];
            }
        }

        try {
            $taskId = bin2hex(random_bytes(16));
            $task = GenerationTask::create([
                'task_id' => $taskId,
                'user_id' => $user->id,
                'status' => 'pending',
                'mode' => $mode,
                'model' => $request->input('model', 'gpt-image-2'),
                'prompt' => $prompt,
                'size' => $request->input('size', 'auto'),
                'quality' => $quality,
                'count' => $count,
                'is_public' => (bool) $request->input('public', false),
                'input_count' => count($files),
                'files' => $files,
                'items' => array_fill(0, $count, null), // 占位
            ]);

            $usageLog->update(['task_id' => $taskId]);

            // 每张图独立任务，推入 Redis
            for ($i = 0; $i < $count; $i++) {
                Redis::rpush('image_gen_tasks', json_encode(['task_id' => $taskId, 'index' => $i]));
            }
        } catch (\Throwable $e) {
            // 任务创建或 dispatch 失败，退款
            $billing->refundLog($usageLog);
            return response()->json(['error' => '任务创建失败，已退款，请重试'], 500);
        }

        $user->refresh();

        try {
            if ($user->credits < 5 && $user->balance < 10) {
                $user->notify(new \App\Notifications\LowBalance());
            }
        } catch (\Throwable) {}


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
        $MAX_TASK_SECONDS = 10 * 60;
        if (in_array($task->status, ['pending', 'processing'], true)
            && $task->created_at
            && $task->created_at->diffInSeconds(now()) > $MAX_TASK_SECONDS) {
            $this->forceFailAndRefund($task, '任务处理超时未完成。');
            $task->refresh();
        }

        // 中间兜底：卡死超过 5 分钟但未到最终超时，尝试重新 dispatch
        if (in_array($task->status, ['pending', 'processing'], true)
            && $task->updated_at
            && $task->updated_at->diffInSeconds(now()) > 300
            && ($task->attempts ?? 0) < 3) {
            $this->retryStuckTask($task);
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

        // 返回已完成的 items（过滤掉 null 占位、false 失败标记和 expired 标记）
        $allItems = $task->items ?? [];
        $doneItems = array_values(array_filter($allItems, fn($i) => is_array($i) && !empty($i['url'])));
        $progress = count($doneItems) . '/' . $task->count;

        $taskPayload = [
            'task_id'      => $task->task_id,
            'status'       => $publicStatus,
            'message'      => $publicMessage,
            'prompt'       => $task->prompt,
            'items'        => $doneItems,
            'progress'     => $progress,
            'count'        => $task->count,
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

        // 原子更新防止 double refund
        $affected = \App\Models\UsageLog::where('task_id', $task->task_id)
            ->whereNull('refunded_at')
            ->update(['refunded_at' => now()]);

        if ($affected > 0) {
            $log = \App\Models\UsageLog::where('task_id', $task->task_id)->first();
            if ($log && $task->user) {
                if ($log->cost_credits > 0) {
                    $task->user->increment('credits', $log->cost_credits);
                }
                if ($log->cost_balance > 0) {
                    $task->user->increment('balance', $log->cost_balance);
                }
            }
        }
    }

    protected function retryStuckTask(GenerationTask $task): void
    {
        // 检查 jobs 表是否已有该任务的 job
        $existingJob = DB::table('jobs')
            ->where('payload', 'like', '%' . $task->task_id . '%')
            ->exists();

        if ($existingJob) {
            return;
        }

        $task->update([
            'status' => 'pending',
            'message' => '正在重试...',
            'attempts' => ($task->attempts ?? 0) + 1,
        ]);

        $items = $task->items ?? [];
        for ($i = 0; $i < $task->count; $i++) {
            if (!isset($items[$i]) || $items[$i] === null) {
                Redis::rpush('image_gen_tasks', json_encode(['task_id' => $task->task_id, 'index' => $i]));
            }
        }
    }
}
