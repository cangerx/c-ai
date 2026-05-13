<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGenerationTask;
use App\Models\GenerationTask;
use Illuminate\Console\Command;

class RetryFailedGenerationTasks extends Command
{
    protected $signature = 'generation:retry-failed {--hours=24 : 只重试多少小时内的任务}';
    protected $description = '重试失败的图片生成任务';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $tasks = GenerationTask::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours($hours))
            ->limit(50)
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('没有需要重试的任务。');
            return 0;
        }

        foreach ($tasks as $task) {
            $task->update(['status' => 'pending', 'message' => '等待重试...', 'error' => null]);
            ProcessGenerationTask::dispatch($task->task_id);
        }

        $this->info("已重新派发 {$tasks->count()} 个任务。");
        return 0;
    }
}
