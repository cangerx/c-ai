<?php

namespace App\Console\Commands;

use App\Models\GenerationTask;
use App\Services\ImageStorageService;
use Illuminate\Console\Command;

class CleanExpiredImages extends Command
{
    protected $signature = 'images:clean-expired {--days=3}';
    protected $description = '删除超过指定天数的非公开图片';

    public function handle(ImageStorageService $storage): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $tasks = GenerationTask::where('is_public', false)
            ->where('status', 'completed')
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('items')
            ->cursor();

        $deleted = 0;

        foreach ($tasks as $task) {
            $items = $task->items ?? [];
            if (empty($items) || (isset($items[0]['expired']) && $items[0]['expired'])) {
                continue;
            }

            $allDeleted = true;
            foreach ($items as $item) {
                if (is_array($item) && !empty($item['key'])) {
                    try {
                        $storage->delete($item['key']);
                        $deleted++;
                    } catch (\Throwable $e) {
                        $allDeleted = false;
                        $this->warn("删除失败: {$item['key']} — {$e->getMessage()}");
                    }
                }
            }

            if ($allDeleted) {
                $task->update(['items' => [['expired' => true]]]);
            }
        }

        $this->info("已清理 {$deleted} 张过期图片。");
        return 0;
    }
}
