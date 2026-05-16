<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_uses_site_setting_cost(): void
    {
        SiteSetting::set('billing_per_generation', 3);

        $billing = app(BillingService::class);
        $this->assertEquals(3, $billing->getCost());
    }

    public function test_distributor_gets_commission_from_referral(): void
    {
        SiteSetting::set('billing_per_generation', 10);
        SiteSetting::set('distributor_commission_rate', 0.10);

        $distributor = User::factory()->create([
            'is_distributor' => true,
            'credits' => 100,
            'commission_credits' => 0,
        ]);
        $subUser = User::factory()->create([
            'parent_id' => $distributor->id,
            'credits' => 10,
        ]);

        $billing = app(BillingService::class);
        $billing->charge($subUser, 'medium', ['app_name' => 'image-gen']);

        $distributor->refresh();
        $this->assertEquals(101, $distributor->credits);
        $this->assertEquals(1, $distributor->commission_credits);
        $this->assertEquals(0, $subUser->fresh()->credits);
        $this->assertEquals(10, $subUser->fresh()->total_consumed_credits);
    }
}
