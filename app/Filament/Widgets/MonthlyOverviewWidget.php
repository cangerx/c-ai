<?php

namespace App\Filament\Widgets;

use App\Models\CommissionLog;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlyOverviewWidget extends BaseWidget
{
    protected ?string $heading = '数据概览（近30天）';
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $since = now()->subDays(30);

        $newUsers = User::where('created_at', '>=', $since)->count();
        $totalCredits = UsageLog::where('created_at', '>=', $since)->sum('cost_credits');
        $totalTasks = GenerationTask::where('created_at', '>=', $since)->count();
        $successTasks = GenerationTask::where('created_at', '>=', $since)->where('status', 'completed')->count();
        $failedTasks = GenerationTask::where('created_at', '>=', $since)->where('status', 'failed')->count();
        $totalCommission = CommissionLog::where('created_at', '>=', $since)->sum('credits');
        $totalUsers = User::count();
        $totalBalance = UsageLog::where('created_at', '>=', $since)->sum('cost_balance');

        return [
            Stat::make('30天新增用户', number_format($newUsers))
                ->description('总用户 ' . number_format($totalUsers)),
            Stat::make('30天消耗积分', number_format($totalCredits)),
            Stat::make('图像生成（成功）', number_format($successTasks))
                ->description("总 {$totalTasks} / 失败 {$failedTasks}"),
            Stat::make('30天佣金发放', number_format($totalCommission))
                ->description('积分'),
            Stat::make('30天余额消耗', '¥' . number_format($totalBalance, 2)),
            Stat::make('平均成功率', $totalTasks > 0 ? round($successTasks / $totalTasks * 100, 1) . '%' : '0%'),
        ];
    }
}
