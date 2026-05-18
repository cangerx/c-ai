<?php

namespace App\Filament\Agent\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Agent\Resources\AgentPlanResource\Pages;
use App\Models\AgentPlan;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgentPlanResource extends Resource
{
    protected static ?string $model = AgentPlan::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = '套餐管理';
    protected static ?string $modelLabel = '套餐';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('agent_id', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->label('套餐名称')->required(),
            Forms\Components\TextInput::make('price')->label('售价 (¥)')->numeric()->required()->minValue(0),
            Forms\Components\TextInput::make('credits')->label('积分')->numeric()->required()->minValue(0),
            Forms\Components\TextInput::make('sort_order')->label('排序')->numeric()->default(0),
            Forms\Components\Textarea::make('features')->label('特性 (每行一条)')->rows(3),
            Forms\Components\Toggle::make('is_featured')->label('推荐')->default(false),
            Forms\Components\Toggle::make('is_active')->label('启用')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('名称')->weight('bold'),
                Tables\Columns\TextColumn::make('price')->label('售价')->prefix('¥'),
                Tables\Columns\TextColumn::make('credits')->label('积分')->suffix(' 积分'),
                Tables\Columns\IconColumn::make('is_featured')->label('推荐')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('启用')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['agent_id'] = auth()->id();
        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentPlans::route('/'),
            'create' => Pages\CreateAgentPlan::route('/create'),
            'edit' => Pages\EditAgentPlan::route('/{record}/edit'),
        ];
    }
}
