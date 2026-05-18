<?php

namespace App\Filament\Agent\Widgets;

use App\Models\AgentTransaction;
use App\Models\CommissionLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $agent = auth()->user();
        $monthStart = now()->startOfMonth();

        $monthRecharge = AgentTransaction::where('user_id', $agent->id)
            ->where('type', 'recharge')
            ->where('created_at', '>=', $monthStart)
            ->sum('credits');

        $monthCommission = CommissionLog::where('agent_id', $agent->id)
            ->where('created_at', '>=', $monthStart)
            ->sum('credits');

        $monthSpent = AgentTransaction::where('user_id', $agent->id)
            ->where('type', 'generate')
            ->where('created_at', '>=', $monthStart)
            ->sum('credits');

        return [
            Stat::make('本月充值', number_format(abs($monthRecharge)) . ' 积分')
                ->chart($this->sparkline('recharge'))
                ->chartColor('success')
                ->color('success')
                ->icon('heroicon-o-arrow-trending-up'),
            Stat::make('本月佣金', number_format($monthCommission) . ' 积分')
                ->chart($this->sparkline('commission'))
                ->chartColor('info')
                ->color('info')
                ->icon('heroicon-o-gift'),
            Stat::make('本月支出', number_format(abs($monthSpent)) . ' 积分')
                ->chart($this->sparkline('generate'))
                ->chartColor('warning')
                ->color('warning')
                ->icon('heroicon-o-arrow-trending-down'),
        ];
    }

    private function sparkline(string $type): array
    {
        $agent = auth()->user();
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            if ($type === 'commission') {
                $data[] = (int) CommissionLog::where('agent_id', $agent->id)
                    ->whereDate('created_at', $date)->sum('credits');
            } else {
                $data[] = abs((int) AgentTransaction::where('user_id', $agent->id)
                    ->where('type', $type)
                    ->whereDate('created_at', $date)->sum('credits'));
            }
        }
        return $data;
    }
}
