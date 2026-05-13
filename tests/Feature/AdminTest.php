<?php

namespace Tests\Feature;

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

        $response = $this->actingAs($agent1)->get('/admin/users');

        $response->assertOk();
        $response->assertSee($sub1->email);
        $response->assertDontSee($sub2->email);
    }

    // 代理商可以申请提现
    public function test_agent_can_apply_for_withdrawal(): void
    {
        $agent = User::factory()->agent()->create(['commission_balance' => 100.00]);

        $response = $this->actingAs($agent)->post('/admin/withdrawals', [
            'amount' => 50,
            'payment_method' => 'alipay',
            'payment_account' => 'test@alipay.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('withdrawal_requests', [
            'user_id' => $agent->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);
    }

    // 管理员可以批准提现
    public function test_admin_can_approve_withdrawal(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create(['commission_balance' => 0]);

        $withdrawal = WithdrawalRequest::create([
            'user_id' => $agent->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_method' => 'alipay',
            'payment_account' => 'test@alipay.com',
        ]);

        $response = $this->actingAs($admin)
            ->post("/admin/withdrawals/{$withdrawal->id}/approve");

        $response->assertRedirect();
        $this->assertEquals('paid', $withdrawal->fresh()->status);
    }
}
