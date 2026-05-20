<?php

namespace App\Services;

use App\Models\AgentSite;
use App\Models\BillingRule;
use App\Models\CommissionLog;
use App\Models\SiteSetting;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingService
{
    /**
     * 获取扣费积分：优先匹配 billing_rules 表，未命中则用全局默认
     */
    public function getCost(string $model = '', string $quality = '', string $appName = 'image-gen', ?AgentSite $agentSite = null): int
    {
        if ($agentSite?->cost_per_generation) {
            return (int) $agentSite->cost_per_generation;
        }

        $rule = $this->matchRule($appName, $model, $quality);
        if ($rule) {
            return $rule->cost_credits;
        }
        return (int) SiteSetting::get('billing_per_generation', 1);
    }

    /**
     * 匹配计费规则：精确 > 通配符，quality 精确 > 不限
     */
    protected function matchRule(string $appName, string $model, string $quality): ?BillingRule
    {
        $rules = BillingRule::where('app_name', $appName)->get();
        if ($rules->isEmpty()) return null;

        $best = null;
        $bestScore = -1;

        foreach ($rules as $rule) {
            // 模型匹配
            if (!$this->wildcardMatch($rule->model_pattern, $model)) continue;

            // quality 匹配
            if ($rule->quality && $rule->quality !== $quality) continue;

            // 计算优先级分数：精确模型 > 通配符，有quality > 无quality
            $score = 0;
            if (!str_contains($rule->model_pattern, '*')) $score += 10;
            if ($rule->quality) $score += 1;

            if ($score > $bestScore) {
                $best = $rule;
                $bestScore = $score;
            }
        }

        return $best;
    }

    protected function wildcardMatch(string $pattern, string $value): bool
    {
        if ($pattern === '*') return true;
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
        return (bool) preg_match($regex, $value);
    }

    public function canAfford(User $user, string $model = '', string $quality = '', string $appName = 'image-gen', int $count = 1, ?AgentSite $agentSite = null): bool
    {
        return $user->credits >= $this->getCost($model, $quality, $appName, $agentSite) * $count;
    }

    public function charge(User $user, string $quality, array $meta = []): UsageLog
    {
        $model = $meta['model'] ?? '';
        $appName = $meta['app_name'] ?? 'image-gen';
        $count = $meta['count'] ?? 1;
        $agentSite = $meta['agent_site'] ?? null;
        $cost = $this->getCost($model, $quality, $appName, $agentSite instanceof AgentSite ? $agentSite : null) * $count;

        return DB::transaction(function () use ($user, $cost, $quality, $meta, $appName) {
            $user = User::lockForUpdate()->find($user->id);

            if ($user->credits < $cost) {
                throw new RuntimeException('积分不足，请先充值');
            }

            $user->decrement('credits', $cost);
            $user->increment('total_consumed_credits', $cost);

            $log = UsageLog::create([
                'user_id' => $user->id,
                'app_name' => $appName,
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
