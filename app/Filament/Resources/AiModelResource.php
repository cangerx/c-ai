<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiModelResource\Pages;
use App\Models\AiModel;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class AiModelResource extends Resource
{
    protected static ?string $model = AiModel::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = '模型管理';
    protected static ?string $modelLabel = '模型';
    protected static string | UnitEnum | null $navigationGroup = 'AI 配置';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('model_id')->label('模型标识')
                ->required()->unique(ignoreRecord: true)
                ->helperText('API 模型名，如 gpt-image-2'),
            Forms\Components\TextInput::make('display_name')->label('前端显示名称')
                ->required()
                ->helperText('用户看到的名称，如 智能图片生成模型v2'),
            Forms\Components\Select::make('type')->label('类型')
                ->options(['chat' => '对话', 'image' => '图片'])
                ->default('chat')->required()->live(),
            Forms\Components\Toggle::make('is_active')->label('启用')->default(true),
            Section::make('图片模型配置')
                ->visible(fn (Get $get) => $get('type') === 'image')
                ->schema([
                    Forms\Components\CheckboxList::make('config.sizes')->label('支持的尺寸')
                        ->options([
                            'auto' => '自动',
                            '1:1' => '1:1',
                            '3:2' => '3:2',
                            '2:3' => '2:3',
                            '16:9' => '16:9',
                            '9:16' => '9:16',
                            '4:3' => '4:3',
                            '3:4' => '3:4',
                            '5:4' => '5:4',
                            '4:5' => '4:5',
                            '2:1' => '2:1',
                            '1:2' => '1:2',
                            '3:1' => '3:1',
                            '1:3' => '1:3',
                        ])->columns(7)
                        ->helperText('不选则前端显示全部尺寸'),
                    Forms\Components\CheckboxList::make('config.qualities')->label('支持的质量')
                        ->options([
                            'low' => '标清 1K',
                            'medium' => '高清 2K',
                            'high' => '超清 4K',
                        ])->columns(3)
                        ->helperText('不选则前端显示全部质量'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('model_id')->label('模型标识')->searchable(),
                Tables\Columns\TextColumn::make('display_name')->label('显示名称')->searchable(),
                Tables\Columns\TextColumn::make('type')->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'chat' ? '对话' : '图片')
                    ->color(fn (string $state) => $state === 'chat' ? 'info' : 'success'),
                Tables\Columns\IconColumn::make('is_active')->label('启用')->boolean(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiModels::route('/'),
            'create' => Pages\CreateAiModel::route('/create'),
            'edit' => Pages\EditAiModel::route('/{record}/edit'),
        ];
    }
}
