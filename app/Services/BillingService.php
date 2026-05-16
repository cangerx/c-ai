<?php

namespace App\Services;

use App\Models\CommissionLog;
use App\Models\SiteSetting;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingService
{
    public function getCost(): int
    {
        return (int) SiteSetting::get('billing_per_generation', 1);
    }

    public function canAfford(User $user): bool
    {
        return $user->credits >= $this->getCost();
    }

    public function charge(User $user, string $quality, array $meta = []): UsageLog
    {
        $cost = $this->getCost();

        return DB::transaction(function () use ($user, $cost, $quality, $meta) {
            $user = User::lockForUpdate()->find($user->id);

            if ($user->credits < $cost) {
                throw new RuntimeException('积分不足，请先充值');
            }

            $user->decrement('credits', $cost);
            $user->increment('total_consumed_credits', $cost);

            $log = UsageLog::create([
                'user_id' => $user->id,
                'app_name' => $meta['app_name'] ?? 'image-gen',
                'task_id' => $meta['task_id'] ?? null,
                'channel_id' => $meta['channel_id'] ?? null,
                'model' => $meta['model'] ?? null,
                'quality' => $quality,
                'cost_credits' => $cost,
                'cost_balance' => 0,
            ]);

            if ($user->parent_id) {
                $this->awardCommission($user->parent_id, $user->id, $log->id, $cost);
            }

            return $log;
        });
    }

    protected function awardCommission(int $distributorId, int $fromUserId, int $usageLogId, int $credits): void
    {
        $distributor = User::where('id', $distributorId)->where('is_distributor', true)->first();
        if (!$distributor) return;

        $rate = (float) SiteSetting::get('distributor_commission_rate', 0.10);
        if ($rate <= 0) return;

        $reward = (int) floor($credits * $rate);
        if ($reward < 1) return;

        $distributor->increment('commission_credits', $reward);
        $distributor->increment('credits', $reward);

        CommissionLog::create([
            'user_id' => $distributorId,
            'from_user_id' => $fromUserId,
            'usage_log_id' => $usageLogId,
            'credits' => $reward,
        ]);
    }

    public function refundLog(UsageLog $log): void
    {
        $affected = UsageLog::where('id', $log->id)
            ->whereNull('refunded_at')
            ->update(['refunded_at' => now()]);

        if ($affected > 0 && $log->cost_credits > 0) {
            User::where('id', $log->user_id)->increment('credits', $log->cost_credits);
        }
    }
}
