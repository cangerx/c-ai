<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenerationTask;
use App\Models\ImageAsyncJob;
use App\Models\UsageLog;
use App\Notifications\TaskCompleted;
use App\Notifications\TaskFailed;
use App\Services\BillingService;
use App\Services\ImageStorageService;
use App\Services\StorageProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * async-oo 上游回调入口
 *
 * URL: POST /api/channels/async-oo/callback/{token}
 *
 * 上游 body 预期（OpenAI 兼容）：
 *   { "id": "task_xxx", "data": [{ "url": "..." } | { "b64_json": "..." }] }
 *   或失败：{ "error": { "message": "..." } }
 *
 * 鉴权：URL 中的 token 必须匹配 image_async_jobs.callback_token（64 hex）
 *
 * 幂等：同 token 重复回调时直接返回已完成状态，不重复入库扣库存
 */
class AsyncCallbackController extends Controller
{
    public function __invoke(string $token, Request $request, ImageStorageService $storage): JsonResponse
    {
        // 1. 找映射
        $job = ImageAsyncJob::where('callback_token', $token)->first();
        if (!$job) {
            Log::channel('upstream')->warning('async_callback_unknown_token', ['token' => substr($token, 0, 8)]);
            return response()->json(['ok' => false, 'error' => 'unknown token'], 404);
        }

        // 2. 幂等：已完成的不重复处理
        if ($job->status === 'completed') {
            return response()->json(['ok' => true, 'message' => 'already completed']);
        }

        $body = $request->all();

        // 3. 上游失败
        if (!empty($body['error'])) {
            $errMsg = $body['error']['message'] ?? json_encode($body['error']);
            $this->markJobFailed($job, $errMsg, $body);
            return response()->json(['ok' => true]);
        }

        // 4. 提取图片项
        $extracted = $this->extractItems($body);
        if (empty($extracted)) {
            $errMsg = '回调中无可用图片数据';
            $this->markJobFailed($job, $errMsg, $body);
            return response()->json(['ok' => false, 'error' => $errMsg], 422);
        }

        // 5. 保存
        $task = GenerationTask::where('task_id', $job->task_id)->first();
        if (!$task) {
            $this->markJobFailed($job, '找不到对应任务', $body);
            return response()->json(['ok' => false, 'error' => 'task not found'], 404);
        }

        try {
            $savedItem = $this->storeItem($extracted[0], $task, $storage);
            $result = $this->saveItemAtIndex($task, $job->index, $savedItem);
            $job->update([
                'status' => 'completed',
                'payload' => $body,
                'completed_at' => now(),
            ]);

            if ($result['all_done'] && $result['status'] === 'completed') {
                $task->refresh();
                try { $task->user?->notify(new TaskCompleted($task)); } catch (Throwable) {}
            } elseif ($result['all_done'] && $result['status'] === 'failed') {
                $task->refresh();
                $this->refund($task);
                try { $task->user?->notify(new TaskFailed($task, '生成失败')); } catch (Throwable) {}
            }
        } catch (Throwable $e) {
            $this->markJobFailed($job, $e->getMessage(), $body);
            Log::channel('upstream')->error('async_callback_store_failed', [
                'job_id' => $job->id,
                'task_id' => $job->task_id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'store failed'], 500);
        }

        return response()->json(['ok' => true]);
    }

    protected function markJobFailed(ImageAsyncJob $job, string $error, array $payload): void
    {
        $job->update([
            'status' => 'failed',
            'error' => mb_substr($error, 0, 500),
            'payload' => $payload,
            'completed_at' => now(),
        ]);

        // 同步把对应 GenerationTask 的该 index 标记失败
        $task = GenerationTask::where('task_id', $job->task_id)->first();
        if ($task) {
            $result = $this->saveItemAtIndex($task, $job->index, false);
            if ($result['all_done'] && $result['status'] === 'failed') {
                $task->refresh();
                $task->update([
                    'message' => '生成失败：' . mb_substr($error, 0, 100) . '（已自动退款）',
                    'error' => $error,
                ]);
                $this->refund($task);
                try { $task->user?->notify(new TaskFailed($task, '生成失败')); } catch (Throwable) {}
            }
        }
    }

    protected function refund(GenerationTask $task): void
    {
        $log = UsageLog::where('task_id', $task->task_id)->whereNull('refunded_at')->first();
        if ($log) {
            app(BillingService::class)->refundLog($log);
        }
    }

    // ─── 以下逻辑与 TaskWorkerCommand 保持一致（提取/保存/扣完成度）───

