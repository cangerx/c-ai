<?php

namespace Tests\Unit;

use App\Models\BillingRule;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

// BillingService 单元测试
class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = new BillingService();
    }

    // 没有余额和 credits 时 canAfford 返回 false
    public function test_can_afford_returns_false_when_no_balance_and_no_credits(): void
    {
        $user = User::factory()->create(['credits' => 0, 'balance' => 0]);

        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => '*',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 0.10,
        ]);

        $this->assertFalse($this->billing->canAfford($user, 'medium'));
    }

    // 有 credits 时 canAfford 返回 true
    public function test_can_afford_returns_true_when_has_credits(): void
    {
        $user = User::factory()->create(['credits' => 5, 'balance' => 0]);

        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => '*',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 0.10,
        ]);

        $this->assertTrue($this->billing->canAfford($user, 'medium'));
    }

    // 既有 credits 也有 balance 时优先扣 credits
    public function test_charge_deducts_credits_first(): void
    {
        $user = User::factory()->create(['credits' => 5, 'balance' => 10.00]);

        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => '*',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 0.10,
        ]);

        $log = $this->billing->charge($user, 'medium', ['app_name' => 'image-gen']);

        $this->assertEquals(1, $log->cost_credits);
        $this->assertEquals(0, (float) $log->cost_balance);
        $this->assertEquals(4, $user->fresh()->credits);
        $this->assertEquals(10.00, (float) $user->fresh()->balance);
    }

    // 没有 credits 时扣 balance
    public function test_charge_falls_back_to_balance_when_no_credits(): void
    {
        $user = User::factory()->create(['credits' => 0, 'balance' => 10.00]);

        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => '*',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 0.10,
        ]);

        $log = $this->billing->charge($user, 'medium', ['app_name' => 'image-gen']);

        $this->assertEquals(0, $log->cost_credits);
        $this->assertEquals(0.10, (float) $log->cost_balance);
        $this->assertEquals(9.90, (float) $user->fresh()->balance);
    }

    // 余额不足时抛出异常
    public function test_charge_throws_when_insufficient(): void
    {
        $user = User::factory()->create(['credits' => 0, 'balance' => 0]);

        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => '*',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 0.10,
        ]);

        $this->expectException(RuntimeException::class);
        $this->billing->charge($user, 'medium', ['app_name' => 'image-gen']);
    }
}
