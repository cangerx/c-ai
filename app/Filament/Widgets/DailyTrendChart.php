<?php

namespace App\Filament\Widgets;

use App\Models\GenerationTask;
use App\Models\UsageLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DailyTrendChart extends ChartWidget
{
    protected ?string $heading = '积分消耗趋势（30天）';
    protected static ?int $sort = 4;
    protected ?string $maxHeight = '250px';
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $since = now()->subDays(29)->startOfDay();

        $credits = UsageLog::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, SUM(cost_credits) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $tasks = GenerationTask::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $creditsData = [];
        $tasksData = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('m/d');
            $creditsData[] = (int) ($credits[$date] ?? 0);
            $tasksData[] = (int) ($tasks[$date] ?? 0);
        }

        return [
            'datasets' => [
                ['label' => '积分消耗', 'data' => $creditsData, 'borderColor' => '#3b82f6', 'fill' => true, 'backgroundColor' => 'rgba(59,130,246,0.1)'],
                ['label' => '成功生成', 'data' => $tasksData, 'borderColor' => '#10b981', 'fill' => false],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
