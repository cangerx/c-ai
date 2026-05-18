<?php

namespace App\Filament\Widgets;

use App\Models\CommissionLog;
use Filament\Widgets\ChartWidget;

class CommissionChart extends ChartWidget
{
    protected ?string $heading = '佣金发放趋势（30天）';
    protected static ?int $sort = 5;
    protected ?string $maxHeight = '250px';
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $since = now()->subDays(29)->startOfDay();

        $commissions = CommissionLog::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, SUM(credits) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('m/d');
            $data[] = (int) ($commissions[$date] ?? 0);
        }

        return [
            'datasets' => [
                ['label' => '佣金（积分）', 'data' => $data, 'borderColor' => '#f59e0b', 'fill' => true, 'backgroundColor' => 'rgba(245,158,11,0.1)'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
