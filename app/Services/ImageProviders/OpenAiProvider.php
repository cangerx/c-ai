<?php

namespace App\Services\ImageProviders;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use Illuminate\Support\Facades\Http;
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

        $response = Http::timeout(300)
            ->connectTimeout(15)
            ->withHeaders([
                'Authorization' => "Bearer {$channel->api_key}",
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $body);

        if (!$response->successful()) {
            throw new RuntimeException("上游返回 {$response->status()}: " . mb_substr($response->body(), 0, 300));
        }

        $json = $response->json() ?? [];

        if ($requestMode === 'async') {
            $json = $this->pollAsyncResult($channel, $json['id'] ?? '');
        }

        return $json;
    }

    protected function pollAsyncResult(AiChannel $channel, string $asyncId): array
    {
        if (empty($asyncId)) {
            throw new RuntimeException('异步接口未返回任务 ID');
        }

        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);
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

    protected function normalizeSize(string $size): string
    {
        $map = [
            '1:1' => '1024x1024',
            '4:3' => '1024x768',
            '3:4' => '768x1024',
            '16:9' => '1536x864',
            '9:16' => '864x1536',
            '3:2' => '1536x1024',
            '2:3' => '1024x1536',
            '5:4' => '1280x1024',
            '4:5' => '1024x1280',
        ];
        return $map[$size] ?? $size;
    }
}
