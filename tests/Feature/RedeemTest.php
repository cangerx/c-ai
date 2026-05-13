<?php

namespace Tests\Feature;

use App\Models\RedeemCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 兑换码功能测试
class RedeemTest extends TestCase
{
    use RefreshDatabase;

    // 用户可以兑换有效的兑换码
    public function test_user_can_redeem_valid_code(): void
    {
        $user = User::factory()->create(['credits' => 0, 'balance' => 0]);
        $code = RedeemCode::factory()->create(['credits' => 10, 'balance' => 5.00]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/redeem', ['code' => $code->code]);

        $response->assertOk()
            ->assertJsonPath('added_credits', 10)
            ->assertJsonPath('added_balance', '5.00');

        $user->refresh();
        $this->assertEquals(10, $user->credits);
        $this->assertEquals(5.00, (float) $user->balance);

        $code->refresh();
        $this->assertEquals('used', $code->status);
        $this->assertEquals($user->id, $code->used_by);
    }

    // 无效兑换码被拒绝
    public function test_invalid_code_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/redeem', ['code' => str_repeat('X', 32)]);

        $response->assertStatus(422);
    }

    // 已使用的兑换码不能再次兑换
    public function test_used_code_rejected(): void
    {
        $user = User::factory()->create();
        $code = RedeemCode::factory()->used()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/redeem', ['code' => $code->code]);

        $response->assertStatus(422);
    }

    // 已禁用的兑换码不能兑换
    public function test_disabled_code_rejected(): void
    {
        $user = User::factory()->create();
        $code = RedeemCode::factory()->disabled()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/redeem', ['code' => $code->code]);

        $response->assertStatus(422);
    }
}
