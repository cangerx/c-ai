<?php

namespace Tests\Feature;

use App\Models\AgentSite;
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

        $agent = User::factory()->agent()->create(['credits' => 100]);
        $distributor = User::factory()->create([
            'parent_id' => $agent->id,
            'is_distributor' => true,
            'credits' => 100,
            'commission_credits' => 0,
        ]);
        AgentSite::create([
            'user_id' => $agent->id,
            'slug' => 'agent-a',
            'subdomain' => 'agent-a',
            'site_name' => 'Agent A',
            'commission_rate' => 10,
            'is_active' => true,
            'status' => 'approved',
        ]);
        $subUser = User::factory()->create([
            'parent_id' => $distributor->id,
            'credits' => 10,
        ]);

        $billing = app(BillingService::class);
        $billing->charge($subUser, 'medium', ['app_name' => 'image-gen']);

        $distributor->refresh();
        $this->assertEquals(100, $distributor->credits);
        $this->assertEquals(1, $distributor->commission_credits);
        $this->assertEquals(99, $agent->fresh()->credits);
        $this->assertEquals(0, $subUser->fresh()->credits);
        $this->assertEquals(10, $subUser->fresh()->total_consumed_credits);
        $this->assertDatabaseHas('commission_logs', [
            'user_id' => $distributor->id,
            'agent_id' => $agent->id,
            'from_user_id' => $subUser->id,
            'credits' => 1,
        ]);
    }
}
