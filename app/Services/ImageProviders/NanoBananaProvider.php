<?php

namespace App\Services\ImageProviders;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Services\CurlClient;
use RuntimeException;

class NanoBananaProvider implements ImageProviderInterface
{
    public function generate(GenerationTask $task, AiChannel $channel): array
    {
        $baseUrl = rtrim($channel->base_url, '/');

        $imageUrls = [];
        if ($task->mode === 'image' && !empty($task->files)) {
            $imageUrls = array_values(array_filter(array_column($task->files, 'url')));
        }

        $hasImages = !empty($imageUrls);

        if ($hasImages) {
            $json = $this->editImage($baseUrl, $channel->api_key, $task, $imageUrls);
        } else {
            $json = $this->generateImage($baseUrl, $channel->api_key, $task);
        }

        $taskId = $json['id'] ?? $json['task_id'] ?? $json['data']['task_id'] ?? null;
        if ($taskId) {
            return $this->pollResult($baseUrl, $channel->api_key, $taskId);
        }

        if (!empty($json['data'])) {
            return $json;
        }
        if (!empty($json['url'])) {
            return ['data' => [['url' => $json['url']]]];
        }

        throw new RuntimeException('Nano-Banana 未返回有效结果: ' . json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    protected function generateImage(string $baseUrl, string $apiKey, GenerationTask $task): array
    {
        $body = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'aspect_ratio' => $this->mapAspectRatio($task->size),
            'oversea' => true,
        ];

        $imageSize = $this->getImageSize($task);
        if ($imageSize) {
            $body['image_size'] = $imageSize;
        }

        $resp = CurlClient::post("{$baseUrl}/api/gemini/nano-banana", $body, [
            'Authorization' => $apiKey,
            'Content-Type' => 'application/json',
        ], 300, 15);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new RuntimeException("上游返回 {$resp['status']}: " . mb_substr($resp['body'], 0, 300));
        }

        return $resp['json'];
    }

    protected function editImage(string $baseUrl, string $apiKey, GenerationTask $task, array $imageUrls): array
    {
        $resolvedUrls = array_map(fn($u) => $this->resolveLocalUrl($u), $imageUrls);

        $body = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'image_urls' => array_slice($resolvedUrls, 0, 10),
            'oversea' => true,
        ];

        if (count($imageUrls) > 1 || $task->size !== 'auto') {
            $body['aspect_ratio'] = $this->mapAspectRatio($task->size);
        }

        $imageSize = $this->getImageSize($task);
        if ($imageSize) {
            $body['image_size'] = $imageSize;
        }

        return $this->submitEditWithFallback($baseUrl, $apiKey, $body);
    }

    protected function submitEditWithFallback(string $baseUrl, string $apiKey, array $body): array
    {
        $variants = [];
        foreach ([$body] as $candidate) {
            $variants[] = $candidate;

            if (array_key_exists('image_size', $candidate)) {
                $withoutImageSize = $candidate;
                unset($withoutImageSize['image_size']);
                $variants[] = $withoutImageSize;
            }

            if (array_key_exists('aspect_ratio', $candidate)) {
                $withoutAspectRatio = $candidate;
                unset($withoutAspectRatio['aspect_ratio']);
                $variants[] = $withoutAspectRatio;
            }
        }

        $last = null;
        foreach ($variants as $variant) {
            $resp = CurlClient::post("{$baseUrl}/api/gemini/nano-banana-edit", $variant, [
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ], 300, 15);

            if ($resp['status'] < 200 || $resp['status'] >= 300) {
                throw new RuntimeException("上游返回 {$resp['status']}: " . mb_substr($resp['body'], 0, 300));
            }

            $last = $resp['json'];
            if (($last['code'] ?? 0) !== 400 || ($last['msg'] ?? '') !== 'fail_to_submit_task') {
                return $last;
            }
        }

        return $last ?? [];
    }

    protected function pollResult(string $baseUrl, string $apiKey, string $taskId): array
    {
        for ($i = 0; $i < 120; $i++) {
            sleep(5);

            $resp = CurlClient::get("{$baseUrl}/api/gemini/nano-banana/{$taskId}", [
                'Authorization' => $apiKey,
            ], 30, 10);

            if ($resp['status'] < 200 || $resp['status'] >= 300) {
                if ($resp['status'] === 404) continue;
                throw new RuntimeException("Nano-Banana 轮询失败 HTTP {$resp['status']}");
            }

            $result = $resp['json'];
            $inner = $result['data'] ?? $result;
            $status = $inner['state'] ?? $inner['status'] ?? $result['state'] ?? $result['status'] ?? '';

            if (in_array($status, ['failed', 'error'])) {
                throw new RuntimeException('Nano-Banana 任务失败: ' . ($inner['msg'] ?? $inner['message'] ?? $result['message'] ?? ''));
            }

            if (in_array($status, ['succeeded', 'completed', 'success'])) {
                $images = $inner['data']['images'] ?? [];
                if (!empty($images)) {
                    return ['data' => array_map(fn($img) => ['url' => $img['url'] ?? ''], $images)];
                }
                if (!empty($inner['url'])) {
                    return ['data' => [['url' => $inner['url']]]];
                }
                if (!empty($inner['image_url'])) {
                    return ['data' => [['url' => $inner['image_url']]]];
                }
                if (!empty($result['url'])) {
                    return ['data' => [['url' => $result['url']]]];
                }
                throw new RuntimeException('Nano-Banana 任务完成但无法解析图片: ' . json_encode($result, JSON_UNESCAPED_UNICODE));
            }
        }

        throw new RuntimeException('Nano-Banana 异步任务超时：轮询 10 分钟未获得结果');
    }

    protected function mapAspectRatio(?string $size): string
    {
        if (!$size || $size === 'auto') {
            return 'auto';
        }

        $validRatios = ['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9'];
        if (in_array($size, $validRatios)) {
            return $size;
        }

        $map = [
            '1024x1024' => '1:1',
            '1024x768' => '4:3',
            '768x1024' => '3:4',
            '1536x864' => '16:9',
            '864x1536' => '9:16',
            '1536x1024' => '3:2',
            '1024x1536' => '2:3',
            '1280x1024' => '5:4',
            '1024x1280' => '4:5',
        ];

        return $map[$size] ?? 'auto';
    }

    protected function getImageSize(GenerationTask $task): ?string
    {
        $supportedModels = ['gemini-3-pro-image-preview', 'nano-banana-pro', 'gemini-3.1-flash-image-preview', 'nano-banana-2'];
        if (!in_array($task->model, $supportedModels)) {
            return null;
        }

        return match ($task->quality) {
            'high' => '4K',
            'medium' => '2K',
            'low' => '1K',
            default => null,
        };
    }

    protected function resolveLocalUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (!in_array($host, ['127.0.0.1', 'localhost', '0.0.0.0'], true)) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if (!preg_match('#^/storage/(.+)$#', $path, $m)) {
            return $url;
        }

        $base = realpath(storage_path('app/public'));
        $filePath = realpath(storage_path('app/public/' . $m[1]));
        if (!$filePath || !str_starts_with($filePath, $base) || !is_file($filePath)) {
            return $url;
        }

        $binary = file_get_contents($filePath);
        $mime = mime_content_type($filePath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }
}
