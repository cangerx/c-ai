<?php

namespace App\Filament\Widgets;

use App\Models\GenerationTask;
use App\Models\UsageLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class GenerationTaskStatsWidget extends StatsOverviewWidget
{
    protected string | null $pollingInterval = '30s';

    protected function getStats(): array
    {
        $since = Carbon::now()->subDays(7);
        $base = GenerationTask::where('created_at', '>=', $since);

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $failed = (clone $base)->where('status', 'failed')->count();
        $processing = (clone $base)->whereIn('status', ['pending', 'processing'])->count();
        $rate = $total > 0 ? round($completed * 100 / $total, 1) : 0;

        $refund = UsageLog::where('app_name', 'image-gen')
            ->whereNotNull('refunded_at')
            ->where('refunded_at', '>=', $since)
            ->selectRaw('COUNT(*) as cnt, SUM(cost_credits) as credits')
            ->first();

        return [
            Stat::make('总任务（7天）', number_format($total)),
            Stat::make('已完成', number_format($completed))
                ->description($rate . '% 成功率')
                ->color('success'),
            Stat::make('失败', number_format($failed))
                ->color('danger'),
            Stat::make('进行中', number_format($processing))
                ->color('warning'),
            Stat::make('已退款', (int) ($refund->cnt ?? 0) . ' 笔')
                ->description('+' . number_format((int) ($refund->credits ?? 0)) . ' 积分')
                ->color('info'),
        ];
    }
}
