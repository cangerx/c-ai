<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\AgentTransactionResource\Pages;
use App\Models\AgentTransaction;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AgentTransactionResource extends Resource
{
    protected static ?string $model = AgentTransaction::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = '充值流水';
    protected static ?string $modelLabel = '充值流水';
    protected static string | UnitEnum | null $navigationGroup = '代理商';
    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('代理')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'recharge' => '充值',
                        'generate' => '生成兑换码',
                        'commission' => '佣金',
                        'withdraw' => '提现',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'recharge' => 'success',
                        'generate' => 'warning',
                        'commission' => 'info',
                        'withdraw' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('credits')->label('积分变动')->numeric()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->prefix(fn ($state) => $state >= 0 ? '+' : ''),
                Tables\Columns\TextColumn::make('balance')->label('余额变动')->prefix('¥')->numeric(2)
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('credits_after')->label('积分余额'),
                Tables\Columns\TextColumn::make('balance_after')->label('账户余额')->prefix('¥')->numeric(2),
                Tables\Columns\TextColumn::make('description')->label('备注')->limit(30)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label('类型')
                    ->options([
                        'recharge' => '充值',
                        'generate' => '生成兑换码',
                        'commission' => '佣金',
                        'withdraw' => '提现',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')->label('代理')
                    ->options(fn () => User::where('role', 'agent')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentTransactions::route('/'),
        ];
    }
}
