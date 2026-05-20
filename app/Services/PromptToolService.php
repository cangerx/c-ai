<?php

namespace App\Services;

use App\Models\AiChannel;
use App\Models\SiteSetting;
use RuntimeException;
use Throwable;

class PromptToolService
{
    public function run(string $kind, string $prompt): array
    {
        $models = $this->candidateModels();
        $dispatcher = app(ChannelDispatcher::class);
        $lastException = null;

        foreach ($models as $model) {
            $lastExclude = null;

            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $released = false;
                $channel = $dispatcher->acquire('image-gen', $lastExclude, null, $model)
                    ?? $dispatcher->acquire('image-gen', null, null, $model)
                    ?? $dispatcher->acquireFallback('image-gen', $lastExclude, $model)
                    ?? $dispatcher->acquireFallback('image-gen', null, $model);

                if (!$channel) {
                    $lastException = new RuntimeException("模型 {$model} 无可用提示词工具渠道");
                    break;
                }

                try {
                    $text = $this->callChannel($channel, $model, $kind, $prompt);
                    $dispatcher->release($channel->id);
                    $released = true;

                    return [
                        'prompt' => $text,
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

                    if ($attempt < 3) sleep(2 * $attempt);
                }
            }
        }

        throw $lastException ?: new RuntimeException('提示词工具执行失败');
    }

    protected function candidateModels(): array
    {
        $preferred = array_values(array_unique(array_filter([
            (string) SiteSetting::get('prompt_tool_model', ''),
            'gpt-5.4-mini',
            'gpt-5.4',
            'gpt-5.5',
        ])));

        $configured = AiChannel::where('app_name', 'image-gen')
            ->where('is_active', true)
            ->whereIn('status', ['active', 'paused'])
            ->get()
            ->flatMap(function (AiChannel $channel) {
                return array_values(array_filter(array_merge($channel->models ?? [], [$channel->model])));
            })
            ->filter(fn($model) => is_string($model)
                && !preg_match('/image/i', $model)
                && preg_match('/^(gpt|chatgpt|claude|gemini|qwen|deepseek|glm|kimi|doubao|yi|llama)/i', $model))
            ->unique()
            ->values()
            ->all();

        usort($configured, function (string $a, string $b) use ($preferred) {
            $ai = array_search($a, $preferred, true);
            $bi = array_search($b, $preferred, true);
            $ai = $ai === false ? PHP_INT_MAX : $ai;
            $bi = $bi === false ? PHP_INT_MAX : $bi;
            return $ai <=> $bi ?: strcmp($a, $b);
        });

        return array_values(array_unique(array_merge($preferred, $configured)));
    }

    protected function callChannel(AiChannel $channel, string $model, string $kind, string $prompt): string
    {
        $baseUrl = rtrim($channel->base_url, '/');
        $baseUrl = preg_replace('#/v1$#', '', $baseUrl);

        $systemPrompt = $kind === 'optimize'
            ? '你是专业的图像提示词优化助手。保留用户原意，补充构图、主体、光线、材质、镜头、风格、细节，但不要胡乱扩写。直接返回优化后的最终提示词，不要加解释，不要加引号。'
            : '你是专业翻译助手。识别用户输入语言，在中文和英文之间做适合图像生成的自然翻译。只返回翻译后的最终提示词，不要解释，不要加引号。';

        $userPrompt = $kind === 'optimize'
            ? "请优化下面这段用于图像生成的提示词：\n{$prompt}"
            : "请翻译下面这段用于图像生成的提示词，中文转英文，英文转中文：\n{$prompt}";

        $resp = CurlClient::post($baseUrl . '/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
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
}
