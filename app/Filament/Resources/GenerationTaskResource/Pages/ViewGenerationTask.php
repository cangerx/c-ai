<?php

namespace App\Filament\Resources\GenerationTaskResource\Pages;

use App\Filament\Resources\GenerationTaskResource;
use App\Models\AiModel;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Notifications\TaskFailed;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Redis;

class ViewGenerationTask extends ViewRecord
{
    protected static string $resource = GenerationTaskResource::class;
    protected static ?string $title = '任务详情';

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('任务信息')->schema([
                TextEntry::make('status')->label('状态')
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
                TextEntry::make('task_id')->label('Task ID')
                    ->fontFamily('mono')->copyable(),
                TextEntry::make('user.nickname')->label('用户')
                    ->formatStateUsing(fn ($record) => $record->user?->nickname ?: $record->user?->email ?: '—'),
                TextEntry::make('model')->label('模型')
                    ->formatStateUsing(fn (?string $state) => $state ? (AiModel::where('model_id', $state)->value('display_name') ?: $state) : '—'),
                TextEntry::make('mode')->label('模式')->default('—'),
                TextEntry::make('size')->label('尺寸')->fontFamily('mono'),
                TextEntry::make('quality')->label('质量')
                    ->formatStateUsing(fn (?string $state) => ['low' => '标清 1K', 'medium' => '高清 2K', 'high' => '超清 4K'][$state] ?? ($state ?: '—')),
                TextEntry::make('count')->label('数量'),
                TextEntry::make('input_count')->label('输入图数')->default('0'),
                TextEntry::make('is_public')->label('公开')
                    ->formatStateUsing(fn ($state) => $state ? '是' : '否'),
                TextEntry::make('created_at')->label('创建时间')->dateTime('Y-m-d H:i:s'),
                TextEntry::make('completed_at')->label('完成时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->placeholder('—'),
                TextEntry::make('duration')->label('耗时')
                    ->state(function ($record) {
                        if ($record->completed_at && $record->created_at) {
                            return $record->created_at->diffForHumans($record->completed_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true);
                        }
                        if ($record->status === 'processing' && $record->created_at) {
                            return $record->created_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) . '...';
                        }
                        return '—';
                    }),
                TextEntry::make('message')->label('消息')->default('—'),
            ])->columns(4),

            Section::make('提示词')->schema([
                TextEntry::make('prompt')->hiddenLabel()
                    ->extraAttributes(['style' => 'max-height:280px;overflow-y:auto']),
            ])->collapsible(),

            Section::make('计费 / 退款')->schema([
                TextEntry::make('billing_channel')->label('渠道')
                    ->state(function ($record) {
                        $log = UsageLog::with('channel')->where('task_id', $record->task_id)->first();
                        if (!$log) return '—';
                        return $log->channel?->display_name ?: $log->channel?->name ?: '#' . $log->channel_id;
                    }),
                TextEntry::make('billing_credits')->label('扣费 credits')
                    ->state(fn ($record) => (int) (UsageLog::where('task_id', $record->task_id)->value('cost_credits') ?? 0)),
                TextEntry::make('billing_balance')->label('扣费 balance')
                    ->state(fn ($record) => '¥' . number_format((float) (UsageLog::where('task_id', $record->task_id)->value('cost_balance') ?? 0), 2)),
                TextEntry::make('billing_refund')->label('退款')
                    ->state(function ($record) {
                        $log = UsageLog::where('task_id', $record->task_id)->first();
                        if (!$log) return '无记录';
                        return $log->refunded_at ? "已退款 · {$log->refunded_at}" : '未退款';
                    })
                    ->color(fn ($state) => str_contains($state, '已退款') ? 'success' : 'gray'),
            ])->columns(4)->visible(fn ($record) => UsageLog::where('task_id', $record->task_id)->exists()),

            Section::make('错误信息')->schema([
                TextEntry::make('error')->hiddenLabel()->fontFamily('mono')
                    ->color('danger'),
            ])->visible(fn ($record) => filled($record->error))->collapsed(),

