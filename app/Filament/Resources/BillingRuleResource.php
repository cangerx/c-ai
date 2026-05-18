<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\BillingRuleResource\Pages;
use App\Models\BillingRule;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class BillingRuleResource extends Resource
{
    protected static ?string $model = BillingRule::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = '计费规则';
    protected static ?string $modelLabel = '计费规则';
    protected static string | UnitEnum | null $navigationGroup = '业务配置';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('匹配条件')->description('系统按应用名+模型匹配计费规则')->schema([
                Forms\Components\TextInput::make('app_name')->label('应用名称')->required(),
                Forms\Components\TextInput::make('model_pattern')->label('模型匹配')->required()
                    ->helperText('支持通配符，如 gpt-4*、flux-*'),
                Forms\Components\TextInput::make('quality')->label('质量等级')
                    ->placeholder('如 hd、standard'),
            ])->columns(2),
            Section::make('扣费金额')->schema([
                Forms\Components\TextInput::make('cost_credits')->label('消耗积分')->numeric()->required()
                    ->suffix('积分'),
                Forms\Components\TextInput::make('cost_balance')->label('消耗余额')->numeric()->default(0)
                    ->prefix('¥'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('app_name')->label('应用')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('model_pattern')->label('模型匹配')->searchable()
                    ->fontFamily('mono')->size('sm'),
                Tables\Columns\TextColumn::make('quality')->label('质量')->placeholder('—'),
                Tables\Columns\TextColumn::make('cost_credits')->label('积分')->badge()->color('info'),
                Tables\Columns\TextColumn::make('cost_balance')->label('余额')->money('CNY'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBillingRules::route('/'),
            'create' => Pages\CreateBillingRule::route('/create'),
            'edit' => Pages\EditBillingRule::route('/{record}/edit'),
        ];
    }
}
