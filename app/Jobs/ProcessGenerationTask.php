<?php

namespace App\Jobs;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Notifications\TaskCompleted;
use App\Notifications\TaskFailed;
use App\Services\ImageStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ProcessGenerationTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 420; // 单张图最多7分钟

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected string $taskId,
        protected int $index = 0,
        protected string $apiKey = '',
    ) {}

    public function handle(ImageStorageService $storage): void
    {
        $task = GenerationTask::findOrFail($this->taskId);

        if ($task->status === 'failed') {
            return; // 已被标记失败，不再处理
        }

        // 标记为 processing（仅首个 job 触发）
        if ($task->status === 'pending') {
            $task->update(['status' => 'processing', 'message' => '正在生成图片...']);
        }

        try {
            $apiKey = $this->resolveApiKey($task);
            $response = $this->callApi($task, $apiKey);
            $extracted = $this->extractItems($response);

            if (empty($extracted)) {
                throw new RuntimeException('上游接口未返回图片数据。');
            }

            $savedItem = $this->storeItem($extracted[0], $task, $this->index, $storage);

            // 原子写入 items[$index] 并检查是否全部完成
            $result = $this->saveItemAtIndex($task, $this->index, $savedItem);

            if ($result['all_done'] && $result['status'] === 'completed') {
                $task->refresh();
                try {
                    $task->user?->notify(new TaskCompleted($task));
                } catch (\Throwable) {}
            }

        } catch (Throwable $e) {
            if ($this->isNonRetryableError($e)) {
                $this->markSingleFailed($task, $e);
                return;
            }

            // sync 驱动不支持重试，直接标记失败
            if (app()->bound('queue.connection') && config('queue.default') === 'sync') {
                $this->markSingleFailed($task, $e);
                return;
            }

            $attemptsLeft = max(0, $this->tries - $this->attempts());
            if ($attemptsLeft > 0) {
                throw $e; // 让 Laravel 重试
            }

            // 最终失败：标记该 index 失败
            $this->markSingleFailed($task, $e);
        }
    }

    /**
     * 单张图最终失败处理
     */
    protected function markSingleFailed(GenerationTask $task, Throwable $e): void
    {
        $result = $this->saveItemAtIndex($task, $this->index, false);

        if ($result['all_done']) {
            $task->refresh();
            if ($result['status'] === 'failed') {
                $task->update([
                    'message' => $this->friendlyMessage($e) . '（已自动退款）',
                    'error' => $e->getMessage(),
                ]);
                $this->refund($task);
                try {
                    $task->user?->notify(new TaskFailed($task, $this->friendlyMessage($e)));
                } catch (\Throwable) {}
            } else {
                try {
                    $task->user?->notify(new TaskCompleted($task));
                } catch (\Throwable) {}
            }
        }
    }

    /**
     * 原子写入 items[$index]
     */
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

    /**
     * 所有重试都失败后（Laravel 自动调用）
     */
    public function failed(Throwable $e): void
    {
        $task = GenerationTask::find($this->taskId);
        if (!$task) return;

        $this->markSingleFailed($task, $e);
    }

    protected function refund(GenerationTask $task): void
    {
        $usageLog = UsageLog::where('task_id', $this->taskId)->first();
        if ($usageLog && $task->user && is_null($usageLog->refunded_at)) {
            $user = $task->user;
            if ($usageLog->cost_credits > 0) {
                $user->increment('credits', $usageLog->cost_credits);
            }
            if ($usageLog->cost_balance > 0) {
                $user->increment('balance', $usageLog->cost_balance);
            }
            $usageLog->update(['refunded_at' => now()]);
        }
    }

    protected function resolveApiKey(GenerationTask $task): string
    {
        if ($this->apiKey) {
            return $this->apiKey;
        }

        $channel = AiChannel::where('status', 'active')
            ->where('app_name', 'image-gen')
            ->orderBy('priority', 'desc')
            ->inRandomOrder()
            ->first();

        if ($channel) {
            return $channel->api_key;
        }

        throw new RuntimeException('暂无可用渠道');
    }

    protected function callApi(GenerationTask $task, string $apiKey): array
    {
        $mode = $task->mode;
        $path = $mode === 'image' ? '/v1/images/edits' : '/v1/images/generations';
        $size = $this->resolveSizeToPixels($task->size, $task->quality);

        $channel = AiChannel::where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();

        $baseUrl = $channel?->base_url ?? 'https://api.openai.com';
        $endpoint = rtrim($baseUrl, '/') . $path;

        if ($mode === 'image' && !empty($task->files)) {
            return $this->callMultipartApi($endpoint, $apiKey, $task, $size);
        }

        $body = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'size' => $size,
            'quality' => $task->quality,
            'n' => 1,
        ];

        $response = Http::timeout(360)
            ->connectTimeout(15)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $body);

        if (!$response->successful()) {
            throw new RuntimeException("上游返回 {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        return $response->json() ?? [];
    }

    protected function callMultipartApi(string $endpoint, string $apiKey, GenerationTask $task, string $size): array
    {
        $pending = Http::timeout(360)
            ->connectTimeout(15)
            ->withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->asMultipart()
            ->attach('prompt', $task->prompt)
            ->attach('model', $task->model)
            ->attach('size', $size)
            ->attach('quality', $task->quality)
            ->attach('n', '1');

        $files = $task->files ?? [];
        foreach ($files as $i => $file) {
            if (!empty($file['path']) && file_exists($file['path'])) {
                $fieldName = $i === 0 ? 'image' : "image[]";
                $pending = $pending->attach(
                    $fieldName,
                    file_get_contents($file['path']),
                    $file['name'] ?? "image_{$i}.png"
                );
            }
        }

        $response = $pending->post($endpoint);

        if (!$response->successful()) {
            throw new RuntimeException("上游返回 {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        return $response->json() ?? [];
    }

    protected function extractItems(array $response): array
    {
        $items = $response['data'] ?? [];
        return array_values(array_filter($items, fn($item) =>
            is_array($item) && (!empty($item['b64_json']) || !empty($item['url']))
        ));
    }

    protected function storeItem(array $item, GenerationTask $task, int $index, ImageStorageService $storage): array
    {
        if (!empty($item['b64_json'])) {
            $binary = base64_decode($item['b64_json'], true);
            if ($binary === false) {
                throw new RuntimeException('Failed to decode image data.');
            }
            $mimeType = $storage->detectMimeFromBinary($binary);
        } elseif (!empty($item['url'])) {
            [$binary, $mimeType] = $storage->fetchRemoteImage($item['url']);
        } else {
            throw new RuntimeException('Missing image payload.');
        }

        $targetSize = $this->resolveSizeToPixels($task->size, $task->quality);
        $binary = $this->enforceSize($binary, $targetSize);

        $key = $storage->store($binary, $mimeType);

        return [
            'key' => $key,
            'url' => $storage->url($key),
            'mime_type' => $mimeType,
            'size' => strlen($binary),
        ];
    }

    protected function enforceSize(string $binary, string $targetSize): string
    {
        if ($targetSize === 'auto' || !preg_match('/^(\d+)x(\d+)$/', $targetSize, $m)) {
            return $binary;
        }

        $targetW = (int) $m[1];
        $targetH = (int) $m[2];

        $img = @imagecreatefromstring($binary);
        if (!$img) {
            return $binary;
        }

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

    protected function resolveSizeToPixels(string $size, string $quality): string
    {
        if ($size === 'auto' || preg_match('/^\d+x\d+$/', $size)) {
            return $size;
        }

        if (!preg_match('/^(\d+):(\d+)$/', $size, $m)) {
            return $size;
        }

        $ratio = (int) $m[1] / (int) $m[2];
        $maxDim = match ($quality) {
            'high' => 3840,
            'medium' => 2048,
            default => 1024,
        };

        if ($ratio >= 1) {
            $w = $maxDim;
            $h = (int) round($maxDim / $ratio);
        } else {
            $h = $maxDim;
            $w = (int) round($maxDim * $ratio);
        }

        $w = max(16, (int) (round($w / 16) * 16));
        $h = max(16, (int) (round($h / 16) * 16));

        return "{$w}x{$h}";
    }

    protected function friendlyMessage(Throwable $e): string
    {
        $msg = $e->getMessage();

        if (preg_match('/上游返回\s*(\d{3})/', $msg, $m)) {
            $code = (int) $m[1];
            return match (true) {
                $code === 400 || $code === 422 => '参数无法被上游接受，请调整提示词或图片后重试。',
                $code === 401 || $code === 403 => '生成服务认证失败，请联系管理员。',
                $code === 429                  => '触发速率限制，请稍后再试。',
                $code >= 500                   => '生成服务暂时不可用，请稍后重试。',
                default                        => '生成请求未成功，请稍后重试。',
            };
        }

        if (str_contains($msg, 'cURL') || str_contains($msg, 'timeout') || str_contains($msg, 'timed out')) {
            return '网络连接异常，请稍后重试。';
        }

        if (str_contains($msg, '未返回图片')) {
            return '上游未返回任何图片，请调整提示词后重试。';
        }

        $clean = preg_replace('/https?:\/\/\S+/', '[url]', $msg);
        $clean = mb_substr(trim((string) $clean), 0, 120);
        return $clean !== '' ? $clean : '生成失败，请稍后重试。';
    }

    protected function isNonRetryableError(Throwable $e): bool
    {
        $message = $e->getMessage();
        if (preg_match('/上游返回 (400|401|403|422)/', $message)) {
            return true;
        }
        if (str_contains($message, '余额不足') || str_contains($message, 'insufficient')) {
            return true;
        }
        return false;
    }
}
