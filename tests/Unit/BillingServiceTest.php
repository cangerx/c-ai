<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = new BillingService();
    }

    public function test_can_afford_returns_false_when_no_credits(): void
    {
        $user = User::factory()->create(['credits' => 0]);
        $this->assertFalse($this->billing->canAfford($user));
    }

    public function test_can_afford_returns_true_when_has_credits(): void
    {
        $user = User::factory()->create(['credits' => 5]);
        $this->assertTrue($this->billing->canAfford($user));
    }

    public function test_charge_deducts_fixed_credits(): void
    {
        SiteSetting::set('billing_per_generation', 2);
        $user = User::factory()->create(['credits' => 10]);

        $log = $this->billing->charge($user, 'medium', ['app_name' => 'image-gen']);

        $this->assertEquals(2, $log->cost_credits);
        $this->assertEquals(8, $user->fresh()->credits);
        $this->assertEquals(2, $user->fresh()->total_consumed_credits);
    }

    public function test_charge_throws_when_insufficient(): void
    {
        $user = User::factory()->create(['credits' => 0]);
        $this->expectException(RuntimeException::class);
        $this->billing->charge($user, 'medium', ['app_name' => 'image-gen']);
    }
}
