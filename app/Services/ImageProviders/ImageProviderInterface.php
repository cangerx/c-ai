<?php

namespace App\Services\ImageProviders;

use App\Models\AiChannel;
use App\Models\GenerationTask;

interface ImageProviderInterface
{
    /**
     * 生成图片，返回统一格式：
     * ['data' => [['url' => '...'] or ['b64_json' => '...'], ...]]
     */
    public function generate(GenerationTask $task, AiChannel $channel): array;
}
