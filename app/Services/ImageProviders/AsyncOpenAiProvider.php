<?php

namespace App\Services\ImageProviders;

use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Models\ImageAsyncJob;
use App\Services\CurlClient;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * 异步 OpenAI 兼容 Provider（代号 async-oo）
 *
 * 与 OpenAiProvider 的区别：
 *   - 请求带 async=true + callback_url
 *   - 不轮询，发完就返回 ['_deferred' => true, ...]
 *   - 由 TaskWorker 捕获 _deferred → 释放 channel slot、任务保持 processing
 *   - 上游完成 → POST /api/channels/async-oo/callback/{token} → AsyncCallbackController 入库
 *
 * 上游响应预期：
 *   { "id": "task_xxx" }    // 仅返回任务 ID
 *   或 { "data": [...] }    // 上游同步完成的兼容场景（极少数）
 *
 * 注意：本 provider 假定上游遵循 Codex 平台 /v1/images/generations 与
 *      /v1/images/edits 的协议（async + callback_url 参数）。
 */
class AsyncOpenAiProvider implements ImageProviderInterface
{
    public function generate(GenerationTask $task, AiChannel $channel): array
    {
        $size = $this->normalizeSize($task->size ?: 'auto');
        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);

        $imageUrls = [];
        if ($task->mode === 'image' && !empty($task->files)) {
            $imageUrls = array_values(array_filter(array_column($task->files, 'url')));
        }
        $hasImages = !empty($imageUrls);

        // 1. 注册 callback 映射（先入库再发请求，避免回调比响应快导致丢任务）
        $index = $this->resolveIndex($task);
        $job = $this->registerJob($task, $index, $channel);

        $callbackUrl = $this->buildCallbackUrl($job->callback_token);

        // 2. 构造 body
        $body = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'size' => $size,
            'quality' => $task->quality ?: 'auto',
            'response_format' => 'url',
            'async' => true,
            'callback_url' => $callbackUrl,
        ];

        $endpoint = $hasImages
            ? "{$baseUrl}/v1/images/edits"
            : "{$baseUrl}/v1/images/generations";

        if ($hasImages) {
            // /v1/images/edits 协议：images: [{image_url: "<url|base64>"}, ...]
            $body['images'] = array_map(fn ($u) => ['image_url' => $u], $imageUrls);
        }

        // 3. 发送
        $resp = CurlClient::post($endpoint, $body, [
            'Authorization' => "Bearer {$channel->api_key}",
            'Content-Type' => 'application/json',
        ], 60, 15);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            // 失败：清掉占位映射，让 worker 走重试逻辑
            $job->delete();
            throw new RuntimeException("上游返回 {$resp['status']}: " . mb_substr($resp['body'], 0, 300));
        }

        $json = $resp['json'] ?? [];

        // 4a. 极少数情况下上游同步返回了结果（兼容处理）
        if (!empty($json['data']) && is_array($json['data'])) {
            $job->update([
                'upstream_id' => $json['id'] ?? null,
                'status' => 'completed',
                'payload' => $json,
                'completed_at' => now(),
            ]);
            return $json;
        }

        // 4b. 异步：记录 upstream_id，挂起等回调
        $upstreamId = $json['id'] ?? $json['task_id'] ?? null;
        $job->update(['upstream_id' => $upstreamId]);

        return [
            '_deferred' => true,
            'upstream_id' => $upstreamId,
            'callback_token' => $job->callback_token,
        ];
    }

    protected function resolveIndex(GenerationTask $task): int
    {
        // worker 在 processTask 里把 $index 通过 task->touch() 流转，
        // 但 generate() 拿不到。我们用 items 数组里第一个 null 占位定位当前 index。
        // 多 worker 并发时不会冲突，因为每张图都是独立的 Redis 消息推送、独立 processTask 调用，
        // 而 worker 在调 generate() 前已经知道 index — 见下方 AsyncOpenAiProvider::generateAt。
        $items = $task->items ?? [];
        foreach ($items as $i => $v) {
            if ($v === null) return $i;
        }
        return 0;
    }

    /**
     * 显式 index 版本，由 TaskWorker 调用以避免并发歧义
     */
    public function generateAt(GenerationTask $task, AiChannel $channel, int $index): array
    {
        $size = $this->normalizeSize($task->size ?: 'auto');
        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);

        $imageUrls = [];
        if ($task->mode === 'image' && !empty($task->files)) {
            $imageUrls = array_values(array_filter(array_column($task->files, 'url')));
        }
        $hasImages = !empty($imageUrls);

        $job = $this->registerJob($task, $index, $channel);
        $callbackUrl = $this->buildCallbackUrl($job->callback_token);

        $body = [
            'model' => $task->model,
            'prompt' => $task->prompt,
            'size' => $size,
            'quality' => $task->quality ?: 'auto',
            'response_format' => 'url',
            'async' => true,
            'callback_url' => $callbackUrl,
        ];
        $endpoint = $hasImages
            ? "{$baseUrl}/v1/images/edits"
            : "{$baseUrl}/v1/images/generations";
        if ($hasImages) {
            $body['images'] = array_map(fn ($u) => ['image_url' => $u], $imageUrls);
        }

        $resp = CurlClient::post($endpoint, $body, [
            'Authorization' => "Bearer {$channel->api_key}",
            'Content-Type' => 'application/json',
        ], 60, 15);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            $job->delete();
            throw new RuntimeException("上游返回 {$resp['status']}: " . mb_substr($resp['body'], 0, 300));
        }

        $json = $resp['json'] ?? [];

        if (!empty($json['data']) && is_array($json['data'])) {
            $job->update([
                'upstream_id' => $json['id'] ?? null,
                'status' => 'completed',
                'payload' => $json,
                'completed_at' => now(),
            ]);
            return $json;
        }

        $upstreamId = $json['id'] ?? $json['task_id'] ?? null;
        $job->update(['upstream_id' => $upstreamId]);

        return [
            '_deferred' => true,
            'upstream_id' => $upstreamId,
            'callback_token' => $job->callback_token,
        ];
    }

    protected function registerJob(GenerationTask $task, int $index, AiChannel $channel): ImageAsyncJob
    {
        // 幂等：同 task_id+index 已存在则复用（重试场景）
        return ImageAsyncJob::updateOrCreate(
            ['task_id' => $task->task_id, 'index' => $index],
            [
                'callback_token' => bin2hex(random_bytes(32)),
                'channel_id' => $channel->id,
                'status' => 'pending',
                'expires_at' => now()->addMinutes((int) config('async_oo.timeout_minutes', 30)),
            ]
        );
    }

    protected function buildCallbackUrl(string $token): string
    {
        $base = rtrim((string) (config('async_oo.callback_base_url') ?: config('app.url')), '/');
        return $base . '/api/channels/async-oo/callback/' . $token;
    }

    protected function normalizeSize(string $size): string
    {
        // 兼容 1K/2K/4K 命名，或 "1024x1024" 这种字面值。
        // 上游协议接受按比例的字符串；这里直通即可。
        return $size ?: 'auto';
    }
}
