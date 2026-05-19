<?php

namespace App\Services\ImageProviders;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use Illuminate\Support\Facades\Http;
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

        // 返回的是异步任务，需要轮询
        $taskId = $json['id'] ?? $json['task_id'] ?? null;
        if ($taskId) {
            return $this->pollResult($baseUrl, $channel->api_key, $taskId);
        }

        // 如果直接返回了图片（同步响应）
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
        ];

        $imageSize = $this->getImageSize($task);
        if ($imageSize) {
            $body['image_size'] = $imageSize;
        }

        $response = Http::timeout(300)
            ->connectTimeout(15)
            ->withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/api/gemini/nano-banana", $body);

        if (!$response->successful()) {
            throw new RuntimeException("上游返回 {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        return $response->json() ?? [];
    }

    protected function editImage(string $baseUrl, string $apiKey, GenerationTask $task, array $imageUrls): array
    {
        $body = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'image_urls' => array_slice($imageUrls, 0, 10),
        ];

        // 图生图时只在多图或明确指定时传 aspect_ratio
        if (count($imageUrls) > 1 || $task->size !== 'auto') {
            $body['aspect_ratio'] = $this->mapAspectRatio($task->size);
        }

        $imageSize = $this->getImageSize($task);
        if ($imageSize) {
            $body['image_size'] = $imageSize;
        }

        $response = Http::timeout(300)
            ->connectTimeout(15)
            ->withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("{$baseUrl}/api/gemini/nano-banana-edit", $body);

        if (!$response->successful()) {
            throw new RuntimeException("上游返回 {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        return $response->json() ?? [];
    }

    protected function pollResult(string $baseUrl, string $apiKey, string $taskId): array
    {
        for ($i = 0; $i < 120; $i++) {
            sleep(5);

            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withHeaders(['Authorization' => $apiKey])
                ->get("{$baseUrl}/api/gemini/nano-banana/{$taskId}");

            if (!$response->successful()) {
                if ($response->status() === 404) continue;
                throw new RuntimeException("Nano-Banana 轮询失败 HTTP {$response->status()}");
            }

            $result = $response->json() ?? [];
            $status = $result['status'] ?? $result['state'] ?? '';

            if (in_array($status, ['failed', 'error'])) {
                throw new RuntimeException('Nano-Banana 任务失败: ' . ($result['message'] ?? $result['error'] ?? ''));
            }

            if (in_array($status, ['succeeded', 'completed', 'success'])) {
                // 尝试多种返回格式
                if (!empty($result['data'])) {
                    $items = is_array($result['data']) ? $result['data'] : [];
                    if (!empty($items) && isset($items[0]['url'])) {
                        return ['data' => $items];
                    }
                }
                if (!empty($result['url'])) {
                    return ['data' => [['url' => $result['url']]]];
                }
                if (!empty($result['image_url'])) {
                    return ['data' => [['url' => $result['image_url']]]];
                }
                if (!empty($result['output'])) {
                    $output = $result['output'];
                    if (is_string($output)) {
                        return ['data' => [['url' => $output]]];
                    }
                    if (is_array($output) && !empty($output[0])) {
                        return ['data' => array_map(fn($u) => ['url' => is_string($u) ? $u : ($u['url'] ?? '')], $output)];
                    }
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

        // 如果已经是比例格式，直接用
        $validRatios = ['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9'];
        if (in_array($size, $validRatios)) {
            return $size;
        }

        // 像素格式转比例
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

    /**
     * 根据 quality 映射 image_size（仅部分模型支持）
     */
    protected function getImageSize(GenerationTask $task): ?string
    {
        // nano-banana-pro 和 nano-banana-2 支持 image_size
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
}
