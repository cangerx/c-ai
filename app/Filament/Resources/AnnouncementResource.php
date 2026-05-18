<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = '公告管理';
    protected static ?string $modelLabel = '公告';
    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Textarea::make('content')->label('内容')->required()->rows(3),
            Forms\Components\TextInput::make('url')->label('链接')->url()->maxLength(500),
            Forms\Components\Toggle::make('enabled')->label('启用')->default(true),
            Forms\Components\TextInput::make('sort')->label('排序')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('content')->label('内容')->limit(60),
                Tables\Columns\TextColumn::make('url')->label('链接')->limit(30),
                Tables\Columns\IconColumn::make('enabled')->label('启用')->boolean(),
                Tables\Columns\TextColumn::make('sort')->label('排序')->sortable(),
            ])
            ->defaultSort('sort')
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
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
