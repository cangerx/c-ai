<?php

namespace App\Services;

use App\Models\BillingRule;
use App\Models\SiteSetting;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingService
{
    public function getCost(string $quality, ?string $model = null, string $appName = 'image-gen'): array
    {
        if ($model) {
            $rule = BillingRule::where('app_name', $appName)
                ->where(function ($q) use ($model) {
                    $q->where('model_pattern', $model)
                      ->orWhere('model_pattern', '*');
                })
                ->where(function ($q) use ($quality) {
                    $q->where('quality', $quality)
                      ->orWhereNull('quality');
                })
                ->orderByRaw("CASE WHEN model_pattern = '*' THEN 1 ELSE 0 END")
                ->orderByRaw("CASE WHEN quality IS NULL THEN 1 ELSE 0 END")
                ->first();

            if ($rule) {
                return [
                    'credits' => $rule->cost_credits,
                    'balance' => (float) $rule->cost_balance,
                ];
            }
        }

        $credits = (int) SiteSetting::get("billing_{$quality}_credits", 1);
        $balance = (float) SiteSetting::get("billing_{$quality}_balance", 0.10);

        return [
            'credits' => $credits,
            'balance' => $balance,
        ];
    }

    public function canAfford(User $user, string $quality, ?string $model = null): bool
    {
        $cost = $this->getCost($quality, $model);
        return $user->credits >= $cost['credits'] || $user->balance >= $cost['balance'];
    }

    public function charge(User $user, string $quality, array $meta = []): UsageLog
    {
        $model = $meta['model'] ?? null;
        $appName = $meta['app_name'] ?? 'image-gen';
        $cost = $this->getCost($quality, $model, $appName);

        return DB::transaction(function () use ($user, $cost, $quality, $meta) {
            $user = User::lockForUpdate()->find($user->id);

            $deductedCredits = 0;
            $deductedBalance = 0.0;

            if ($user->credits >= $cost['credits']) {
                $user->decrement('credits', $cost['credits']);
                $deductedCredits = $cost['credits'];
            } elseif ($user->balance >= $cost['balance']) {
                $user->decrement('balance', $cost['balance']);
                $deductedBalance = $cost['balance'];
            } else {
                throw new RuntimeException('余额不足，请先兑换充值');
            }

            $log = UsageLog::create([
                'user_id' => $user->id,
                'app_name' => $meta['app_name'] ?? 'image-gen',
                'task_id' => $meta['task_id'] ?? null,
                'channel_id' => $meta['channel_id'] ?? null,
                'model' => $meta['model'] ?? null,
                'quality' => $quality,
                'cost_credits' => $deductedCredits,
                'cost_balance' => $deductedBalance,
            ]);

            if ($deductedBalance > 0 && $user->parent_id) {
                $this->awardCommission($user->parent_id, $deductedBalance);
            }

            return $log;
        });
    }

    protected function awardCommission(int $agentId, float $amount): void
    {
        $rate = (float) SiteSetting::get('agent_commission_rate', 0.10);
        if ($rate <= 0) return;

        $commission = round($amount * $rate, 2);
        if ($commission < 0.01) return;

        User::where('id', $agentId)->increment('commission_balance', $commission);
    }
}
