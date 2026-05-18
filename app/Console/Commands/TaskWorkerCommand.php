<?php

namespace App\Console\Commands;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Notifications\TaskCompleted;
use App\Notifications\TaskFailed;
use App\Services\ChannelDispatcher;
use App\Services\ImageStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

class TaskWorkerCommand extends Command
{
    protected $signature = 'task:worker {--max-retries=3}';
    protected $description = 'BLPOP 消费图片生成任务';

    public function handle(ChannelDispatcher $dispatcher, ImageStorageService $storage): void
    {
        $this->info('Task worker started, waiting for jobs...');

        while (true) {
            $payload = Redis::blpop('image_gen_tasks', 10);
            if (!$payload) continue;

            $data = json_decode($payload[1], true);
            if (!$data || empty($data['task_id'])) continue;

            try {
                $this->processTask($data['task_id'], $data['index'] ?? 0, $dispatcher, $storage);
            } catch (Throwable $e) {
                Log::error('task:worker uncaught', ['task_id' => $data['task_id'], 'error' => $e->getMessage()]);
            }
        }
    }

    protected function processTask(string $taskId, int $index, ChannelDispatcher $dispatcher, ImageStorageService $storage): void
    {
        $task = GenerationTask::find($taskId);
        if (!$task || in_array($task->status, ['failed', 'completed'])) {
            return;
        }

        if ($task->status === 'pending') {
            $task->update(['status' => 'processing', 'message' => '正在生成图片...']);
        } else {
            $task->touch();
        }

        $maxRetries = (int) $this->option('max-retries');
        $lastExclude = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $released = false;
            $channel = $dispatcher->acquire('image-gen', $lastExclude)
                ?? $dispatcher->acquire('image-gen');
            if (!$channel) {
                $lastException = new RuntimeException('无可用渠道');
                if ($attempt < $maxRetries) {
                    sleep(3 * $attempt);
                }
                continue;
            }

            $task->touch();

            try {
                $start = microtime(true);
                $response = $this->callApi($task, $channel);
                $elapsed = round(microtime(true) - $start, 2);

                // 记录上游响应头
                Log::channel('upstream')->info('response', [
                    'task_id' => $taskId,
                    'channel_id' => $channel->id,
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'elapsed' => $elapsed,
                ]);

                if (!$response->successful()) {
                    throw new RuntimeException("上游返回 {$response->status()}: " . mb_substr($response->body(), 0, 300));
                }

                $json = $response->json() ?? [];

                // 异步模式需要轮询
                if (($channel->request_mode ?? 'sync') === 'async') {
                    $json = $this->pollAsyncResult($channel, $json['id'] ?? '');
                }

                // API 调用成功，释放负载
                $dispatcher->release($channel->id);
                $released = true;

                // 记录渠道
                if ($index === 0) {
                    UsageLog::where('task_id', $taskId)->update(['channel_id' => $channel->id]);
                }

                $extracted = $this->extractItems($json);
                if (empty($extracted)) {
                    throw new RuntimeException('上游接口未返回图片数据。');
                }

                $savedItem = $this->storeItem($extracted[0], $task, $storage);
                $result = $this->saveItemAtIndex($task, $index, $savedItem);

                if ($result['all_done'] && $result['status'] === 'completed') {
                    $task->refresh();
                    try { $task->user?->notify(new TaskCompleted($task)); } catch (Throwable) {}
                }

                return; // 成功，退出

            } catch (Throwable $e) {
                if (empty($released)) {
                    $dispatcher->reportError($channel->id);
                }
                $lastExclude = $channel->id;
                $lastException = $e;

                if ($this->isNonRetryableError($e)) {
                    break;
                }
                if ($attempt < $maxRetries) {
                    sleep(2 * $attempt);
                }
            }
        }

