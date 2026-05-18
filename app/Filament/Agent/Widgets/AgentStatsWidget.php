<?php

namespace App\Filament\Agent\Widgets;

use App\Models\UsageLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AgentStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $agent = auth()->user();
        $childIds = $agent->children()->pluck('id');

        $subUsers = $childIds->count();
        $todayUsers = $agent->children()->whereDate('created_at', today())->count();
        $todayCredits = UsageLog::whereIn('user_id', $childIds)->whereDate('created_at', today())->sum('cost_credits');

        return [
            Stat::make('下级用户', $subUsers)
                ->description("今日 +{$todayUsers}")
                ->icon('heroicon-o-users'),
            Stat::make('今日消耗积分', number_format($todayCredits))
                ->icon('heroicon-o-fire'),
            Stat::make('积分余额', number_format($agent->credits))
                ->description('余额 ¥' . number_format($agent->balance, 2))
                ->icon('heroicon-o-wallet'),
        ];
    }
}
