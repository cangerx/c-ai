<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = '套餐管理';
    protected static ?string $modelLabel = '套餐';
    protected static string | UnitEnum | null $navigationGroup = '业务配置';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('套餐信息')->schema([
                Forms\Components\TextInput::make('name')->label('名称')->required(),
                Forms\Components\Select::make('type')->label('类型')
                    ->options(['once' => '一次性', 'subscription' => '订阅'])
                    ->default('once')
                    ->required(),
                Forms\Components\TextInput::make('price')->label('价格')->numeric()->required()->prefix('¥'),
                Forms\Components\TextInput::make('credits')->label('积分')->numeric()->required()->suffix('积分'),
                Forms\Components\TextInput::make('balance')->label('赠送余额')->numeric()->default(0)->prefix('¥')
                    ->helperText('额外赠送的现金余额，可与积分共存'),
                Forms\Components\TextInput::make('duration_days')->label('有效天数')->numeric()
                    ->placeholder('永久')->helperText('留空表示永久有效'),
            ])->columns(2),
            Section::make('展示设置')->schema([
                Forms\Components\Toggle::make('is_featured')->label('推荐标记')->helperText('前端会高亮显示'),
                Forms\Components\Toggle::make('is_active')->label('启用')->default(true),
                Forms\Components\TextInput::make('sort_order')->label('排序')->numeric()->default(0)
                    ->helperText('数值越小越靠前'),
                Forms\Components\Textarea::make('features')->label('特性描述')->rows(3)
                    ->helperText('每行一个特性，前端会逐条展示')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('名称')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('type')->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'once' => '一次性',
                        'subscription' => '订阅',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('price')->label('价格')->money('CNY')->sortable(),
                Tables\Columns\TextColumn::make('credits')->label('积分')->suffix(' 积分'),
                Tables\Columns\TextColumn::make('balance')->label('赠送余额')->money('CNY')->toggleable(),
                Tables\Columns\IconColumn::make('is_featured')->label('推荐')->boolean()
                    ->trueIcon('heroicon-s-star')->falseIcon('heroicon-o-star')
                    ->trueColor('warning')->falseColor('gray'),
                Tables\Columns\IconColumn::make('is_active')->label('启用')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('排序')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
