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
            Section::make('基本信息')->schema([
                Forms\Components\TextInput::make('title')->label('标题')->required()->maxLength(100),
                Forms\Components\TextInput::make('tags')->label('标签')->placeholder('逗号分隔，如：人像,风景,插画'),
                Forms\Components\Select::make('status')->label('状态')
                    ->options(['draft' => '草稿', 'published' => '已发布'])->default('draft'),
                Forms\Components\Toggle::make('is_featured')->label('推荐'),
                Forms\Components\TextInput::make('sort_order')->label('排序')->numeric()->default(0),
                Forms\Components\TextInput::make('preview_url')->label('预览图 URL')->url()->columnSpanFull(),
            ])->columns(3),

            Section::make('提示词')->schema([
                Forms\Components\Textarea::make('original_prompt')->label('原始提示词')
                    ->rows(4)->disabled()->dehydrated(),
                \Filament\Schemas\Components\Actions::make([
                    Actions\Action::make('ai_extract')
                        ->label('AI 提取变量')
                        ->icon('heroicon-o-cpu-chip')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('channel_id')->label('渠道')
                                ->options(fn () => \App\Models\AiChannel::where('status', 'active')
                                    ->get(['id', 'app_name', 'base_url'])
                                    ->mapWithKeys(fn ($ch) => [$ch->id => "#{$ch->id} [{$ch->app_name}] {$ch->base_url}"]))
                                ->placeholder('自动选择')
                                ->live()
                                ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('model', null)),
                            Forms\Components\Select::make('model')->label('模型')
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search, \Filament\Schemas\Components\Utilities\Get $get) {
                                    $channelId = $get('channel_id');
                                    if (!$channelId) {
                                        return [];
                                    }
                                    $channel = \App\Models\AiChannel::find($channelId);
                                    if (!$channel) {
                                        return [];
                                    }
                                    try {
                                        $url = rtrim($channel->base_url, '/') . '/v1/models';
                                        $ch = curl_init($url);
                                        curl_setopt_array($ch, [
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_TIMEOUT => 8,
                                            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $channel->api_key],
                                        ]);
                                        $result = curl_exec($ch);
                                        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                                        curl_close($ch);
                                        if ($status !== 200) {
                                            return [];
                                        }
                                        $data = json_decode($result, true);
                                        return collect($data['data'] ?? [])
                                            ->pluck('id')
                                            ->sort()
                                            ->filter(fn ($m) => !$search || str_contains($m, $search))
                                            ->mapWithKeys(fn ($m) => [$m => $m])
                                            ->toArray();
                                    } catch (\Throwable) {
                                        return [];
                                    }
                                })
                                ->placeholder('搜索模型（请先选择渠道）'),
                        ])
                        ->action(function (array $data, \Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                            $prompt = $get('original_prompt');
                            if (!$prompt) {
                                Notification::make()->title('请先填写原始提示词')->warning()->send();
                                return;
                            }
                            try {
                                $result = app(PromptAnalysisService::class)->extractVariables(
                                    $prompt,
                                    $data['channel_id'] ? (int) $data['channel_id'] : null,
                                    $data['model'] ?: null,
                                );
                                $set('template_prompt', $result['template_prompt'] ?? $prompt);
                                $set('variables', $result['variables'] ?? []);
                                Notification::make()->title('AI 提取完成')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()->title('提取失败')->body($e->getMessage())->danger()->send();
                            }
                        }),
                ]),
                Forms\Components\Textarea::make('template_prompt')->label('模板提示词（含 {{变量}}）')
                    ->rows(4)->required(),
            ]),

            Section::make('变量定义')->schema([
                Forms\Components\Repeater::make('variables')->hiddenLabel()
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('标识符')->required()->placeholder('subject'),
                        Forms\Components\TextInput::make('label')->label('中文名')->required()->placeholder('主题'),
                        Forms\Components\TextInput::make('description')->label('说明')->placeholder('图片的主要主题'),
                        Forms\Components\TextInput::make('default')->label('默认值')->required(),
                        Forms\Components\TagsInput::make('alternatives')->label('备选项'),
                    ])->columns(3)->defaultItems(0)->collapsible()->itemLabel(fn (array $state) => ($state['label'] ?? '') . ' (' . ($state['name'] ?? '') . ')'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_url')->label('预览')->height(50)->width(50),
                Tables\Columns\TextColumn::make('title')->label('标题')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('tags')->label('标签')->limit(20),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'published' ? '已发布' : '草稿')
                    ->color(fn (string $state) => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_featured')->label('推荐')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('排序')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('创建时间')->dateTime('m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['draft' => '草稿', 'published' => '已发布']),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
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
