<?php

namespace App\Filament\Agent\Widgets;

use App\Models\UsageLog;
use Filament\Widgets\ChartWidget;

class ConsumptionTrendChart extends ChartWidget
{
    protected ?string $heading = '消耗与增长趋势';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '280px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => '近7天', '30' => '近30天', '90' => '近90天'];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $agent = auth()->user();
        $childIds = $agent->children()->pluck('id');
        $since = now()->subDays($days - 1)->startOfDay();

        $creditsByDay = UsageLog::whereIn('user_id', $childIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, SUM(cost_credits) as c')
            ->groupByRaw('DATE(created_at)')->pluck('c', 'd');

        $usersByDay = $agent->children()->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupByRaw('DATE(created_at)')->pluck('c', 'd');

        $labels = [];
        $credits = [];
        $users = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('m/d');
            $credits[] = (int) ($creditsByDay[$date] ?? 0);
            $users[] = (int) ($usersByDay[$date] ?? 0);
        }

        return [
            'datasets' => [
                ['label' => '积分消耗', 'data' => $credits, 'borderColor' => '#f59e0b', 'backgroundColor' => 'rgba(245,158,11,0.1)', 'fill' => true, 'yAxisID' => 'y'],
                ['label' => '新增用户', 'data' => $users, 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,0.1)', 'fill' => true, 'yAxisID' => 'y1'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['position' => 'left', 'title' => ['display' => true, 'text' => '积分']],
                'y1' => ['position' => 'right', 'title' => ['display' => true, 'text' => '用户'], 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
