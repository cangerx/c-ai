<?php

namespace Database\Factories;

use App\Models\RedeemCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RedeemCode>
 */
class RedeemCodeFactory extends Factory
{
    protected $model = RedeemCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(32)),
            'type' => 'mixed',
            'credits' => 10,
            'balance' => 5.00,
            'status' => 'unused',
            'created_by' => User::factory()->admin(),
            'used_by' => null,
            'used_at' => null,
            'expires_at' => null,
            'batch_id' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'status' => 'used',
            'used_by' => User::factory(),
            'used_at' => now(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => 'disabled']);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
