<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\ArtworkResource\Pages;
use App\Models\GenerationTask;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ArtworkResource extends Resource
{
    protected static ?string $model = GenerationTask::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = '作品管理';
    protected static ?string $modelLabel = '作品';
    protected static ?string $pluralModelLabel = '作品';
    protected static string | UnitEnum | null $navigationGroup = '图片生成';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'artworks';

    public static function table(Table $table): Table
    {
        return $table
            ->query(GenerationTask::query()->where('is_public', true)->where('status', 'completed'))
            ->columns([
                Tables\Columns\ImageColumn::make('first_image')
                    ->label('预览')
                    ->height(60)->width(60)
                    ->state(fn (GenerationTask $record) => collect($record->items)->pluck('url')->first()),
                Tables\Columns\TextColumn::make('prompt')->label('提示词')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('model')->label('模型')->limit(15),
                Tables\Columns\TextColumn::make('user.name')->label('用户'),
                Tables\Columns\TextColumn::make('completed_at')->label('完成时间')->dateTime('m-d H:i')->sortable(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->actions([
                Actions\Action::make('createTemplate')->label('创建模板')
                    ->icon('heroicon-o-sparkles')->color('info')
                    ->url(fn (GenerationTask $record) => PromptTemplateResource::getUrl('create', [
                        'task_id' => $record->task_id,
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArtworks::route('/'),
        ];
    }
}
