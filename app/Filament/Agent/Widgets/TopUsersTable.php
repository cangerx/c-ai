<?php

namespace App\Filament\Agent\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopUsersTable extends TableWidget
{
    protected static ?string $heading = 'Top 消费用户 (本月)';
    protected static ?int $sort = 7;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $agent = auth()->user();

        return $table
            ->query(
                User::query()
                    ->where('parent_id', $agent->id)
                    ->whereHas('usageLogs', fn (Builder $q) => $q->where('created_at', '>=', now()->startOfMonth()))
                    ->withSum(['usageLogs as total_credits' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth())], 'cost_credits')
                    ->withCount(['usageLogs as usage_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth())])
                    ->withMax(['usageLogs as last_active' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth())], 'created_at')
            )
            ->defaultSort('total_credits', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nickname')->label('用户')->default('-'),
                Tables\Columns\TextColumn::make('usage_count')->label('调用次数'),
                Tables\Columns\TextColumn::make('total_credits')->label('消耗积分')->numeric(),
                Tables\Columns\TextColumn::make('last_active')->label('最近活跃')->since(),
            ])
            ->defaultPaginationPageOption(10);
    }
}
