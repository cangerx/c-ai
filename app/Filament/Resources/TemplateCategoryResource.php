<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\TemplateCategoryResource\Pages;
use App\Models\TemplateCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TemplateCategoryResource extends Resource
{
    protected static ?string $model = TemplateCategory::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = '模板分类';
    protected static ?string $modelLabel = '模板分类';
    protected static ?string $pluralModelLabel = '模板分类';
    protected static string | UnitEnum | null $navigationGroup = '图片生成';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->label('分类名称')->required()->maxLength(50),
            Forms\Components\TextInput::make('icon')->label('图标')->placeholder('heroicon-o-photo 或 emoji')
                ->helperText('支持 heroicon 名或 emoji'),
            Forms\Components\TextInput::make('sort_order')->label('排序')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('启用')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('名称')->searchable(),
                Tables\Columns\TextColumn::make('icon')->label('图标'),
                Tables\Columns\TextColumn::make('templates_count')->label('模板数')->counts('templates'),
                Tables\Columns\TextColumn::make('sort_order')->label('排序')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('启用')->boolean(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
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
            'index' => Pages\ListTemplateCategories::route('/'),
            'create' => Pages\CreateTemplateCategory::route('/create'),
            'edit' => Pages\EditTemplateCategory::route('/{record}/edit'),
        ];
    }
}
