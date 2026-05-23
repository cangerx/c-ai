<?php

namespace Tests\Unit;

use App\Models\AiChannel;
use App\Services\ChannelDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_image_model_only_acquires_nano_banana_provider(): void
    {
        $openAiChannel = $this->createChannel([
            'name' => 'openai-compatible',
            'provider' => 'openai',
            'model' => 'gemini-3-pro-image-preview',
            'models' => ['gemini-3-pro-image-preview'],
            'priority' => 100,
        ]);

        $nanoBananaChannel = $this->createChannel([
            'name' => 'nano-banana',
            'provider' => 'nano-banana',
            'model' => 'gemini-3-pro-image-preview',
            'models' => ['gemini-3-pro-image-preview'],
            'priority' => 1,
        ]);

        $channel = (new ChannelDispatcher())->acquire('image-gen', model: 'gemini-3-pro-image-preview');

        $this->assertNotNull($channel);
        $this->assertSame($nanoBananaChannel->id, $channel->id);
        $this->assertSame(0, $openAiChannel->fresh()->current_load);
        $this->assertSame(1, $nanoBananaChannel->fresh()->current_load);
    }

    public function test_nano_banana_model_fallback_skips_openai_compatible_provider(): void
    {
        $openAiChannel = $this->createChannel([
            'name' => 'openai-compatible',
            'provider' => 'openai',
            'model' => 'nano-banana-pro',
            'models' => ['nano-banana-pro'],
            'status' => 'paused',
        ]);

        $nanoBananaChannel = $this->createChannel([
            'name' => 'nano-banana',
            'provider' => 'nano-banana',
            'model' => 'nano-banana-pro',
            'models' => ['nano-banana-pro'],
            'status' => 'paused',
        ]);

        $channel = (new ChannelDispatcher())->acquireFallback('image-gen', model: 'nano-banana-pro');

        $this->assertNotNull($channel);
        $this->assertSame($nanoBananaChannel->id, $channel->id);
        $this->assertSame(0, $openAiChannel->fresh()->current_load);
        $this->assertSame(1, $nanoBananaChannel->fresh()->current_load);
    }

    public function test_openai_image_model_can_still_acquire_openai_provider(): void
    {
        $openAiChannel = $this->createChannel([
            'name' => 'openai-compatible',
            'provider' => 'openai',
            'model' => 'gpt-image-2',
            'models' => ['gpt-image-2'],
        ]);

        $channel = (new ChannelDispatcher())->acquire('image-gen', model: 'gpt-image-2');

        $this->assertNotNull($channel);
        $this->assertSame($openAiChannel->id, $channel->id);
    }

    private function createChannel(array $attributes = []): AiChannel
    {
        return AiChannel::create(array_merge([
            'name' => 'test-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.example.test',
            'api_key' => 'sk-test',
            'model' => 'gpt-image-2',
            'models' => ['gpt-image-2'],
            'priority' => 1,
            'request_mode' => 'sync',
            'is_active' => true,
            'status' => 'active',
            'rate_limit' => 60,
            'app_name' => 'image-gen',
            'current_load' => 0,
            'error_count' => 0,
            'max_errors' => 5,
        ], $attributes));
    }
}
