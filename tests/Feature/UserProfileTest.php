<?php

namespace Tests\Feature;

use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 用户资料和数据管理功能测试
class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    // 用户可以通过 API 更新昵称
    public function test_user_can_update_nickname_via_api(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/me', ['nickname' => '新昵称']);

        $response->assertOk();
        $this->assertEquals('新昵称', $user->fresh()->nickname);
    }

    // 用户可以通过 API 更新密码
    public function test_user_can_update_password_via_api(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpass123')]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/me', [
                'password' => 'newpass123',
                'current_password' => 'oldpass123',
            ]);

        $response->assertOk();
    }

    // 没有当前密码时无法更新密码
    public function test_user_cannot_update_without_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpass123')]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/me', [
                'password' => 'newpass123',
            ]);

        $response->assertStatus(422);
    }

    // 用户可以查看使用记录
    public function test_user_can_view_usage_history(): void
    {
        $user = User::factory()->create();
        UsageLog::create([
            'user_id' => $user->id,
            'app_name' => 'image-gen',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/usage');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // 用户可以查看自己的任务
    public function test_user_can_view_own_tasks(): void
    {
        $user = User::factory()->create();
        GenerationTask::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // 用户可以删除自己的任务
    public function test_user_can_delete_own_task(): void
    {
        $user = User::factory()->create();
        $task = GenerationTask::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->task_id}");

        $response->assertOk();
        $this->assertDatabaseMissing('generation_tasks', ['task_id' => $task->task_id]);
    }

    // 用户不能删除别人的任务
    public function test_user_cannot_delete_others_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = GenerationTask::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->task_id}");

        $response->assertStatus(404);
    }
}
