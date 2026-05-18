<?php

namespace App\Services;

use App\Models\AiChannel;
use App\Models\SiteSetting;

class PromptAnalysisService
{
    public function extractVariables(string $prompt, ?int $channelId = null, ?string $model = null): array
    {
        $model = $model ?: SiteSetting::get('prompt_tool_model') ?: null;

        if ($channelId) {
            $channel = AiChannel::find($channelId);
        } elseif ($model) {
            // 根据模型名找到包含它的渠道
            $channel = AiChannel::where('is_active', true)
                ->where('models', 'like', '%"' . $model . '"%')
                ->orderByDesc('priority')
                ->first();
        }

        if (!isset($channel) || !$channel) {
            $channel = AiChannel::where('is_active', true)
                ->orderByDesc('priority')
                ->first();
        }

        if (!$channel) {
            throw new \RuntimeException('没有可用的 AI 渠道');
        }

        if (!$model || (!empty($channel->models) && !in_array($model, $channel->models))) {
            $model = $channel->model;
        }

        $systemPrompt = <<<'SYSTEM'
你是一个 prompt 模板分析专家。用户会给你一段图片生成的 prompt，你需要：
1. 为这个 prompt 生成一个简短的中文标题（不超过20字）
2. 推荐 1-3 个分类标签（如：人像、风景、插画、3D、动漫、产品、建筑等）
3. 识别其中可以被用户自定义替换的关键变量部分

变量提取规则：
- 识别 3-8 个最重要的可替换变量（如主题、风格、颜色、场景、材质等）
- 变量应该是用户最可能想要修改的部分
- 不要把整段描述作为一个变量，要拆分成具体的可替换片段

返回严格的 JSON 格式（不要 markdown 代码块）：
{
  "title": "简短中文标题",
  "tags": "标签1,标签2",
  "template_prompt": "原始prompt中变量部分替换为 {{variable_name}} 的版本",
  "variables": [
    {
      "name": "英文标识符_snake_case",
      "label": "中文显示名",
      "description": "简短说明这个变量控制什么",
      "default": "从原始prompt中提取的默认值",
      "alternatives": ["替代选项1", "替代选项2", "替代选项3"]
    }
  ]
}
SYSTEM;

        $body = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
        ], JSON_UNESCAPED_UNICODE);

        $baseUrl = rtrim($channel->base_url, '/');
        $url = str_ends_with($baseUrl, '/v1') ? $baseUrl . '/chat/completions' : $baseUrl . '/v1/chat/completions';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $channel->api_key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if (!$result || $status !== 200) {
            $msg = $status === 0 ? "连接超时: {$err}" : "HTTP {$status}";
            throw new \RuntimeException('AI 请求失败: ' . $msg);
        }

        $data = json_decode($result, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        $content = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($content));

        $parsed = json_decode($content, true);
        if (!$parsed || !isset($parsed['variables'])) {
            throw new \RuntimeException('AI 返回格式异常');
        }

        return $parsed;
    }
}
