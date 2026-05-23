<?php

namespace App\Services;

use App\Models\AiChannel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChannelDispatcher
{
    /**
     * 组合方法：获取可用渠道 → 按权重选一个 → 原子加负载
     */
    public function acquire(string $appName, ?int $excludeId = null, ?string $preferMode = null, ?string $model = null): ?AiChannel
    {
        $channels = $this->matchingChannels($appName, $excludeId, $model);

        if ($preferMode) {
            $preferred = $channels->filter(fn($ch) => ($ch->request_mode ?? 'sync') === $preferMode)->values();
            if ($preferred->isNotEmpty()) {
                $channels = $preferred;
            }
        }

        // 尝试最多 3 次（防止并发竞争导致 increment 失败）
        for ($i = 0; $i < 3; $i++) {
            $channel = $this->selectByWeight($channels);
            if (!$channel) {
                return null;
            }

            $locked = $this->incrementLoad($channel->id);
            if ($locked) {
                return $channel;
            }

            // increment 失败说明被其他进程抢占满了，排除后重选
            $channels = $channels->reject(fn($ch) => $ch->id === $channel->id)->values();
        }

        return null;
    }

    public function acquireFallback(string $appName, ?int $excludeId = null, ?string $model = null): ?AiChannel
    {
        $channels = $this->matchingChannels($appName, $excludeId, $model, true);

        if ($channels->isEmpty()) {
            return null;
        }

        $channels = $channels->sortBy([
            fn($a, $b) => ($a->status === 'active' ? 0 : 1) <=> ($b->status === 'active' ? 0 : 1),
            fn($a, $b) => ($a->error_count ?? 0) <=> ($b->error_count ?? 0),
            fn($a, $b) => ($a->current_load ?? 0) <=> ($b->current_load ?? 0),
            fn($a, $b) => ($b->priority ?? 0) <=> ($a->priority ?? 0),
        ])->values();

        foreach ($channels as $channel) {
            AiChannel::where('id', $channel->id)->update([
                'status' => 'active',
                'paused_at' => null,
                'current_load' => 0,
            ]);

            if ($this->incrementLoad($channel->id)) {
                return $channel->fresh();
            }
        }

        return null;
    }

    protected function matchingChannels(string $appName, ?int $excludeId = null, ?string $model = null, bool $includeCooling = false): Collection
    {
        $channels = $includeCooling
            ? $this->getFallbackChannels($appName, $excludeId)
            : $this->getAvailableChannels($appName, $excludeId);

        if ($model) {
            $channels = $channels
                ->filter(fn($ch) => $this->supportsRequestedModel($ch, $model))
                ->values();
        }

        return $channels;
    }

    public function supportsRequestedModel(AiChannel $channel, string $model): bool
    {
        if ($this->isNanoBananaModel($model) && $channel->provider !== 'nano-banana') {
            return false;
        }

        $models = $channel->models ?? [];
        if (empty($models) && empty($channel->model)) {
            return true;
        }

        if (in_array($model, $models, true)) {
            return true;
        }

        return $channel->model === $model;
    }

    protected function isNanoBananaModel(string $model): bool
    {
        return preg_match('/(^|[\/:_-])(gemini|nano[-_]?banana)(?:[\/:_-]|$)/i', $model) === 1;
    }

    /**
     * 获取可用渠道列表（排除满载、暂停、指定ID）
     */
    public function getAvailableChannels(string $appName, ?int $excludeId = null): Collection
    {
        // 自动恢复：paused_at 超过 30 秒的渠道清除暂停状态
        AiChannel::where('status', 'active')
            ->where('app_name', $appName)
            ->where('is_active', true)
            ->whereNotNull('paused_at')
            ->where('paused_at', '<', now()->subSeconds(30))
            ->update(['paused_at' => null, 'error_count' => 0]);

        $channels = AiChannel::where('status', 'active')
            ->where('app_name', $appName)
            ->where('is_active', true)
            ->whereRaw('current_load < rate_limit')
            ->whereNull('paused_at')
            ->get();

        return $channels->filter(function ($ch) use ($excludeId) {
            if ($excludeId && $ch->id === $excludeId) return false;
            if ($ch->error_count >= $ch->max_errors) return false;
            return true;
        })->values();
    }

    public function getFallbackChannels(string $appName, ?int $excludeId = null): Collection
    {
        return AiChannel::where('app_name', $appName)
            ->where('is_active', true)
            ->whereIn('status', ['active', 'paused'])
            ->whereRaw('current_load < rate_limit')
            ->get()
            ->filter(function ($ch) use ($excludeId) {
                if ($excludeId && $ch->id === $excludeId) return false;
                if (($ch->error_count ?? 0) >= max((int) $ch->max_errors, 1)) return false;
                return true;
            })
            ->values();
    }

    /**
     * 按权重（priority）加权随机选一个渠道
     */
    public function selectByWeight(Collection $channels): ?AiChannel
    {
        if ($channels->isEmpty()) {
            return null;
        }

        $weights = $channels->mapWithKeys(fn($ch) => [$ch->id => $this->scoreChannel($ch)]);
        $totalWeight = max(1, $weights->sum());
        $rand = mt_rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($channels as $ch) {
            $cumulative += $weights[$ch->id] ?? 1;
            if ($rand <= $cumulative) {
                return $ch;
            }
        }

        return $channels->last();
    }

    protected function scoreChannel(AiChannel $channel): int
    {
        $priority = max((int) $channel->priority, 1);
        $rateLimit = max((int) $channel->rate_limit, 1);
        $maxErrors = max((int) $channel->max_errors, 1);
        $loadRatio = min(1, max(0, (int) $channel->current_load / $rateLimit));
        $errorRatio = min(1, max(0, (int) $channel->error_count / $maxErrors));

        $score = $priority * 100;
        $score *= (1 - ($loadRatio * 0.7));
        $score *= (1 - ($errorRatio * 0.9));

        return max(1, (int) round($score));
    }

    /**
     * 原子加负载（仅在未满载时成功）
     */
    public function incrementLoad(int $channelId): bool
    {
        $affected = AiChannel::where('id', $channelId)
            ->whereRaw('current_load < rate_limit')
            ->update(['current_load' => DB::raw('current_load + 1')]);

        return $affected > 0;
    }

    /**
     * 释放负载（成功时调用），如果有错误记录则 -1
     */
    public function release(int $channelId): void
    {
        AiChannel::where('id', $channelId)->where('current_load', '>', 0)->decrement('current_load');

        AiChannel::where('id', $channelId)->where('error_count', '>', 0)->decrement('error_count');
    }

    /**
     * 释放负载 + 记录错误（失败时调用），达阈值则暂停
     */
    public function reportError(int $channelId): void
    {
        AiChannel::where('id', $channelId)->where('current_load', '>', 0)->decrement('current_load');

        $channel = AiChannel::find($channelId);
        if (!$channel) return;

        $channel->increment('error_count');

        if ($channel->fresh()->error_count >= $channel->max_errors) {
            // 冷却 30 秒后自动恢复，不再永久暂停
            $channel->update(['paused_at' => now()]);
        }
    }
}
