<?php

namespace App\Services;

use App\Models\AgentSite;
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

            // 分销返佣：从代理积分池扣，发给分销员
            if ($user->parent_id) {
                $this->awardCommission($user, $log->id, $cost);
            }

            return $log;
        });
    }

    /**
     * 分销返佣逻辑：
     * 1. 找到用户的上级（分销员），必须是 is_distributor
     * 2. 找到分销员所属代理（通过 parent_id 链向上找 agent）
     * 3. 读代理分站的 commission_rate
     * 4. 从代理积分池扣除返佣积分
     * 5. 发给分销员
     */
    protected function awardCommission(User $consumer, int $usageLogId, int $credits): void
    {
        // 上级必须是分销员
        $distributor = User::where('id', $consumer->parent_id)
            ->where('is_distributor', true)
            ->first();
        if (!$distributor) return;

        // 找到所属代理（分销员的代理 = 分销员的 parent 是 agent，或分销员自己属于某个代理分站）
        $agentId = $this->findAgentId($distributor);
        if (!$agentId) return;

        // 读代理分站的返佣比例
        $site = AgentSite::where('user_id', $agentId)->where('is_active', true)->first();
        if (!$site || !$site->commission_rate) return;

        $rate = $site->commission_rate / 100;
        $reward = (int) floor($credits * $rate);
        if ($reward < 1) return;

        // 从代理积分池扣（代理承担成本）
        $agent = User::lockForUpdate()->find($agentId);
        if (!$agent || $agent->credits < $reward) return; // 代理积分不足则不发

        $agent->decrement('credits', $reward);
        $distributor->increment('commission_credits', $reward);

        CommissionLog::create([
            'user_id' => $distributor->id,
            'agent_id' => $agentId,
            'from_user_id' => $consumer->id,
            'usage_log_id' => $usageLogId,
            'credits' => $reward,
        ]);
    }

    /**
     * 向上查找用户所属的代理 ID
     */
    public function findAgentId(User $user): ?int
    {
        // 如果用户的 parent 是代理
        if ($user->parent_id) {
            $parent = User::find($user->parent_id);
            if ($parent && $parent->role === 'agent') {
                return $parent->id;
            }
            // 再往上找一层
            if ($parent && $parent->parent_id) {
                $grandParent = User::find($parent->parent_id);
                if ($grandParent && $grandParent->role === 'agent') {
                    return $grandParent->id;
                }
            }
        }
        return null;
    }

    public function refundLog(UsageLog $log): void
    {
        $affected = UsageLog::where('id', $log->id)
            ->whereNull('refunded_at')
            ->update(['refunded_at' => now()]);

        if ($affected > 0 && $log->cost_credits > 0) {
            User::where('id', $log->user_id)->increment('credits', $log->cost_credits);

            // 撤销佣金：退回给代理，从分销员扣除
            $commission = CommissionLog::where('usage_log_id', $log->id)->first();
            if ($commission) {
                User::where('id', $commission->agent_id)->increment('credits', $commission->credits);
                User::where('id', $commission->user_id)
                    ->where('commission_credits', '>=', $commission->credits)
                    ->decrement('commission_credits', $commission->credits);
                $commission->delete();
            }
        }
    }
}
