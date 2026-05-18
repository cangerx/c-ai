<?php

namespace App\Filament\Agent\Widgets;

use App\Models\UsageLog;
use Filament\Widgets\ChartWidget;

class ModelUsageChart extends ChartWidget
{
    protected ?string $heading = '应用消耗排行 (本月)';
    protected static ?int $sort = 5;
    protected ?string $maxHeight = '260px';
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $agent = auth()->user();
        $childIds = $agent->children()->pluck('id');

        $rows = UsageLog::whereIn('user_id', $childIds)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('app_name, SUM(cost_credits) as total')
            ->groupBy('app_name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'app_name');

        $colors = ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#06b6d4', '#84cc16'];

        return [
            'datasets' => [[
                'data' => $rows->values()->toArray(),
                'backgroundColor' => array_slice($colors, 0, $rows->count()),
            ]],
            'labels' => $rows->keys()->map(fn ($n) => $n ?: '未知')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