        // 全部重试失败
        $this->markFailed($task, $taskId, $index, $lastException);
    }

    // ─── API 调用 ───

    protected function callApi(GenerationTask $task, AiChannel $channel): \Illuminate\Http\Client\Response
    {
        $mode = $task->mode;
        $size = $task->size ?: 'auto';
        $requestMode = $channel->request_mode ?? 'sync';

        // 判断是否有参考图
        $imageUrls = [];
        if ($mode === 'image' && !empty($task->files)) {
            $imageUrls = array_values(array_filter(array_column($task->files, 'url')));
        }

        $hasImages = !empty($imageUrls);
        $path = ($hasImages && $requestMode !== 'async') ? '/v1/images/edits' : '/v1/images/generations';
        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);
        $endpoint = $baseUrl . $path;

        if ($requestMode === 'async') {
            $endpoint .= '?async=true';
        }

        $body = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'size' => $size,
            'quality' => $task->quality,
            'n' => 1,
        ];

        if ($hasImages) {
            if ($requestMode === 'async') {
                $body['image'] = count($imageUrls) === 1 ? $imageUrls[0] : $imageUrls;
            } else {
                $body['images'] = array_map(fn($u) => ['image_url' => $u], $imageUrls);
            }
        }

        return Http::timeout(300)
            ->connectTimeout(15)
            ->withHeaders([
                'Authorization' => "Bearer {$channel->api_key}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $body);
    }


    protected function pollAsyncResult(AiChannel $channel, string $asyncId): array
    {
        if (empty($asyncId)) {
            throw new RuntimeException('异步接口未返回任务 ID');
        }

        $pollUrl = $baseUrl . '/v1/tasks/' . $asyncId;

        for ($i = 0; $i < 120; $i++) {
            sleep(5);

            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withHeaders(['Authorization' => "Bearer {$channel->api_key}"])
                ->get($pollUrl);

            if (!$response->successful()) {
                if ($response->status() === 404) continue;
                throw new RuntimeException("轮询异步结果失败 HTTP {$response->status()}");
            }

            $result = $response->json() ?? [];
            $state = $result['state'] ?? '';

            if (in_array($state, ['failed', 'error'])) {
                throw new RuntimeException('上游异步任务失败: ' . ($result['data']['description'] ?? ''));
            }

            if ($state === 'succeeded') {
                $images = $result['data']['images'] ?? [];
                return ['data' => array_map(fn($img) => ['url' => $img['url']], $images)];
            }
        }

        throw new RuntimeException('异步任务超时：轮询 10 分钟未获得结果');
    }

    // ─── 结果处理 ───

    protected function extractItems(array $response): array
    {
        $items = $response['data'] ?? [];
        return array_values(array_filter($items, fn($item) =>
            is_array($item) && (!empty($item['b64_json']) || !empty($item['url']))
        ));
    }

    protected function storeItem(array $item, GenerationTask $task, ImageStorageService $storage): array
    {
        $originUrl = $item['url'] ?? null;

        if (!empty($item['b64_json'])) {
            $binary = base64_decode($item['b64_json'], true);
            if ($binary === false) throw new RuntimeException('Failed to decode image data.');
            $mimeType = $storage->detectMimeFromBinary($binary);
        } elseif (!empty($originUrl)) {
            [$binary, $mimeType] = $storage->fetchRemoteImage($originUrl);
        } else {
            throw new RuntimeException('Missing image payload.');
        }

        $targetSize = $task->size ?: 'auto';
        $binary = $this->enforceSize($binary, $targetSize);

        $key = $storage->store($binary, $mimeType);

        $result = ['key' => $key, 'url' => $storage->url($key), 'mime_type' => $mimeType, 'size' => strlen($binary)];
        if ($originUrl) $result['origin_url'] = $originUrl;

        return $result;
    }

    protected function enforceSize(string $binary, string $targetSize): string
    {
        if ($targetSize === 'auto' || !preg_match('/^(\d+)x(\d+)$/', $targetSize, $m)) {
            return $binary;
        }

        $targetW = (int) $m[1];
        $targetH = (int) $m[2];
        $img = @imagecreatefromstring($binary);
        if (!$img) return $binary;

        $actualW = imagesx($img);
        $actualH = imagesy($img);
        if ($actualW === $targetW && $actualH === $targetH) {
            imagedestroy($img);
            return $binary;
        }

        $resized = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $targetW, $targetH, $actualW, $actualH);
        imagedestroy($img);

        ob_start();
        imagepng($resized, null, 6);
        imagedestroy($resized);
        return ob_get_clean();
    }

    protected function saveItemAtIndex(GenerationTask $task, int $index, mixed $value): array
    {
        $result = ['all_done' => false, 'completed' => 0, 'status' => 'processing'];

        DB::transaction(function () use ($task, $index, $value, &$result) {
            $fresh = GenerationTask::where('task_id', $task->task_id)->lockForUpdate()->first();
            $items = $fresh->items ?? [];
            $items[$index] = $value;

            $completed = count(array_filter($items, fn($i) => is_array($i)));
            $failed = count(array_filter($items, fn($i) => $i === false));
            $result['completed'] = $completed;

            if (($completed + $failed) >= $fresh->count) {
                $result['all_done'] = true;
                if ($completed > 0) {
                    $fresh->status = 'completed';
                    $fresh->message = $completed >= $fresh->count ? '生成完成。' : "生成完成（{$completed}/{$fresh->count} 张成功）。";
                    $fresh->items = array_values(array_filter($items, fn($i) => is_array($i)));
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

    // ─── 失败处理 ───

    protected function markFailed(GenerationTask $task, string $taskId, int $index, ?Throwable $e): void
    {
        $result = $this->saveItemAtIndex($task, $index, false);

        if ($result['all_done'] && $result['status'] === 'failed') {
            $task->refresh();
            $task->update([
                'message' => $this->friendlyMessage($e) . '（已自动退款）',
                'error' => $e?->getMessage(),
            ]);
            $this->refund($task, $taskId);
            try { $task->user?->notify(new TaskFailed($task, $this->friendlyMessage($e))); } catch (Throwable) {}
        } elseif ($result['all_done'] && $result['status'] === 'completed') {
            $task->refresh();
            try { $task->user?->notify(new TaskCompleted($task)); } catch (Throwable) {}
        }
    }

    protected function refund(GenerationTask $task, string $taskId): void
    {
        $affected = UsageLog::where('task_id', $taskId)
            ->whereNull('refunded_at')
            ->update(['refunded_at' => now()]);

        if ($affected === 0) return;

        $usageLog = UsageLog::where('task_id', $taskId)->first();
        if ($usageLog && $task->user) {
            if ($usageLog->cost_credits > 0) {
                $task->user->increment('credits', $usageLog->cost_credits);
            }
            if ($usageLog->cost_balance > 0) {
                $task->user->increment('balance', $usageLog->cost_balance);
            }
        }
    }

    protected function friendlyMessage(?Throwable $e): string
    {
        if (!$e) return '生成失败，请稍后重试。';
        $msg = $e->getMessage();

        if (preg_match('/上游返回\s*(\d{3})/', $msg, $m)) {
            $code = (int) $m[1];
            return match (true) {
                $code === 400 || $code === 422 => '参数无法被上游接受，请调整提示词或图片后重试。',
                $code === 401 || $code === 403 => '生成服务认证失败，请联系管理员。',
                $code === 429 => '触发速率限制，请稍后再试。',
                $code >= 500 => '生成服务暂时不可用，请稍后重试。',
                default => '生成请求未成功，请稍后重试。',
            };
        }

        if (str_contains($msg, 'cURL') || str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
            return '网络连接异常，请稍后重试。';
        }

        return '生成失败，请稍后重试。';
    }

    protected function isNonRetryableError(Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '余额不足')
            || str_contains($msg, 'insufficient')
            || str_contains($msg, '无法获取参考图片');
    }
}
