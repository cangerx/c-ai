<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\CommissionLogResource\Pages;
use App\Filament\Resources\UserResource;
use App\Models\CommissionLog;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionLogResource extends Resource
{
    protected static ?string $model = CommissionLog::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = '佣金记录';
    protected static ?string $modelLabel = '佣金';
    protected static string | UnitEnum | null $navigationGroup = '代理商';
    protected static ?int $navigationSort = 9;

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
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('agent.name')->label('归属代理')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('user.name')->label('分销员')->searchable()->weight('medium')
                    ->url(fn (CommissionLog $record) => UserResource::getUrl('edit', ['record' => $record->user_id])),
                Tables\Columns\TextColumn::make('fromUser.name')->label('来源用户')->searchable()
                    ->url(fn (CommissionLog $record) => $record->from_user_id ? UserResource::getUrl('edit', ['record' => $record->from_user_id]) : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('credits')->label('佣金积分')->sortable()
                    ->badge()->color('success')->suffix(' 积分'),
                Tables\Columns\TextColumn::make('created_at')->label('时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')->label('代理商')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissionLogs::route('/'),
        ];
    }
}
