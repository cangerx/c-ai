<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\AiChannelResource\Pages;
use App\Models\AiChannel;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class AiChannelResource extends Resource
{
    protected static ?string $model = AiChannel::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = '渠道管理';
    protected static ?string $modelLabel = '渠道';
    protected static string | UnitEnum | null $navigationGroup = 'AI 配置';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('基本信息')->description('渠道的接入配置，决定调用哪个 AI 服务')->schema([
                Forms\Components\TextInput::make('name')->label('渠道标识')->required()
                    ->helperText('唯一标识，用于系统内部识别'),
                Forms\Components\TextInput::make('display_name')->label('渠道备注')
                    ->helperText('备注信息，方便区分多个渠道'),
                Forms\Components\Select::make('provider')->label('供应商')
                    ->options([
                        'openai' => 'OpenAI',
                        'azure' => 'Azure',
                        'anthropic' => 'Anthropic',
                        'custom' => '自定义',
                    ])->required(),
                Forms\Components\TextInput::make('base_url')->label('API 地址')->url()->required()
                    ->placeholder('https://api.openai.com/v1')
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('api_key')->label('API Key')->password()->revealable()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('model')->label('默认模型')
                    ->placeholder('gpt-4o'),
                Forms\Components\CheckboxList::make('models')->label('绑定模型名称')
                    ->options(function ($record, \Filament\Schemas\Components\Utilities\Get $get) {
                        $models = $record?->models ?? $get('models') ?? [];
                        if (empty($models)) {
                            return [];
                        }
                        return collect($models)->sort()->mapWithKeys(fn ($m) => [$m => $m])->toArray();
                    })
                    ->columns(3)
                    ->searchable()
                    ->bulkToggleable()
                    ->helperText('点击「获取模型」刷新列表')
                    ->columnSpanFull(),
                \Filament\Schemas\Components\Actions::make([
                    Actions\Action::make('fetchModels')
                        ->label('获取模型')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($record, $livewire, \Filament\Schemas\Components\Utilities\Get $get) {
                            $baseUrl = $record?->base_url ?? $get('base_url');
                            $apiKey = $record?->api_key ?? $get('api_key');
                            $models = static::fetchModels($baseUrl, $apiKey);
                            if (empty($models)) {
                                \Filament\Notifications\Notification::make()->title('获取失败，请检查 API 地址和 Key')->danger()->send();
                                return;
                            }
                            if ($record) {
                                $record->update(['models' => array_keys($models)]);
                            }
                            // 同步到 ai_models 表
                            foreach (array_keys($models) as $modelId) {
                                \App\Models\AiModel::firstOrCreate(
                                    ['model_id' => $modelId],
                                    ['display_name' => $modelId, 'type' => 'chat']
                                );
                            }
                            \Filament\Notifications\Notification::make()->title('已获取 ' . count($models) . ' 个模型并保存')->success()->send();
                            if ($record) {
                                $livewire->redirect(request()->header('Referer'));
                            }
                        }),
                    Actions\Action::make('testModel')
                        ->label('检测模型')
                        ->icon('heroicon-o-bolt')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('test_model')
                                ->label('选择模型')
                                ->options(function ($record, \Filament\Schemas\Components\Utilities\Get $get) {
                                    $models = $record?->models ?? $get('models') ?? [];
                                    return collect($models)->sort()->mapWithKeys(fn ($m) => [$m => $m])->toArray();
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (array $data, $record, \Filament\Schemas\Components\Utilities\Get $get) {
                            $baseUrl = $record?->base_url ?? $get('base_url');
                            $apiKey = $record?->api_key ?? $get('api_key');
                            $model = $data['test_model'];
                            $result = static::testModelAvailability($baseUrl, $apiKey, $model);
                            if ($result['success']) {
                                \Filament\Notifications\Notification::make()
                                    ->title("✅ {$model} 可用")
                                    ->body("响应时间: {$result['time']}ms")
                                    ->success()->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title("❌ {$model} 不可用")
                                    ->body($result['error'])
                                    ->danger()->send();
                            }
                        }),
                ])->columnSpanFull(),
            ])->columns(2),
            Section::make('调度参数')->description('控制渠道的负载、错误容忍和工作模式')->schema([
                Forms\Components\TextInput::make('priority')->label('优先级')->numeric()->default(0)
                    ->helperText('数值越大优先级越高'),
                Forms\Components\Select::make('request_mode')->label('请求模式')
                    ->options(['sync' => '同步', 'async' => '异步'])->default('sync'),
                Forms\Components\TextInput::make('rate_limit')->label('速率限制')->numeric()->default(60)
                    ->suffix('次/分'),
                Forms\Components\TextInput::make('max_errors')->label('最大错误数')->numeric()->default(5)
                    ->helperText('超过后自动暂停'),
                Forms\Components\Toggle::make('is_active')->label('启用')->default(true)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->label('备注')->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('provider')->label('供应商')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'openai' => 'success',
                        'azure' => 'info',
                        'anthropic' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('model')->label('模型')->color('gray'),
                Tables\Columns\TextColumn::make('priority')->label('优先级')->sortable()
                    ->badge()->color('gray'),
                Tables\Columns\TextColumn::make('current_load')->label('负载')->placeholder('0'),
                Tables\Columns\TextColumn::make('error_count')->label('错误')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => '正常',
                        'paused' => '暂停',
                        'error' => '异常',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_active')->label('启用')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('provider')->label('供应商')
                    ->options(['openai' => 'OpenAI', 'azure' => 'Azure', 'anthropic' => 'Anthropic', 'custom' => '自定义']),
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options(['active' => '正常', 'paused' => '暂停', 'error' => '异常']),
            ])
            ->actions([
                Actions\Action::make('resetErrors')
                    ->label('重置')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (AiChannel $record) => $record->error_count > 0)
                    ->action(fn (AiChannel $record) => $record->update(['error_count' => 0, 'status' => 'active', 'paused_at' => null])),
                Actions\Action::make('toggleActive')
                    ->label(fn (AiChannel $record) => $record->is_active ? '禁用' : '启用')
                    ->icon('heroicon-o-power')
                    ->color(fn (AiChannel $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (AiChannel $record) => $record->update(['is_active' => !$record->is_active])),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }

    protected static function fetchModels(?string $baseUrl, ?string $apiKey): array
    {
        if (!$baseUrl || !$apiKey) {
            return [];
        }
        try {
            $baseUrl = rtrim($baseUrl, '/');
            // 避免双重 /v1
            $url = str_ends_with($baseUrl, '/v1') ? $baseUrl . '/models' : $baseUrl . '/v1/models';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
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
                ->mapWithKeys(fn ($m) => [$m => $m])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function testModelAvailability(?string $baseUrl, ?string $apiKey, string $model): array
    {
        if (!$baseUrl || !$apiKey) {
            return ['success' => false, 'error' => '缺少 API 地址或 Key'];
        }
        try {
            $baseUrl = rtrim($baseUrl, '/');
            $baseUrl = preg_replace('#/v1$#', '', $baseUrl);
            $url = $baseUrl . '/v1/images/generations';
            $start = microtime(true);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $model,
                    'prompt' => 'a simple red circle on white background',
                    'size' => '1024x1024',
                    'n' => 1,
                ]),
            ]);
            $result = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            $time = round((microtime(true) - $start) * 1000);

            if ($status === 200) {
                return ['success' => true, 'time' => $time];
            }
            $body = json_decode($result, true);
            $error = $body['error']['message'] ?? "HTTP {$status}";
            return ['success' => false, 'error' => $error];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiChannels::route('/'),
            'create' => Pages\CreateAiChannel::route('/create'),
            'edit' => Pages\EditAiChannel::route('/{record}/edit'),
        ];
    }
}
