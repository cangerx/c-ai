<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\PromptTemplateResource\Pages;
use App\Models\PromptTemplate;
use App\Services\PromptAnalysisService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PromptTemplateResource extends Resource
{
    protected static ?string $model = PromptTemplate::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = '模板管理';
    protected static ?string $modelLabel = '提示词模板';
    protected static ?string $pluralModelLabel = '提示词模板';
    protected static string | UnitEnum | null $navigationGroup = '图片生成';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Hidden::make('task_id'),

            Section::make('提示词')->schema([
                Forms\Components\Textarea::make('original_prompt')->label('原始提示词')
                    ->rows(5)->required()
                    ->placeholder('粘贴完整的图片生成提示词'),
                \Filament\Schemas\Components\Actions::make([
                    Actions\Action::make('ai_extract')
                        ->label('智能解析')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('primary')
                        ->action(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                            $prompt = $get('original_prompt');
                            if (!$prompt) {
                                Notification::make()->title('请先填写原始提示词')->warning()->send();
                                return;
                            }
                            try {
                                $model = $get('parse_model') ?: null;
                                $result = app(PromptAnalysisService::class)->extractVariables($prompt, null, $model);
                                $set('template_prompt', $result['template_prompt'] ?? $prompt);
                                $vars = array_map(fn ($v) => array_merge(['type' => 'text'], $v), $result['variables'] ?? []);
                                $set('variables', $vars);
                                if (!empty($result['title'])) $set('title', $result['title']);
                                if (!empty($result['tags'])) $set('tags', $result['tags']);
                                Notification::make()->title('解析完成')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('解析失败: ' . $e->getMessage())->danger()->send();
                            }
                        }),
                ]),
                Forms\Components\Select::make('parse_model')
                    ->label('解析模型')
                    ->placeholder('默认（自动）')
                    ->options(fn () => \Illuminate\Support\Facades\DB::table('ai_models')
                        ->where('type', 'chat')->where('is_active', true)
                        ->pluck('display_name', 'model_id')->all())
                    ->dehydrated(false),
                Forms\Components\Textarea::make('template_prompt')->label('模板提示词（含变量）')
                    ->rows(5)->required()
                    ->helperText('变量格式: {{variable_name}}'),
            ]),

            Section::make('基本信息')->schema([
                Forms\Components\TextInput::make('title')->label('标题')->required()->maxLength(100),
                Forms\Components\Select::make('category_id')->label('分类')
                    ->relationship('category', 'name')
                    ->placeholder('选择分类'),
                Forms\Components\TextInput::make('tags')->label('标签')->placeholder('逗号分隔'),
                Forms\Components\Select::make('status')->label('状态')
                    ->options(['draft' => '草稿', 'published' => '已发布'])->default('draft'),
                Forms\Components\Toggle::make('is_featured')->label('推荐'),
                Forms\Components\TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Forms\Components\TextInput::make('preview_url')->label('预览图 URL')->url(),
            ])->columns(2),

            Section::make('变量定义')->schema([
                Forms\Components\Repeater::make('variables')->hiddenLabel()
                    ->schema([
                        Forms\Components\Select::make('type')->label('类型')
                            ->options(['text' => '文本', 'image' => '图片'])->default('text')->required()->live(),
                        Forms\Components\TextInput::make('name')->label('标识符')->required(),
                        Forms\Components\TextInput::make('label')->label('显示名')->required(),
                        Forms\Components\TextInput::make('description')->label('说明'),
                        Forms\Components\TextInput::make('default')->label('默认值')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('type') !== 'image'),
                        Forms\Components\TagsInput::make('alternatives')->label('备选项')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('type') !== 'image'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->collapsible()
                    ->cloneable()
                    ->reorderable()
                    ->itemLabel(fn (array $state) => ($state['label'] ?? '') . (($state['type'] ?? '') === 'image' ? ' [图片]' : ''))
                    ->addActionLabel('添加变量'),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_url')->label('预览')->height(50)->width(50),
                Tables\Columns\TextColumn::make('title')->label('标题')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('tags')->label('标签')->limit(20),
                Tables\Columns\TextColumn::make('variables')->label('变量')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . '个' : '0'),
                Tables\Columns\TextColumn::make('status')->label('状态')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'published' ? '已发布' : '草稿')
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_featured')->label('推荐')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('创建时间')->dateTime('m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['draft' => '草稿', 'published' => '已发布']),
            ])
            ->actions([
                Actions\Action::make('quick_publish')->label('发布')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (PromptTemplate $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(fn (PromptTemplate $record) => $record->update(['status' => 'published'])),
                Actions\Action::make('clone')->label('复制')->icon('heroicon-o-document-duplicate')->color('gray')
                    ->action(function (PromptTemplate $record) {
                        $clone = $record->replicate();
                        $clone->title = $record->title . ' (副本)';
                        $clone->status = 'draft';
                        $clone->save();
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkAction::make('bulk_publish')->label('批量发布')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['status' => 'published'])),
                Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('sort_order', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromptTemplates::route('/'),
            'create' => Pages\CreatePromptTemplate::route('/create'),
            'edit' => Pages\EditPromptTemplate::route('/{record}/edit'),
        ];
    }
}
