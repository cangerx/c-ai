<?php

namespace Tests\Feature;

use App\Models\AiChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 图像生成功能测试
class GenerateTest extends TestCase
{
    use RefreshDatabase;

    // 余额不足的用户无法生成
    public function test_user_cannot_generate_without_balance(): void
    {
        $user = User::factory()->create(['credits' => 0, 'balance' => 0]);
        AiChannel::create([
            'name' => 'test', 'base_url' => 'https://api.test.com',
            'api_key' => 'sk-test', 'app_name' => 'image-gen',
            'status' => 'active', 'priority' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apps/image-gen/generate', [
                'prompt' => 'a cat',
                'quality' => 'medium',
            ]);

        $response->assertStatus(402);
    }

    // 有余额的用户可以提交生成任务
    public function test_user_can_submit_generation_task(): void
    {
        $user = User::factory()->create(['credits' => 10, 'balance' => 5.00]);
        AiChannel::create([
            'name' => 'test', 'base_url' => 'https://api.test.com',
            'api_key' => 'sk-test', 'app_name' => 'image-gen',
            'status' => 'active', 'priority' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/apps/image-gen/generate', [
                'prompt' => 'a beautiful sunset',
                'quality' => 'medium',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['task_id', 'status']);

        // 验证任务创建
        $this->assertDatabaseHas('generation_tasks', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // 验证 usage log 创建
        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $user->id,
            'app_name' => 'image-gen',
        ]);
    }

    // 未登录用户无法生成
    public function test_generation_requires_auth(): void
    {
        $response = $this->postJson('/api/apps/image-gen/generate', [
            'prompt' => 'a cat',
        ]);

        $response->assertStatus(401);
    }
}
