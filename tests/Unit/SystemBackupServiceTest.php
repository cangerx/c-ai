<?php

namespace Tests\Unit;

use App\Services\StorageProfileService;
use App\Services\System\SystemBackupService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_remote_preserves_valid_backup_filename(): void
    {
        Storage::fake('remote_backup');
        config(['system.backup_dir' => sys_get_temp_dir() . '/cang-ai-test-backups-' . bin2hex(random_bytes(3))]);

        $filename = 'cang-ai-backup-20260523-120000-abcdef.tar.gz';
        $key = 'system-backups/20260523/' . $filename;
        Storage::disk('remote_backup')->put($key, file_get_contents($this->makeBackupArchive($filename)));

        $this->app->instance(StorageProfileService::class, new class extends StorageProfileService {
            public function isCloud(string $purpose): bool
            {
                return $purpose === self::PURPOSE_BACKUP;
            }

            public function disk(string $purpose): Filesystem
            {
                return Storage::disk('remote_backup');
            }

            public function driverForPurpose(string $purpose): string
            {
                return 'r2';
            }
        });

        $service = app(SystemBackupService::class);
        $info = $service->importRemote($key);

        $this->assertSame($filename, $info['filename']);
        $this->assertFileExists(config('system.backup_dir') . '/' . $filename);
        $this->assertTrue(collect($service->listRemote())->firstWhere('key', $key)['local_exists']);
    }

    protected function makeBackupArchive(string $filename): string
    {
        $workDir = sys_get_temp_dir() . '/cang-ai-test-archive-' . bin2hex(random_bytes(3));
        $path = sys_get_temp_dir() . '/' . $filename;
        mkdir($workDir, 0755, true);
        file_put_contents($workDir . '/manifest.json', json_encode(['app' => 'cang-ai', 'reason' => 'test']));

        exec('cd ' . escapeshellarg($workDir) . ' && tar -czf ' . escapeshellarg($path) . ' . 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));

        return $path;
    }
}
