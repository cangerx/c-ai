<?php

namespace App\Console\Commands;

use App\Models\GenerationTask;
use App\Services\ImageStorageService;
use App\Services\StorageProfileService;
use Illuminate\Console\Command;

class CleanExpiredImages extends Command
{
    protected $signature = 'images:clean-expired {--days=3} {--hours=}';
    protected $description = '删除超过指定时间的非公开图片';

    public function handle(ImageStorageService $storage): int
    {
        $hours = $this->option('hours');
        $cutoff = $hours !== null && $hours !== ''
            ? now()->subHours(max(1, (int) $hours))
            : now()->subDays(max(1, (int) $this->option('days')));

        $tasks = GenerationTask::where('is_public', false)
            ->where('status', 'completed')
            ->where('created_at', '<', $cutoff)
            ->cursor();

        $deleted = 0;

        foreach ($tasks as $task) {
            $items = $task->items ?? [];
            if (isset($items[0]['expired']) && $items[0]['expired']) {
                continue;
            }

            $allDeleted = true;
            $objects = [];

            foreach (array_merge($items, $task->files ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $key = $item['key'] ?? $storage->keyFromUrl($item['url'] ?? null);
                if ($key) {
                    $objects[] = [
                        'key' => $key,
                        'purpose' => $item['purpose'] ?? StorageProfileService::PURPOSE_GENERATED,
                    ];
                }
            }

            $seen = [];
            foreach ($objects as $object) {
                $dedupeKey = $object['purpose'] . ':' . $object['key'];
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;

                try {
                    $storage->delete($object['key'], $object['purpose']);
                    $deleted++;
                } catch (\Throwable $e) {
                    $allDeleted = false;
                    $this->warn("删除失败: {$object['key']} — {$e->getMessage()}");
                }
            }

            if ($allDeleted) {
                $task->update([
                    'items' => [['expired' => true]],
                    'files' => [],
                ]);
            }
        }

        $this->info("已清理 {$deleted} 张过期图片。");
        return 0;
    }
}
