<?php

namespace App\Filament\Widgets;

use App\Models\AiChannel;
use App\Models\CommissionLog;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected ?string $heading = '今日实时概览';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayUsers = User::whereDate('created_at', today())->count();
        $yesterdayUsers = User::whereDate('created_at', today()->subDay())->count();

        $todayCredits = UsageLog::whereDate('created_at', today())->sum('cost_credits');
        $yesterdayCredits = UsageLog::whereDate('created_at', today()->subDay())->sum('cost_credits');

        $todayTasks = GenerationTask::whereDate('created_at', today())->count();
        $yesterdayTasks = GenerationTask::whereDate('created_at', today()->subDay())->count();

        $todaySuccess = GenerationTask::whereDate('created_at', today())->where('status', 'completed')->count();
        $successRate = $todayTasks > 0 ? round($todaySuccess / $todayTasks * 100, 1) : 0;

        $todayCommission = CommissionLog::whereDate('created_at', today())->sum('credits');

        $activeChannels = AiChannel::where('is_active', true)->where('status', 'active')->count();
        $errorChannels = AiChannel::where('status', 'error')->count();

        return [
            Stat::make('今日新增用户', $todayUsers)
                ->description($this->trend($todayUsers, $yesterdayUsers))
                ->descriptionIcon($todayUsers >= $yesterdayUsers ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayUsers >= $yesterdayUsers ? 'success' : 'danger'),
            Stat::make('今日消耗积分', number_format($todayCredits))
                ->description($this->trend($todayCredits, $yesterdayCredits))
                ->descriptionIcon($todayCredits >= $yesterdayCredits ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('primary'),
            Stat::make('今日图像生成', $todayTasks)
                ->description($this->trend($todayTasks, $yesterdayTasks))
                ->descriptionIcon($todayTasks >= $yesterdayTasks ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('info'),
            Stat::make('渠道成功率', $successRate . '%')
                ->description("成功 {$todaySuccess} / 总 {$todayTasks}")
                ->color($successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger')),
            Stat::make('今日佣金发放', number_format($todayCommission))
                ->description('积分')
                ->color('warning'),
            Stat::make('活跃渠道', $activeChannels)
                ->description("异常 {$errorChannels} 个")
                ->color($errorChannels > 0 ? 'danger' : 'success'),
        ];
    }

    private function trend(int|float $today, int|float $yesterday): string
    {
        if ($yesterday == 0) return $today > 0 ? '+100%' : '持平';
        $pct = round(($today - $yesterday) / $yesterday * 100, 1);
        return ($pct >= 0 ? '+' : '') . $pct . '% 环比昨日';
    }
}
