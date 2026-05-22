<?php

namespace App\Services;

use App\Models\AiChannel;
use App\Models\SiteSetting;
use RuntimeException;
use Throwable;

class ReversePromptService
{
    private const DEFAULT_INSTRUCTION = '以json格式描述这幅图，中文描述准确复制原始图图像所需的所有方面.光线、风格、身体姿势以及任何其他相关元素的具体信息，确保能够包括有关物品、服装、发型、复杂细节、配饰、摄影器材、环境、精确地重现原始图像的每一个细节。';

    public function analyze(string $imageUrl, ?string $userPrompt = null): array
    {
        $models = $this->candidateModels();
        $dispatcher = app(ChannelDispatcher::class);
        $lastException = null;

        foreach ($models as $model) {
            $lastExclude = null;

            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $released = false;
                // 不按模型过滤渠道，任何有 base_url + api_key 的渠道都能调 chat/completions
                $channel = $dispatcher->acquire('image-gen', $lastExclude)
                    ?? $dispatcher->acquire('image-gen')
                    ?? $dispatcher->acquireFallback('image-gen', $lastExclude)
                    ?? $dispatcher->acquireFallback('image-gen');

                if (!$channel) {
                    $lastException = new RuntimeException("模型 {$model} 无可用反推渠道");
                    break;
                }

                try {
                    $result = $this->callChannel($channel, $model, $imageUrl, $userPrompt);
                    $dispatcher->release($channel->id);
                    $released = true;

                    return [
                        'prompt' => $result,
                        'model' => $model,
                        'channel_id' => $channel->id,
                        'channel_name' => $channel->display_name ?: $channel->name,
                    ];
                } catch (Throwable $e) {
                    if (!$released) {
                        $dispatcher->reportError($channel->id);
                    }
                    if ($this->isAuthError($e)) {
                        AiChannel::where('id', $channel->id)->update([
                            'status' => 'error',
                            'paused_at' => now(),
                        ]);
                    }
                    $lastExclude = $channel->id;
                    $lastException = $e;

                    if ($this->isNonRetryableError($e)) {
                        break;
                    }

                    if ($attempt < 3) sleep(2 * $attempt);
                }
            }
        }

        throw $lastException ?: new RuntimeException('反推提示词失败');
    }

    protected function candidateModels(): array
    {
        $configured = (string) SiteSetting::get('reverse_prompt_model', '');
        $fallback = (string) SiteSetting::get('prompt_tool_model', '');

        return array_values(array_unique(array_filter([
            $configured,
            $fallback,
            'gpt-4o-mini',
            'gpt-4o',
        ])));
    }

    protected function callChannel(AiChannel $channel, string $model, string $imageUrl, ?string $userPrompt): string
    {
        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);
        $instruction = trim((string) $userPrompt) ?: self::DEFAULT_INSTRUCTION;

        $resp = CurlClient::post($baseUrl . '/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是专业的图像反推助手。严格根据图片内容输出中文结果；如果用户提供了额外要求，必须按用户要求组织输出。不要编造图片中不存在的元素。',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $instruction],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                    ],
                ],
            ],
        ], [
            'Authorization' => "Bearer {$channel->api_key}",
            'Content-Type' => 'application/json',
        ], 120, 15);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new RuntimeException("上游返回 {$resp['status']}: " . mb_substr($resp['body'], 0, 500));
        }

        $content = $resp['json']['choices'][0]['message']['content']
            ?? $resp['json']['choices'][0]['text']
            ?? '';

        $content = trim(is_string($content) ? $content : '');
        if ($content === '') {
            throw new RuntimeException('模型未返回可用提示词');
        }

        return $content;
    }

    protected function isAuthError(Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '上游返回 401')
            || str_contains($msg, '上游返回 403')
            || str_contains($msg, 'Invalid token');
    }

    protected function isNonRetryableError(Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '余额不足')
            || str_contains($msg, 'insufficient')
            || str_contains($msg, 'model_not_found')
            || str_contains($msg, 'Invalid model')
            || str_contains($msg, 'images endpoint')
            || str_contains($msg, 'requires an image model');
    }
}