    protected function extractItems(array $response): array
    {
        $items = $response['data'] ?? [];
        if (!is_array($items)) return [];
        return array_values(array_filter(array_map(fn ($item) => $this->normalizeImageItem($item), $items)));
    }

    protected function normalizeImageItem(mixed $item): ?array
    {
        if (!is_array($item)) return null;

        if (!empty($item['b64_json'])) {
            return ['b64_json' => $item['b64_json']];
        }
        foreach (['url', 'image_url', 'imageUrl', 'result_url', 'resultUrl', 'output_url', 'outputUrl', 'download_url', 'downloadUrl'] as $key) {
            if (!empty($item[$key]) && is_string($item[$key])) {
                return ['url' => $item[$key]];
            }
        }
        foreach (['base64', 'image_base64', 'imageBase64', 'data'] as $key) {
            if (!empty($item[$key]) && is_string($item[$key])) {
                if (str_starts_with($item[$key], 'data:image/')) {
                    $parts = explode(',', $item[$key], 2);
                    return !empty($parts[1]) ? ['b64_json' => $parts[1]] : null;
                }
                if (strlen($item[$key]) > 200 && !preg_match('/^https?:\/\//', $item[$key])) {
                    return ['b64_json' => preg_replace('/\s+/', '', $item[$key])];
                }
            }
        }
        return null;
    }

    protected function storeItem(array $item, GenerationTask $task, ImageStorageService $storage): array
    {
        $originUrl = $item['url'] ?? null;

        if (!empty($item['b64_json'])) {
            $binary = base64_decode($item['b64_json'], true);
            if ($binary === false) throw new RuntimeException('Failed to decode image data.');
            $mimeType = $storage->detectMimeFromBinary($binary);
        } elseif (!empty($originUrl) && str_starts_with($originUrl, 'data:image/')) {
            [$meta, $data] = array_pad(explode(',', $originUrl, 2), 2, '');
            $binary = base64_decode($data, true);
            if ($binary === false) throw new RuntimeException('Failed to decode data URI image.');
            $mimeType = preg_match('#^data:(image/[^;]+);base64#', $meta, $m) ? $m[1] : $storage->detectMimeFromBinary($binary);
            $originUrl = null;
        } elseif (!empty($originUrl)) {
            [$binary, $mimeType] = $storage->fetchRemoteImage($originUrl);
        } else {
            throw new RuntimeException('Missing image payload.');
        }

        $key = $storage->store($binary, $mimeType, StorageProfileService::PURPOSE_GENERATED);

        $result = [
            'key' => $key,
            'url' => $storage->url($key, StorageProfileService::PURPOSE_GENERATED),
            'purpose' => StorageProfileService::PURPOSE_GENERATED,
            'mime_type' => $mimeType,
            'size' => strlen($binary),
        ];
        if ($originUrl) $result['origin_url'] = $originUrl;

        return $result;
    }

    protected function saveItemAtIndex(GenerationTask $task, int $index, mixed $value): array
    {
        $result = ['all_done' => false, 'completed' => 0, 'status' => 'processing'];

        DB::transaction(function () use ($task, $index, $value, &$result) {
            $fresh = GenerationTask::where('task_id', $task->task_id)->lockForUpdate()->first();
            if (in_array($fresh->status, ['failed', 'completed'])) {
                $result['all_done'] = true;
                $result['status'] = $fresh->status;
                return;
            }
            $items = $fresh->items ?? [];
            $items[$index] = $value;

            $completed = count(array_filter($items, fn ($i) => is_array($i)));
            $failed = count(array_filter($items, fn ($i) => $i === false));
            $result['completed'] = $completed;

            if (($completed + $failed) >= $fresh->count) {
                $result['all_done'] = true;
                if ($completed > 0) {
                    $fresh->status = 'completed';
                    $fresh->message = $completed >= $fresh->count
                        ? '生成完成。'
                        : "生成完成（{$completed}/{$fresh->count} 张成功）。";
                    $fresh->items = array_values(array_filter($items, fn ($i) => is_array($i)));
                    $fresh->completed_at = now();
                    $fresh->error = null;
                    $result['status'] = 'completed';
                } else {
                    $fresh->status = 'failed';
                    $fresh->message = '生成失败';
                    $fresh->items = [];
                    $result['status'] = 'failed';
                }
            } else {
                $fresh->items = $items;
                $fresh->message = "已完成 {$completed}/{$fresh->count} 张";
            }

            $fresh->save();
        });

        return $result;
    }
}
