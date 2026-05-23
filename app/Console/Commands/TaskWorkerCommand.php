<?php

namespace App\Console\Commands;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Notifications\TaskCompleted;
use App\Notifications\TaskFailed;
use App\Services\ChannelDispatcher;
use App\Services\ImageProviders\ImageProviderInterface;
use App\Services\ImageProviders\NanoBananaProvider;
use App\Services\ImageProviders\OpenAiProvider;
use App\Services\BillingService;
use App\Services\ImageStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
            GenerationTask::where('task_id', $task->task_id)
                ->where('status', 'pending')
                ->update(['status' => 'processing', 'message' => '正在生成图片...']);
        } else {
            $task->touch();
        }

        $maxRetries = (int) $this->option('max-retries');
        $lastExclude = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $released = false;
            $channel = $dispatcher->acquire('image-gen', $lastExclude, null, $task->model)
                ?? $dispatcher->acquire('image-gen', null, null, $task->model)
                ?? $dispatcher->acquireFallback('image-gen', $lastExclude, $task->model)
                ?? $dispatcher->acquireFallback('image-gen', null, $task->model);
            if (!$channel) {
                $lastException = new RuntimeException('无可用渠道');
                if ($attempt < $maxRetries) {
                    sleep(15 * $attempt);
                }
                continue;
            }

            if (!$this->channelSupportsModel($channel, $task->model)) {
                $dispatcher->release($channel->id);
                Log::channel('upstream')->warning('provider_skipped_model_mismatch', [
                    'task_id' => $taskId,
                    'attempt' => $attempt,
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->display_name ?: $channel->name,
                    'provider' => $channel->provider,
                    'task_model' => $task->model,
                    'channel_model' => $channel->model,
                    'channel_models' => $channel->models,
                ]);
                $lastExclude = $channel->id;
                $lastException = new RuntimeException("渠道 {$channel->id} 不支持模型 {$task->model}");
                continue;
            }

            $task->touch();

            try {
                if ($index === 0) {
                    UsageLog::where('task_id', $taskId)->update(['channel_id' => $channel->id]);
                }

                $start = microtime(true);
                $json = $this->getProvider($channel->provider)->generate($task, $channel);
                $elapsed = round(microtime(true) - $start, 2);

                Log::channel('upstream')->info('provider_response', [
                    'task_id' => $taskId,
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->display_name ?: $channel->name,
                    'provider' => $channel->provider,
                    'elapsed' => $elapsed,
                ]);

                // API 调用成功，释放负载
                $dispatcher->release($channel->id);
                $released = true;

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
                if ($this->isAuthError($e)) {
                    AiChannel::where('id', $channel->id)->update([
                        'status' => 'error',
                        'paused_at' => now(),
                    ]);
                }
                Log::channel('upstream')->warning('provider_failed', [
                    'task_id' => $taskId,
                    'attempt' => $attempt,
                    'channel_id' => $channel->id,
                    'channel_name' => $channel->display_name ?: $channel->name,
                    'provider' => $channel->provider,
                    'error' => $e->getMessage(),
                ]);
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

    // ─── Provider 分发 ───

    protected function channelSupportsModel(AiChannel $channel, ?string $model): bool
    {
        if (!$model) {
            return false;
        }

        return app(ChannelDispatcher::class)->supportsRequestedModel($channel, $model);
    }

    protected function getProvider(?string $provider): ImageProviderInterface
    {
        return match ($provider) {
            'nano-banana' => new NanoBananaProvider(),
            default => new OpenAiProvider(),
        };
    }

    // ─── 结果处理 ───

    protected function extractItems(array $response): array
    {
        $items = $response['data'] ?? [];
        return array_values(array_filter(array_map(fn ($item) => $this->normalizeImageItem($item), $items)));
    }

    protected function normalizeImageItem(mixed $item): ?array
    {
        if (!is_array($item)) {
            return null;
        }

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
            if (in_array($fresh->status, ['failed', 'completed'])) {
                $result['all_done'] = true;
                $result['status'] = $fresh->status;
                return;
            }
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
        $log = UsageLog::where('task_id', $taskId)->whereNull('refunded_at')->first();
        if ($log) {
            app(BillingService::class)->refundLog($log);
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

        if (str_contains($msg, 'model_not_found')) {
            return '当前模型暂不可用，请联系管理员配置。';
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
            || str_contains($msg, '无法获取参考图片')
            || str_contains($msg, 'model_not_found')
            || str_contains($msg, 'Invalid model');
    }

    protected function isAuthError(Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '上游返回 401')
            || str_contains($msg, '上游返回 403')
            || str_contains($msg, 'Invalid token');
    }

}
