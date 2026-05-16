<?php

namespace App\Console\Commands;

use App\Models\GenerationTask;
use App\Models\UsageLog;
use Illuminate\Console\Command;
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
        $stuck = GenerationTask::where('attempts', '<', 3)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(5));
                })->orWhere(function ($q2) {
                    $q2->where('status', 'pending')->where('updated_at', '<', now()->subMinutes(3));
                });
            })
            ->get();

        foreach ($stuck as $task) {
            $task->update([
                'status' => 'pending',
                'message' => '正在重试...',
                'attempts' => $task->attempts + 1,
            ]);

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
            $task->update([
                'status' => 'failed',
                'message' => '生成失败，已自动退款，请重试',
                'error' => '多次重试仍未完成',
            ]);
            $this->refund($task);
            $abandoned++;
        }

        if ($recovered + $abandoned > 0) {
            $this->info("恢复 {$recovered} 个任务，放弃 {$abandoned} 个任务。");
        }

        return 0;
    }

    protected function refund(GenerationTask $task): void
    {
        $affected = UsageLog::where('task_id', $task->task_id)
            ->whereNull('refunded_at')
            ->update(['refunded_at' => now()]);

        if ($affected > 0) {
            $log = UsageLog::where('task_id', $task->task_id)->first();
            if ($log && $task->user) {
                if ($log->cost_credits > 0) {
                    $task->user->increment('credits', $log->cost_credits);
                }
                if ($log->cost_balance > 0) {
                    $task->user->increment('balance', $log->cost_balance);
                }
            }
        }
    }
}
