<?php

namespace App\Filament\Agent\Widgets;

use App\Models\AgentTransaction;
use App\Models\CommissionLog;
use Filament\Widgets\ChartWidget;

class RevenueBreakdownChart extends ChartWidget
{
    protected ?string $heading = '本月收支构成';
    protected static ?int $sort = 4;
    protected ?string $maxHeight = '260px';
    protected int|string|array $columnSpan = 1;
    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $agent = auth()->user();
        $monthStart = now()->startOfMonth();

        $recharge = (int) AgentTransaction::where('user_id', $agent->id)
            ->where('type', 'recharge')->where('created_at', '>=', $monthStart)->sum('credits');

        $commission = (int) CommissionLog::where('agent_id', $agent->id)
            ->where('created_at', '>=', $monthStart)->sum('credits');

        $spent = abs((int) AgentTransaction::where('user_id', $agent->id)
            ->where('type', 'generate')->where('created_at', '>=', $monthStart)->sum('credits'));

        return [
            'datasets' => [[
                'data' => [$recharge, $commission, $spent],
                'backgroundColor' => ['#10b981', '#6366f1', '#f59e0b'],
            ]],
            'labels' => ['充值', '佣金', '生成兑换码'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
