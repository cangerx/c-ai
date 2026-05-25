<?php

namespace App\Console\Commands;

use App\Models\GenerationTask;
use App\Models\ImageAsyncJob;
use App\Models\UsageLog;
use App\Notifications\TaskFailed;
use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 清理 async-oo 超时未回调的任务
 *
 * 默认每分钟跑，把 expires_at < now 且仍 pending 的 ImageAsyncJob 标失败，
 * 并将对应 GenerationTask 的该 index 置空（视为生成失败），
 * 若整任务全部失败 → 退款 + 通知。
 */
class AsyncOoSweepCommand extends Command
{
    protected $signature = 'async-oo:sweep {--limit=200}';
    protected $description = '清理 async-oo 超时未回调的任务并退款';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $jobs = ImageAsyncJob::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->limit($limit)
            ->get();

        if ($jobs->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("清理 {$jobs->count()} 个超时任务");

        foreach ($jobs as $job) {
            try {
                $this->sweep($job);
            } catch (Throwable $e) {
                Log::channel('upstream')->error('async_oo_sweep_failed', [
                    'job_id' => $job->id,
                    'task_id' => $job->task_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    protected function sweep(ImageAsyncJob $job): void
    {
        $job->update([
            'status' => 'failed',
            'error' => '上游未在 ' . $job->expires_at?->diffForHumans(now(), true) . ' 内回调',
            'completed_at' => now(),
        ]);

        $task = GenerationTask::where('task_id', $job->task_id)->first();
        if (!$task) return;

        $result = $this->saveItemAtIndex($task, $job->index, false);
        if ($result['all_done'] && $result['status'] === 'failed') {
            $task->refresh();
            $task->update([
                'message' => '生成超时（已自动退款）',
                'error' => 'async-oo callback timeout',
            ]);
            $log = UsageLog::where('task_id', $task->task_id)->whereNull('refunded_at')->first();
            if ($log) {
                app(BillingService::class)->refundLog($log);
            }
            try { $task->user?->notify(new TaskFailed($task, '生成超时')); } catch (Throwable) {}
        }
    }

    protected function saveItemAtIndex(GenerationTask $task, int $index, mixed $value): array
    {
        $result = ['all_done' => false, 'completed' => 0, 'status' => 'processing'];

        DB::transaction(function () use ($task, $index, $value, &$result) {
            $fresh = GenerationTask::where('task_id', $task->task_id)->lockForUpdate()->first();
            if (in_array($fresh->status, ['failed', 'completed'])) {
                $result['all_done'] = true;
                $result['status'] = $fresh->status;
                return;
            }
            $items = $fresh->items ?? [];
            $items[$index] = $value;

            $completed = count(array_filter($items, fn ($i) => is_array($i)));
            $failed = count(array_filter($items, fn ($i) => $i === false));

            if (($completed + $failed) >= $fresh->count) {
                $result['all_done'] = true;
                if ($completed > 0) {
                    $fresh->status = 'completed';
                    $fresh->message = "生成完成（{$completed}/{$fresh->count} 张成功）。";
                    $fresh->items = array_values(array_filter($items, fn ($i) => is_array($i)));
                    $fresh->completed_at = now();
                    $result['status'] = 'completed';
                } else {
                    $fresh->status = 'failed';
                    $fresh->message = '生成失败';
                    $fresh->items = [];
                    $result['status'] = 'failed';
                }
            } else {
                $fresh->items = $items;
                $fresh->message = "已完成 {$completed}/{$fresh->count} 张";
            }

            $fresh->save();
        });

        return $result;
    }
}
