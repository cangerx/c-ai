<?php

namespace App\Console\Commands;

use App\Models\GenerationTask;
use App\Models\UsageLog;
use App\Notifications\TaskFailed;
use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RecoverStuckTasks extends Command
{
    protected $signature = 'generation:recover-stuck';
    protected $description = '恢复卡死的图片生成任务';

    public function handle(): int
    {
        $recovered = 0;
        $abandoned = 0;

        // 卡在 processing 超过 5 分钟，或 pending 超过 3 分钟的任务
        $stuck = GenerationTask::where(function ($q) {
                $q->whereNull('attempts')->orWhere('attempts', '<', 3);
            })
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(5));
                })->orWhere(function ($q2) {
                    $q2->where('status', 'pending')->where('updated_at', '<', now()->subMinutes(3));
                });
            })
            ->get();

        foreach ($stuck as $task) {
            $affected = GenerationTask::where('task_id', $task->task_id)
                ->whereIn('status', ['pending', 'processing'])
                ->where(function ($q) {
                    $q->whereNull('attempts')->orWhere('attempts', '<', 3);
                })
                ->update([
                    'status' => 'pending',
                    'message' => '正在重试...',
                    'attempts' => DB::raw('COALESCE(attempts, 0) + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) continue;

            $task->refresh();
            $items = $task->items ?? [];
            for ($i = 0; $i < $task->count; $i++) {
                if (!isset($items[$i]) || $items[$i] === null) {
                    Redis::rpush('image_gen_tasks', json_encode(['task_id' => $task->task_id, 'index' => $i]));
                }
            }
            $recovered++;
        }

        // 超过 3 次仍卡死的，标记失败并退款
        $hopeless = GenerationTask::where('attempts', '>=', 3)
            ->whereIn('status', ['processing', 'pending'])
            ->where('updated_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($hopeless as $task) {
            $items = $task->items ?? [];
            $completedItems = array_values(array_filter($items, fn($i) => is_array($i) && !empty($i['url'])));

            if (!empty($completedItems)) {
                $affected = GenerationTask::where('task_id', $task->task_id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->update([
                        'status' => 'completed',
                        'message' => count($completedItems) . "/{$task->count} 张成功（部分超时）",
                        'items' => json_encode($completedItems),
                        'completed_at' => now(),
                        'error' => '多次重试仍未全部完成',
                    ]);
                if ($affected > 0) {
                    try { $task->user?->notify(new \App\Notifications\TaskCompleted($task->fresh())); } catch (\Throwable) {}
                }
            } else {
                $affected = GenerationTask::where('task_id', $task->task_id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->update([
                        'status' => 'failed',
                        'message' => '生成失败，已自动退款，请重试',
                        'error' => '多次重试仍未完成',
                    ]);
                if ($affected > 0) {
                    $this->refund($task);
                    try { $task->user?->notify(new TaskFailed($task, '多次重试仍未完成，已自动退款。')); } catch (\Throwable) {}
                }
            }
            $abandoned++;
        }

        if ($recovered + $abandoned > 0) {
            $this->info("恢复 {$recovered} 个任务，放弃 {$abandoned} 个任务。");
        }

        return 0;
    }

    protected function refund(GenerationTask $task): void
    {
        $log = UsageLog::where('task_id', $task->task_id)->whereNull('refunded_at')->first();
        if ($log) {
            app(BillingService::class)->refundLog($log);
        }
    }
}
