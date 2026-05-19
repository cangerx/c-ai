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
        $channels = $this->getAvailableChannels($appName, $excludeId);

        if ($model) {
            $matched = $channels->filter(fn($ch) => in_array($model, $ch->models ?? []) || $ch->model === $model)->values();
            $channels = $matched;
        }

        // 优先选指定 request_mode 的渠道
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

    /**
     * 获取可用渠道列表（排除满载、暂停、指定ID）
     */
    public function getAvailableChannels(string $appName, ?int $excludeId = null): Collection
    {
        // 自动恢复：paused_at 超过 30 秒的渠道清除暂停状态
        AiChannel::where('status', 'active')
            ->where('app_name', $appName)
            ->whereNotNull('paused_at')
            ->where('paused_at', '<', now()->subSeconds(30))
            ->update(['paused_at' => null, 'error_count' => 0]);

        $channels = AiChannel::where('status', 'active')
            ->where('app_name', $appName)
            ->whereRaw('current_load < rate_limit')
            ->whereNull('paused_at')
            ->get();

        return $channels->filter(function ($ch) use ($excludeId) {
            if ($excludeId && $ch->id === $excludeId) return false;
            if ($ch->error_count >= $ch->max_errors) return false;
            return true;
        })->values();
    }

    /**
     * 按权重（priority）加权随机选一个渠道
     */
    public function selectByWeight(Collection $channels): ?AiChannel
    {
        if ($channels->isEmpty()) {
            return null;
        }

        // 每个渠道权重至少为 1
        $totalWeight = $channels->sum(fn($ch) => max($ch->priority, 1));
        $rand = mt_rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($channels as $ch) {
            $cumulative += max($ch->priority, 1);
            if ($rand <= $cumulative) {
                return $ch;
            }
        }

        return $channels->last();
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
