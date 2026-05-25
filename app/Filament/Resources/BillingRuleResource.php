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
    protected static ?string $pluralModelLabel = '计费规则';
    protected static string | UnitEnum | null $navigationGroup = '业务配置';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('匹配条件')
                ->description('按「应用+模型+质量」匹配，优先精确匹配。未命中则使用全局默认扣费。')
                ->schema([
                    Forms\Components\Select::make('app_name')->label('应用')
                        ->options([
                            'image-gen' => '图片生成',
                            'video-gen' => '视频生成',
                            'chat' => '对话',
                        ])
                        ->default('image-gen')
                        ->required(),
                    Forms\Components\TextInput::make('model_pattern')->label('模型匹配')
                        ->required()->default('*')
                        ->helperText('支持通配符：gpt-image-2、flux-*、* (所有)'),
                    Forms\Components\Select::make('quality')->label('质量等级')
                        ->options([
                            '' => '不限',
                            'low' => '低 (low)',
                            'medium' => '中 (medium)',
                            'high' => '高 (high)',
                            'hd' => 'HD',
                        ])
                        ->default('')
                        ->placeholder('不限'),
                ])->columns(3),

            Section::make('扣费设置')->schema([
                Forms\Components\TextInput::make('cost_credits')->label('消耗积分')
                    ->numeric()->required()->default(1)->minValue(0)
                    ->suffix('积分/次'),
                Forms\Components\TextInput::make('cost_balance')->label('消耗余额')
                    ->numeric()->default(0)->minValue(0)
                    ->prefix('¥')->suffix('/次')
                    ->helperText('为 0 表示不扣余额'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('cost_credits', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('app_name')->label('应用')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'image-gen' => '🎨 图片生成',
                        'video-gen' => '🎬 视频生成',
                        'chat' => '💬 对话',
                        default => $state,
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_pattern')->label('模型匹配')
                    ->searchable()->fontFamily('mono')->size('sm')
                    ->badge()->color('gray'),
                Tables\Columns\TextColumn::make('quality')->label('质量')
                    ->placeholder('不限')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'high', 'hd' => '⬆ ' . $state,
                        'medium' => '● ' . $state,
                        'low' => '⬇ ' . $state,
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('cost_credits')->label('积分/次')
                    ->badge()->color('info')->suffix(' 积分'),
                Tables\Columns\TextColumn::make('cost_balance')->label('余额/次')
                    ->money('CNY')->placeholder('—'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([Actions\DeleteBulkAction::make()])
            ->emptyStateHeading('暂无计费规则')
            ->emptyStateDescription('未配置规则时，系统使用「站点设置 → 计费设置」中的全局默认扣费。')
            ->emptyStateIcon('heroicon-o-calculator');
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