            Section::make('生成结果')->schema([
                ImageEntry::make('items_urls')->hiddenLabel()
                    ->state(fn ($record) => collect($record->items ?? [])->pluck('url')->filter()->map(fn ($url) => $this->imageUrl($url))->values()->toArray())
                    ->height(160)
                    ->width(160)
                    ->square()
                    ->stacked(false)
                    ->extraImgAttributes(['class' => 'lightbox-img', 'style' => 'cursor:pointer']),
            ])->visible(fn ($record) => !empty($record->items)),

            Section::make('上传图片')->schema([
                ImageEntry::make('files_urls')->hiddenLabel()
                    ->state(fn ($record) => collect($record->files ?? [])->map(fn ($f) => is_array($f) ? ($f['url'] ?? '') : $f)->filter()->map(fn ($url) => $this->imageUrl($url))->values()->toArray())
                    ->height(160)
                    ->width(160)
                    ->square()
                    ->stacked(false)
                    ->extraImgAttributes(['class' => 'lightbox-img', 'style' => 'cursor:pointer']),
            ])->visible(fn ($record) => !empty($record->files)),
        ]);
    }

    protected function imageUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return asset(ltrim($url, '/'));
    }

    protected function getHeaderActions(): array
    {
        $record = $this->record;

        return [
            Actions\Action::make('back')->label('返回列表')
                ->icon('heroicon-o-arrow-left')->color('gray')
                ->url(GenerationTaskResource::getUrl('index')),

            Actions\Action::make('retry')->label('重跑')
                ->icon('heroicon-o-arrow-path')->color('warning')
                ->visible(in_array($record->status, ['failed', 'completed']))
                ->requiresConfirmation()
                ->modalHeading('重新执行任务')
                ->modalDescription('将重置任务状态并重新入队，不会退款也不会重新扣费。')
                ->action(function () use ($record) {
                    $record->update([
                        'status' => 'pending',
                        'message' => '管理员手动重跑，已入队。',
                        'error' => null,
                        'attempts' => 0,
                    ]);
                    for ($i = 0; $i < max(1, $record->count); $i++) {
                        Redis::rpush('image_gen_tasks', json_encode([
                            'task_id' => $record->task_id,
                            'index' => $i,
                        ]));
                    }
                    Notification::make()->title('已重新入队')->success()->send();
                    $this->redirect(static::getUrl(['record' => $record->task_id]));
                }),

            Actions\Action::make('refund')->label('退款')
                ->icon('heroicon-o-banknotes')->color('success')
                ->visible(function () use ($record) {
                    $log = UsageLog::where('task_id', $record->task_id)->first();
                    return $log && is_null($log->refunded_at);
                })
                ->requiresConfirmation()
                ->modalHeading('退款给用户')
                ->modalDescription(function () use ($record) {
                    $log = UsageLog::where('task_id', $record->task_id)->first();
                    if (!$log) return '没有计费记录';
                    return sprintf('将退还 credits +%d，balance +%.2f', (int) $log->cost_credits, (float) $log->cost_balance);
                })
                ->action(function () use ($record) {
                    $log = UsageLog::where('task_id', $record->task_id)->first();
                    if (!$log || !is_null($log->refunded_at) || !$record->user) {
                        Notification::make()->title('退款失败')->danger()->send();
                        return;
                    }
                    app(\App\Services\BillingService::class)->refundLog($log);
                    Notification::make()->title('退款成功')->success()->send();
                    $this->redirect(static::getUrl(['record' => $record->task_id]));
                }),

            Actions\Action::make('forceFail')->label('强制失败')
                ->icon('heroicon-o-x-circle')->color('danger')
                ->visible($record->status !== 'completed' && $record->status !== 'failed')
                ->requiresConfirmation()
                ->modalHeading('强制标记为失败')
                ->action(function () use ($record) {
                    $record->update([
                        'status' => 'failed',
                        'message' => '管理员强制标记失败。',
                        'error' => ($record->error ?: '') . ' | 管理员手动强制失败于 ' . now(),
                    ]);
                    try { $record->user?->notify(new TaskFailed($record, '管理员已将任务标记为失败。')); } catch (\Throwable) {}
                    Notification::make()->title('已标记为失败')->success()->send();
                    $this->redirect(static::getUrl(['record' => $record->task_id]));
                }),
        ];
    }
}
