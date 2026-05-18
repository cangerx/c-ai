<?php

namespace App\Filament\Agent\Widgets;

use App\Models\CommissionLog;
use Filament\Widgets\ChartWidget;

class CommissionTrendChart extends ChartWidget
{
    protected ?string $heading = '佣金收入趋势 (30天)';
    protected static ?int $sort = 6;
    protected ?string $maxHeight = '240px';
    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $agent = auth()->user();
        $since = now()->subDays(29)->startOfDay();

        $byDay = CommissionLog::where('agent_id', $agent->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, SUM(credits) as c')
            ->groupByRaw('DATE(created_at)')->pluck('c', 'd');

        $labels = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('m/d');
            $data[] = (int) ($byDay[$date] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => '佣金积分',
                'data' => $data,
                'borderColor' => '#8b5cf6',
                'backgroundColor' => 'rgba(139,92,246,0.1)',
                'fill' => true,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
