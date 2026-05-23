<?php

namespace App\Console\Commands;

use App\Models\GenerationTask;
use App\Services\ImageStorageService;
use App\Services\StorageProfileService;
use Illuminate\Console\Command;

class CleanExpiredImages extends Command
{
    protected $signature = 'images:clean-expired {--days=3} {--hours=} {--temp : 仅清理过期上传/下载临时图}';
    protected $description = '删除超过指定时间的非公开图片';

    public function handle(ImageStorageService $storage): int
    {
        if ($this->option('temp')) {
            return $this->cleanTemporaryImages($storage);
        }

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

                $purpose = $item['purpose'] ?? StorageProfileService::PURPOSE_GENERATED;
                $key = $item['key'] ?? $storage->keyFromUrl($item['url'] ?? null, $purpose);
                if ($key) {
                    $objects[] = [
                        'key' => $key,
                        'purpose' => $purpose,
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

    protected function cleanTemporaryImages(ImageStorageService $storage): int
    {
        $ttlDays = max(1, (int) \App\Models\SiteSetting::get('storage_temp_ttl_days', 7));
        $fallbackCutoff = now()->subDays($ttlDays);
        $tasks = GenerationTask::whereNotNull('files')
            ->where(function ($query) use ($fallbackCutoff) {
                $query
                    ->where('created_at', '<', $fallbackCutoff)
                    ->orWhere('files', 'like', '%"expires_at"%');
            })
            ->cursor();

        $deleted = 0;
        $updated = 0;

        foreach ($tasks as $task) {
            $files = $task->files ?? [];
            if (!is_array($files) || $files === []) {
                continue;
            }

            $changed = false;
            $remaining = [];

            foreach ($files as $file) {
                if (!is_array($file)) {
                    $remaining[] = $file;
                    continue;
                }

                $purpose = $file['purpose'] ?? StorageProfileService::PURPOSE_GENERATED;
                if (!in_array($purpose, [StorageProfileService::PURPOSE_UPLOAD, StorageProfileService::PURPOSE_DOWNLOAD], true)) {
                    $remaining[] = $file;
                    continue;
                }

                $expiresAt = $this->temporaryFileExpiresAt($file, $task, $ttlDays);
                if (!$expiresAt || $expiresAt->isFuture()) {
                    $remaining[] = $file;
                    continue;
                }

                $key = $file['key'] ?? $storage->keyFromUrl($file['url'] ?? null, $purpose);
                if (!$key) {
                    $changed = true;
                    continue;
                }

                try {
                    $storage->delete($key, $purpose);
                    $deleted++;
                    $changed = true;
                } catch (\Throwable $e) {
                    $remaining[] = $file;
                    $this->warn("临时图删除失败: {$key} — {$e->getMessage()}");
                }
            }

            if ($changed) {
                $task->update(['files' => array_values($remaining)]);
                $updated++;
            }
        }

        $this->info("已清理 {$deleted} 张过期临时图，更新 {$updated} 个任务。");

        return 0;
    }

    protected function temporaryFileExpiresAt(array $file, GenerationTask $task, int $ttlDays): ?\Illuminate\Support\Carbon
    {
        if (!empty($file['expires_at'])) {
            try {
                return \Illuminate\Support\Carbon::parse($file['expires_at']);
            } catch (\Throwable) {
                // Fall back to the task timestamp when old records contain a malformed value.
            }
        }

        return $task->created_at?->copy()->addDays($ttlDays);
    }
}
