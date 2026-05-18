<?php

namespace App\Filament\Agent\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Agent\Resources\TransactionResource\Pages;
use App\Models\AgentTransaction;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = AgentTransaction::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = '交易记录';
    protected static ?string $modelLabel = '交易';
    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'recharge' => '充值',
                        'generate' => '生成兑换码',
                        'commission' => '佣金',
                        'withdraw' => '提现',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('credits')->label('积分变动'),
                Tables\Columns\TextColumn::make('balance')->label('余额变动')->prefix('¥'),
                Tables\Columns\TextColumn::make('credits_after')->label('积分余'),
                Tables\Columns\TextColumn::make('balance_after')->label('余额余')->prefix('¥'),
                Tables\Columns\TextColumn::make('description')->label('说明')->limit(30),
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
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}
