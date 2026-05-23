<?php

namespace App\Apps\ImageGen\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiChannel;
use App\Models\AgentSite;
use App\Models\GenerationTask;
use App\Services\BillingService;
use App\Services\ChannelDispatcher;
use App\Services\ContentFilterService;
use App\Services\ImageStorageService;
use Illuminate\Support\Facades\Redis;
use App\Apps\ImageGen\Controllers\GalleryController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateController extends Controller
{
    public function submit(Request $request, BillingService $billing, ChannelDispatcher $dispatcher): JsonResponse
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

        $mode = $request->input('mode', 'text');
        $fileUrlsInput = $request->input('file_urls', []);
        $fileUrlsInput = is_array($fileUrlsInput) ? array_values(array_filter($fileUrlsInput)) : [];

        if ($mode === 'image' && empty($fileUrlsInput) && !$request->hasFile('image')) {
            return response()->json(['error' => '参考图上传失败，请重新上传后再生成'], 422);
        }

        $count = max(1, min(4, (int) $request->input('count', 1)));
        $model = $request->input('model', 'gpt-image-2');
        $requestedSize = $request->input('size', 'auto');
        $sizeForValidation = $requestedSize;
        $size = $this->normalizeImageSize($requestedSize);
        $agentSite = AgentSite::resolveForHost($request->getHost());

        // 校验模型配置（如果 ai_models 表有记录则验证）
        $aiModel = \App\Models\AiModel::where('model_id', $model)->where('type', 'image')->first();
        if ($aiModel && !$aiModel->is_active) {
            return response()->json(['error' => '模型已停用'], 422);
        }
        if ($aiModel) {
            $cfg = $aiModel->config ?? [];
            if (!empty($cfg['sizes']) && !in_array($sizeForValidation, $cfg['sizes'], true)) {
                return response()->json(['error' => '该模型不支持此尺寸'], 422);
            }
            if (!empty($cfg['qualities']) && !in_array($quality, $cfg['qualities'], true)) {
                return response()->json(['error' => '该模型不支持此质量'], 422);
            }
        }

        if (!$billing->canAfford($user, $model, $quality, 'image-gen', $count, $agentSite)) {
            return response()->json(['error' => '积分不足，请先充值'], 402);
        }

        $channel = AiChannel::where('status', 'active')
            ->where('app_name', 'image-gen')
            ->where(function ($q) use ($model) {
                $q->whereJsonContains('models', $model)
                  ->orWhereNull('models')
                  ->orWhereJsonLength('models', 0);
            })
            ->orderBy('priority', 'desc')
            ->inRandomOrder()
            ->get()
            ->first(fn (AiChannel $channel) => $dispatcher->supportsRequestedModel($channel, $model));

        if (!$channel) {
            return response()->json(['error' => '暂无可用渠道，请联系管理员'], 503);
        }

        try {
            $usageLog = $billing->charge($user, $quality, [
                'app_name' => 'image-gen',
                'model' => $request->input('model', 'gpt-image-2'),
                'channel_id' => $channel->id,
                'count' => $count,
                'agent_site' => $agentSite,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 402);
        }

        $files = [];
        if ($mode === 'image') {
            $fileUrls = $request->input('file_urls', []);
            Log::debug('image-gen file_urls received', [
                'user_id' => $user->id,
                'file_urls' => $fileUrls,
                'request_host' => $request->getHost(),
                'app_url' => config('app.url'),
                'storage_url' => \App\Models\SiteSetting::get('storage_url', ''),
            ]);
            if (!empty($fileUrls) && is_array($fileUrls)) {
                $storageUrl = \App\Models\SiteSetting::get('storage_url', '');
                $storageHost = $storageUrl ? (parse_url($storageUrl, PHP_URL_HOST) ?: '') : '';
                $storageEndpoint = \App\Models\SiteSetting::get('storage_endpoint', '');
                $endpointHost = $storageEndpoint ? (parse_url($storageEndpoint, PHP_URL_HOST) ?: '') : '';
                $bucket = \App\Models\SiteSetting::get('storage_bucket', '');
                $endpointPublicHost = $endpointHost;
                if ($bucket !== '' && $endpointHost !== '' && !str_starts_with($endpointHost, $bucket . '.')) {
                    $endpointPublicHost = $bucket . '.' . $endpointHost;
                }
                $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: '';
                $requestHost = $request->getHost();
                $allowedHosts = array_values(array_unique(array_filter([$storageHost, $endpointHost, $endpointPublicHost, $appHost, $requestHost])));
                $localHosts = ['127.0.0.1', 'localhost', '0.0.0.0'];
                if (app()->environment('local') || in_array($appHost, $localHosts, true) || in_array($requestHost, $localHosts, true)) {
                    $allowedHosts = array_values(array_unique(array_merge($allowedHosts, $localHosts)));
                }
                foreach (array_slice($fileUrls, 0, 4) as $url) {
                    if (preg_match('#^/storage/.+$#', $url)) {
                        $files[] = ['name' => basename($url), 'mime_type' => 'image/png', 'url' => $url];
                        continue;
                    }
                    if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
                    $host = parse_url($url, PHP_URL_HOST) ?: '';
                    if (empty($allowedHosts) || !in_array($host, $allowedHosts, true)) continue;
                    $files[] = ['name' => basename(parse_url($url, PHP_URL_PATH)), 'mime_type' => 'image/png', 'url' => $url];
                }
            } elseif ($request->hasFile('image')) {
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
        }

        if ($mode === 'image' && empty($files)) {
            Log::warning('image-gen rejected empty image files', [
                'user_id' => $user->id,
                'file_urls' => $request->input('file_urls', []),
            ]);
            $billing->refundLog($usageLog);
            return response()->json(['error' => '参考图上传失败或地址无效，请重新上传后再生成'], 422);
        }

        try {
            $taskId = bin2hex(random_bytes(16));
            $task = GenerationTask::create([
                'task_id' => $taskId,
                'user_id' => $user->id,
                'status' => 'pending',
                'mode' => $mode,
                'model' => $model,
                'prompt' => $prompt,
                'size' => $size,
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

    protected function normalizeImageSize(string $size): string
    {
        $map = [
            '1:1' => '1024x1024',
            '3:2' => '1536x1024',
            '2:3' => '1024x1536',
            '16:9' => '1824x1024',
            '9:16' => '1024x1824',
            '4:3' => '1536x1152',
            '3:4' => '1152x1536',
            '5:4' => '1280x1024',
            '4:5' => '1024x1280',
            '2:1' => '1536x768',
            '1:2' => '768x1536',
            '3:1' => '1536x512',
            '1:3' => '512x1536',
            '21:9' => '1792x768',
        ];

        return $map[$size] ?? $size;
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
        // 修正数据库中固化的绝对 URL 为相对路径
        $doneItems = array_map(function ($item) {
            $item['url'] = GalleryController::normalizeImageUrl($item['url'] ?? null) ?? $item['url'];
            return $item;
        }, $doneItems);
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
        $items = $task->items ?? [];
        $completedItems = array_values(array_filter($items, fn($i) => is_array($i) && !empty($i['url'])));

        if (!empty($completedItems)) {
            $affected = GenerationTask::where('task_id', $task->task_id)
                ->whereIn('status', ['pending', 'processing'])
                ->update([
                    'status'       => 'completed',
                    'message'      => count($completedItems) . "/{$task->count} 张成功（部分超时）",
                    'items'        => json_encode($completedItems),
                    'completed_at' => now(),
                    'error'        => $reason,
                ]);
            if ($affected > 0) {
                try { $task->user?->notify(new \App\Notifications\TaskCompleted($task->fresh())); } catch (\Throwable) {}
            }
            return;
        }

        $affected = GenerationTask::where('task_id', $task->task_id)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status'  => 'failed',
                'message' => '生成失败，已自动退款，请重试',
                'error'   => $reason,
            ]);

        if ($affected === 0) return;

        $log = \App\Models\UsageLog::where('task_id', $task->task_id)->whereNull('refunded_at')->first();
        if ($log) {
            app(\App\Services\BillingService::class)->refundLog($log);
        }

        try { $task->user?->notify(new \App\Notifications\TaskFailed($task->fresh(), '任务超时未完成，已自动退款。')); } catch (\Throwable) {}
    }

    protected function retryStuckTask(GenerationTask $task): void
    {
        // 原子抢锁：只有一个进程能成功更新（防止与 cron 竞态重复推送）
        $affected = GenerationTask::where('task_id', $task->task_id)
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<', now()->subMinutes(5))
            ->where(function ($q) {
                $q->whereNull('attempts')->orWhere('attempts', '<', 3);
            })
            ->update([
                'status' => 'pending',
                'message' => '正在重试...',
                'attempts' => DB::raw('COALESCE(attempts, 0) + 1'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) return; // 被其他进程抢了或条件不再满足

        $task->refresh();
        $items = $task->items ?? [];
        for ($i = 0; $i < $task->count; $i++) {
            if (!isset($items[$i]) || $items[$i] === null) {
                Redis::rpush('image_gen_tasks', json_encode(['task_id' => $task->task_id, 'index' => $i]));
            }
        }
    }
}
