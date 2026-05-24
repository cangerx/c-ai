<?php

namespace App\Services\ImageProviders;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Services\CurlClient;
use RuntimeException;

class OpenAiProvider implements ImageProviderInterface
{
    public function generate(GenerationTask $task, AiChannel $channel): array
    {
        $size = $this->normalizeSize($task->size ?: 'auto');
        $requestMode = $channel->request_mode ?? 'sync';

        $imageUrls = [];
        if ($task->mode === 'image' && !empty($task->files)) {
            $imageUrls = array_values(array_filter(array_column($task->files, 'url')));
        }

        $hasImages = !empty($imageUrls);
        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);

        if ($hasImages && $requestMode !== 'async') {
            $localFiles = $this->resolveLocalFiles($imageUrls);
            $endpoint = $baseUrl . '/v1/images/edits';

            if ($localFiles) {
                $resp = $this->postMultipartEdit($endpoint, $channel->api_key, $task, $size, $localFiles);
            } else {
                $body = [
                    'model' => $task->model,
                    'prompt' => $task->prompt,
                    'size' => $size,
                    'quality' => $task->quality,
                    'n' => 1,
                    'images' => array_map(fn($u) => ['image_url' => $u], $imageUrls),
                ];
                $resp = CurlClient::post($endpoint, $body, [
                    'Authorization' => "Bearer {$channel->api_key}",
                    'Content-Type' => 'application/json',
                ], 300, 15);
            }
        } else {
            $path = '/v1/images/generations';
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

            if ($hasImages && $requestMode === 'async') {
                $body['image'] = count($imageUrls) === 1 ? $imageUrls[0] : $imageUrls;
            }

            $resp = CurlClient::post($endpoint, $body, [
                'Authorization' => "Bearer {$channel->api_key}",
                'Content-Type' => 'application/json',
            ], 300, 15);
        }

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new RuntimeException("上游返回 {$resp['status']}: " . mb_substr($resp['body'], 0, 300));
        }

        $json = $resp['json'];

        if ($requestMode === 'async') {
            $json = $this->pollAsyncResult($channel, $json['id'] ?? '', $task);
        }

        return $json;
    }

    protected function postMultipartEdit(string $endpoint, string $apiKey, GenerationTask $task, string $size, array $localFiles): array
    {
        $fields = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'size' => $size,
            'quality' => $task->quality,
            'n' => 1,
        ];

        if (count($localFiles) === 1) {
            $fields['image'] = $this->makeCurlFile($localFiles[0]);
        } else {
            foreach ($localFiles as $i => $filePath) {
                $fields["image[{$i}]"] = $this->makeCurlFile($filePath);
            }
        }

        return CurlClient::postMultipart($endpoint, $fields, [
            'Authorization' => "Bearer {$apiKey}",
        ], 300, 15);
    }

    protected function makeCurlFile(string $filePath): \CURLFile
    {
        return new \CURLFile(
            $filePath,
            mime_content_type($filePath) ?: 'application/octet-stream',
            basename($filePath)
        );
    }

    protected function resolveLocalFiles(array $urls): array
    {
        $files = [];
        foreach ($urls as $url) {
            $file = $this->localFilePathFromUrl($url);
            if (!$file) {
                return [];
            }
            $files[] = $file;
        }

        return $files;
    }

    protected function pollAsyncResult(AiChannel $channel, string $asyncId, ?GenerationTask $task = null): array
    {
        if (empty($asyncId)) {
            throw new RuntimeException('异步接口未返回任务 ID');
        }

        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);
        $pollUrl = $baseUrl . '/v1/tasks/' . $asyncId;

        for ($i = 0; $i < 120; $i++) {
            sleep(5);
            $this->heartbeat($task);

            $resp = CurlClient::get($pollUrl, [
                'Authorization' => "Bearer {$channel->api_key}",
            ], 30, 10);

            if ($resp['status'] < 200 || $resp['status'] >= 300) {
                if ($resp['status'] === 404) continue;
                throw new RuntimeException("轮询异步结果失败 HTTP {$resp['status']}");
            }

            $result = $resp['json'];
            $state = strtolower(trim((string) ($result['state'] ?? $result['status'] ?? '')));

            if (in_array($state, ['failed', 'fail', 'error', 'cancelled', 'canceled'], true)) {
                throw new RuntimeException('上游异步任务失败: ' . ($result['data']['description'] ?? ''));
            }

            if (in_array($state, ['succeeded', 'succeed', 'completed', 'complete', 'success', 'done', 'finished', 'finish'], true)) {
                $images = $result['data']['images'] ?? $result['images'] ?? [];
                return ['data' => array_values(array_filter(array_map(
                    fn($img) => !empty($img['url']) ? ['url' => $img['url']] : null,
                    $images,
                )))];
            }
        }

        throw new RuntimeException('异步任务超时：轮询 10 分钟未获得结果');
    }

    protected function heartbeat(?GenerationTask $task): void
    {
        if (!$task) {
            return;
        }

        GenerationTask::where('task_id', $task->task_id)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'processing',
                'message' => '等待上游生成结果...',
                'updated_at' => now(),
            ]);
    }

    protected function localFilePathFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        // 相对路径 /storage/... 直接解析
        if (empty($host) && preg_match('#^/storage/(.+)$#', $url, $m)) {
            $base = realpath(storage_path('app/public'));
            if (!$base) return null;
            $filePath = realpath(storage_path('app/public/' . $m[1]));
            if (!$filePath || !str_starts_with($filePath, $base . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
                return null;
            }
            return $filePath;
        }

        if (!in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0'], true)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if (!preg_match('#^/storage/(.+)$#', $path, $m)) {
            return null;
        }

        $base = realpath(storage_path('app/public'));
        if (!$base) {
            return null;
        }

        $filePath = realpath(storage_path('app/public/' . $m[1]));
        if (!$filePath || !str_starts_with($filePath, $base . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
            return null;
        }

        return $filePath;
    }

    protected function normalizeSize(string $size): string
    {
        $map = [
            '1:1' => '1024x1024',
            '4:3' => '1536x1152',
            '3:4' => '1152x1536',
            '16:9' => '1824x1024',
            '9:16' => '1024x1824',
            '3:2' => '1536x1024',
            '2:3' => '1024x1536',
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
}
