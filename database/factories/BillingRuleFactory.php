<?php

namespace Database\Factories;

use App\Models\BillingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingRule>
 */
class BillingRuleFactory extends Factory
{
    protected $model = BillingRule::class;

    public function definition(): array
    {
        return [
            'app_name' => 'image-gen',
            'model_pattern' => 'gpt-image-2',
            'quality' => 'medium',
            'cost_credits' => 1,
            'cost_balance' => 0.10,
        ];
    }
}
