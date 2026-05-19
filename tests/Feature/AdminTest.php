<?php

namespace Tests\Feature;

use App\Filament\Agent\Resources\SubUserResource;
use App\Filament\Agent\Resources\WithdrawalResource as AgentWithdrawalResource;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 管理后台功能测试
class AdminTest extends TestCase
{
    use RefreshDatabase;

    // 普通用户不能访问管理后台
    public function test_non_admin_cannot_access_admin_area(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(403);
    }

    // 管理员可以访问后台首页
    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
    }

    // 代理商只能看到自己的子用户
    public function test_agent_only_sees_own_sub_users(): void
    {
        $agent1 = User::factory()->agent()->create();
        $agent2 = User::factory()->agent()->create();

        $sub1 = User::factory()->create(['parent_id' => $agent1->id]);
        $sub2 = User::factory()->create(['parent_id' => $agent2->id]);

        $this->actingAs($agent1);
        $emails = SubUserResource::getEloquentQuery()->pluck('email')->all();

        $this->assertContains($sub1->email, $emails);
        $this->assertNotContains($sub2->email, $emails);
    }

    // 分销员可以通过 API 申请提现
    public function test_distributor_can_apply_for_withdrawal(): void
    {
        $agent = User::factory()->agent()->create();
        $distributor = User::factory()->create([
            'parent_id' => $agent->id,
            'is_distributor' => true,
            'commission_credits' => 100,
        ]);

        $response = $this->actingAs($distributor, 'sanctum')->postJson('/api/withdrawals', [
            'amount' => 50,
            'payment_method' => 'alipay',
            'payment_account' => 'test@alipay.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', '提现申请已提交');
        $this->assertDatabaseHas('withdrawal_requests', [
            'user_id' => $distributor->id,
            'agent_id' => $agent->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);
        $this->assertEquals(50, $distributor->fresh()->commission_credits);
    }

    // 代理商只能看到归属自己的提现申请
    public function test_agent_only_sees_own_withdrawal_requests(): void
    {
        $agent1 = User::factory()->agent()->create();
        $agent2 = User::factory()->agent()->create();
        $user1 = User::factory()->create(['parent_id' => $agent1->id, 'is_distributor' => true]);
        $user2 = User::factory()->create(['parent_id' => $agent2->id, 'is_distributor' => true]);

        $own = WithdrawalRequest::create([
            'user_id' => $user1->id,
            'agent_id' => $agent1->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_method' => 'alipay',
            'payment_account' => 'test@alipay.com',
        ]);
        $other = WithdrawalRequest::create([
            'user_id' => $user2->id,
            'agent_id' => $agent2->id,
            'amount' => 60.00,
            'status' => 'pending',
            'payment_method' => 'wechat',
            'payment_account' => 'other@wechat.com',
        ]);

        $this->actingAs($agent1);
        $ids = AgentWithdrawalResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }
}
