<?php

namespace App\Filament\Resources;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\GenerationTaskResource\Pages;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Notifications\TaskFailed;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Redis;

class GenerationTaskResource extends Resource
{
    protected static ?string $model = GenerationTask::class;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = '生成任务';
    protected static ?string $modelLabel = '生成任务';
    protected static ?string $pluralModelLabel = '生成任务';
    protected static string | UnitEnum | null $navigationGroup = '图片生成';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'task_id';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('任务信息')->schema([
                Forms\Components\TextInput::make('task_id')->label('Task ID')->disabled(),
                Forms\Components\TextInput::make('status')->label('状态')->disabled(),
                Forms\Components\TextInput::make('model')->label('模型')->disabled(),
                Forms\Components\TextInput::make('size')->label('尺寸')->disabled(),
                Forms\Components\TextInput::make('quality')->label('质量')->disabled(),
                Forms\Components\TextInput::make('count')->label('数量')->disabled(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,email,nickname,name'))
            ->columns([
                Tables\Columns\TextColumn::make('status')->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => [
                        'pending' => '排队',
                        'processing' => '生成中',
                        'completed' => '完成',
                        'failed' => '失败',
                    ][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('task_id')->label('Task ID')
                    ->fontFamily('mono')->size('sm')->color('gray')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->task_id)
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.nickname')->label('用户')
                    ->formatStateUsing(fn ($record) => $record->user?->nickname ?: $record->user?->email ?: '—')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('user', function ($u) use ($search) {
                            $u->where('email', 'like', "%{$search}%")
                              ->orWhere('nickname', 'like', "%{$search}%")
                              ->orWhere('name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('model')->label('模型')
                    ->badge()->color('info')->size('sm'),

                Tables\Columns\TextColumn::make('size')->label('尺寸')
                    ->size('sm')->color('gray'),

                Tables\Columns\TextColumn::make('quality')->label('质量')
                    ->size('sm')->color('gray')->toggleable(),

                Tables\Columns\TextColumn::make('count')->label('数量')
                    ->numeric()->alignCenter()->toggleable(),

                Tables\Columns\TextColumn::make('refund_status')->label('退款')
                    ->state(function ($record) {
                        $log = UsageLog::where('task_id', $record->task_id)
                            ->where('app_name', 'image-gen')->first();
                        if (! $log) return '—';
                        if ($log->refunded_at) return '已退 +' . $log->cost_credits;
                        if ($record->status === 'failed') return '未退';
                        return '—';
                    })
                    ->badge()
                    ->color(function ($state) {
                        if (str_starts_with((string) $state, '已退')) return 'success';
                        if ($state === '未退') return 'danger';
                        return 'gray';
                    }),

                Tables\Columns\TextColumn::make('error')->label('错误')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->error)
                    ->color('danger')->size('xs')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('elapsed')->label('耗时')
                    ->state(function ($record) {
                        if ($record->completed_at && $record->created_at) {
                            return $record->created_at->diffForHumans($record->completed_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true);
                        }
                        if ($record->status === 'processing' && $record->created_at) {
                            return $record->created_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) . '...';
                        }
                        return '—';
                    })
                    ->size('sm')->color('gray'),

                Tables\Columns\TextColumn::make('created_at')->label('创建时间')
                    ->dateTime('m-d H:i:s')->sortable()->size('sm')->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('状态')
                    ->options([
                        'pending' => '排队',
                        'processing' => '生成中',
                        'completed' => '完成',
                        'failed' => '失败',
                    ]),
                Tables\Filters\Filter::make('range')
                    ->label('时间范围')
                    ->schema([
                        Forms\Components\Select::make('range')->label('范围')
                            ->options([
                                'today' => '今天',
                                '7d' => '近 7 天',
                                '30d' => '近 30 天',
                                'all' => '全部',
                            ])
                            ->default('7d'),
                    ])
                    ->query(function ($query, array $data) {
                        $range = $data['range'] ?? '7d';
                        return match ($range) {
                            'today' => $query->where('created_at', '>=', \Illuminate\Support\Carbon::today()),
                            '30d' => $query->where('created_at', '>=', \Illuminate\Support\Carbon::now()->subDays(30)),
                            'all' => $query,
                            default => $query->where('created_at', '>=', \Illuminate\Support\Carbon::now()->subDays(7)),
                        };
                    }),
            ])
            ->actions([
                Actions\ViewAction::make()->label('详情')->icon('heroicon-o-eye'),

                Actions\Action::make('createTemplate')->label('创建模板')
                    ->icon('heroicon-o-sparkles')->color('info')
                    ->visible(fn (GenerationTask $record) => $record->status === 'completed' && $record->is_public)
                    ->url(fn (GenerationTask $record) => PromptTemplateResource::getUrl('create', [
                        'task_id' => $record->task_id,
                    ])),

                Actions\Action::make('retry')->label('重跑')
                    ->icon('heroicon-o-arrow-path')->color('warning')
                    ->visible(fn (GenerationTask $record) => in_array($record->status, ['failed', 'completed']))
                    ->requiresConfirmation()
                    ->modalHeading('重新执行任务')
                    ->modalDescription(fn (GenerationTask $record) => '将重置任务状态并重新入队，不会退款也不会重新扣费。Task ID: ' . $record->task_id)
                    ->action(function (GenerationTask $record) {
                        $record->update([
                            'status' => 'pending',
                            'message' => '管理员手动重跑，已入队。',
                            'error' => null,
                            'attempts' => 0,
                        ]);
                        $items = $record->items ?? [];
                        for ($i = 0; $i < max(1, $record->count); $i++) {
                            if (! isset($items[$i]) || ! is_array($items[$i])) {
                                Redis::rpush('image_gen_tasks', json_encode([
                                    'task_id' => $record->task_id,
                                    'index' => $i,
                                ]));
                            }
                        }
                        Notification::make()->title('已重新入队')->body($record->task_id)->success()->send();
                    }),

                Actions\Action::make('refund')->label('退款')
                    ->icon('heroicon-o-banknotes')->color('success')
                    ->visible(function (GenerationTask $record) {
                        $log = UsageLog::where('task_id', $record->task_id)->first();
                        return $log && is_null($log->refunded_at);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('退款给用户')
                    ->modalDescription(function (GenerationTask $record) {
                        $log = UsageLog::where('task_id', $record->task_id)->first();
                        if (! $log) return '没有计费记录';
                        return sprintf('将退还 credits +%d，balance +%.2f', (int) $log->cost_credits, (float) $log->cost_balance);
                    })
                    ->action(function (GenerationTask $record) {
                        $log = UsageLog::where('task_id', $record->task_id)->first();
                        if (! $log) {
                            Notification::make()->title('退款失败')->body('该任务没有计费记录')->danger()->send();
                            return;
                        }
                        if (! is_null($log->refunded_at)) {
                            Notification::make()->title('已退款')->body('该任务已退款于 ' . $log->refunded_at)->warning()->send();
                            return;
                        }
                        if (! $record->user) {
                            Notification::make()->title('退款失败')->body('用户不存在')->danger()->send();
                            return;
                        }
                        app(\App\Services\BillingService::class)->refundLog($log);
                        Notification::make()
                            ->title('退款成功')
                            ->body(sprintf('credits +%d，balance +%.2f', (int) $log->cost_credits, (float) $log->cost_balance))
                            ->success()->send();
                    }),

                Actions\Action::make('forceFail')->label('强制失败')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (GenerationTask $record) => $record->status !== 'completed' && $record->status !== 'failed')
                    ->requiresConfirmation()
                    ->modalHeading('强制标记为失败')
                    ->modalDescription('仅修改数据库状态，不影响 worker 中正在执行的 Job。常用于卡死的 processing 任务。')
                    ->action(function (GenerationTask $record) {
                        $record->update([
                            'status' => 'failed',
                            'message' => '管理员强制标记失败。',
                            'error' => ($record->error ?: '') . ' | 管理员手动强制失败于 ' . now(),
                        ]);
                        try { $record->user?->notify(new TaskFailed($record, '管理员已将任务标记为失败。')); } catch (\Throwable) {}
                        Notification::make()->title('已标记为失败')->body($record->task_id)->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('15s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGenerationTasks::route('/'),
            'view'  => Pages\ViewGenerationTask::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
