<?php

namespace App\Filament\Agent\Widgets;

use App\Models\UsageLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopUsersTable extends TableWidget
{
    protected static ?string $heading = 'Top 消费用户 (本月)';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $agent = auth()->user();
        $childIds = $agent->children()->pluck('id');

        return $table
            ->query(
                UsageLog::query()
                    ->whereIn('user_id', $childIds)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->selectRaw('user_id, SUM(cost_credits) as total_credits, MAX(created_at) as last_active, COUNT(*) as usage_count')
                    ->groupBy('user_id')
                    ->orderByDesc('total_credits')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.nickname')->label('用户')->default('-'),
                Tables\Columns\TextColumn::make('usage_count')->label('调用次数'),
                Tables\Columns\TextColumn::make('total_credits')->label('消耗积分')->numeric(),
                Tables\Columns\TextColumn::make('last_active')->label('最近活跃')->since(),
            ])
            ->defaultPaginationPageOption(10);
    }
}
