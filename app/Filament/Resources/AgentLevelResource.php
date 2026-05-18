<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\AgentLevelResource\Pages;
use App\Models\AgentLevel;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class AgentLevelResource extends Resource
{
    protected static ?string $model = AgentLevel::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = '代理等级';
    protected static ?string $modelLabel = '代理等级';
    protected static string | UnitEnum | null $navigationGroup = '代理商';
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->label('等级名称')->required(),
            Forms\Components\TextInput::make('min_recharge')->label('最低累计充值 (¥)')->numeric()->required()->default(0),
            Forms\Components\TextInput::make('price_per_credit')->label('进货价 (¥/积分)')->numeric()->required()
                ->step(0.0001)->minValue(0.0001),
            Forms\Components\TextInput::make('sort_order')->label('排序')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('排序')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('等级名称')->weight('bold'),
                Tables\Columns\TextColumn::make('min_recharge')->label('最低充值')->money('CNY'),
                Tables\Columns\TextColumn::make('price_per_credit')->label('进货价/积分')->prefix('¥'),
                Tables\Columns\TextColumn::make('agents_count')->label('代理商数')
                    ->counts('agents'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentLevels::route('/'),
            'create' => Pages\CreateAgentLevel::route('/create'),
            'edit' => Pages\EditAgentLevel::route('/{record}/edit'),
        ];
    }
}
