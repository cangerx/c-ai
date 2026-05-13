<?php

namespace Tests\Feature;

use App\Models\BillingRule;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// 计费规则功能测试
class BillingRuleTest extends TestCase
{
    use RefreshDatabase;

    // BillingService 使用匹配的规则
    public function test_billing_service_uses_matching_rule(): void
    {
        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => 'gpt-image-2',
            'quality' => 'high',
            'cost_credits' => 5,
            'cost_balance' => 0.50,
        ]);

        $billing = app(BillingService::class);
        $cost = $billing->getCost('high', 'gpt-image-2');

        $this->assertEquals(5, $cost['credits']);
        $this->assertEquals(0.50, $cost['balance']);
    }

    // BillingService 回退到通配符规则
    public function test_billing_service_falls_back_to_wildcard(): void
    {
        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => '*',
            'quality' => 'medium',
            'cost_credits' => 2,
            'cost_balance' => 0.20,
        ]);

        $billing = app(BillingService::class);
        $cost = $billing->getCost('medium', 'some-random-model');

        $this->assertEquals(2, $cost['credits']);
        $this->assertEquals(0.20, $cost['balance']);
    }

    // BillingService 回退到站点设置默认值
    public function test_billing_service_falls_back_to_site_setting(): void
    {
        SiteSetting::set('billing_low_credits', 3);
        SiteSetting::set('billing_low_balance', 0.30);

        $billing = app(BillingService::class);
        $cost = $billing->getCost('low', 'any-model');

        $this->assertEquals(3, $cost['credits']);
        $this->assertEquals(0.30, $cost['balance']);
    }

    // 代理商的下级用户消费余额时，代理商获得佣金
    public function test_user_gets_commission_from_sub_user(): void
    {
        $agent = User::factory()->agent()->create(['commission_balance' => 0]);
        $subUser = User::factory()->create([
            'parent_id' => $agent->id,
            'credits' => 0,
            'balance' => 10.00,
        ]);

        SiteSetting::set('agent_commission_rate', 0.10);

        BillingRule::create([
            'app_name' => 'image-gen',
            'model_pattern' => '*',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 1.00,
        ]);

        $billing = app(BillingService::class);
        $billing->charge($subUser, 'medium', [
            'app_name' => 'image-gen',
            'model' => 'gpt-image-2',
        ]);

        $agent->refresh();
        // 1.00 * 0.10 = 0.10 佣金
        $this->assertEquals(0.10, (float) $agent->commission_balance);
    }
}
