<?php

namespace App\Services\System;

use App\Services\StorageProfileService;
use RuntimeException;

class SystemBackupService
{
    public function create(string $reason = 'manual'): array
    {
        $backupDir = $this->backupDir();
        $workDir = sys_get_temp_dir() . '/cang-ai-backup-work-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $filename = 'cang-ai-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.tar.gz';
        $path = $backupDir . '/' . $filename;

        $this->ensureCommandExists('tar');
        $this->ensureDirectory($backupDir);
        $this->ensureDirectory($workDir);

        try {
            $manifest = $this->manifest($reason);
            file_put_contents($workDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->backupEnv($workDir);
            $this->backupDatabase($workDir, $manifest);
            $this->backupStorage($workDir);

            $this->run('cd ' . escapeshellarg($workDir) . ' && tar -czf ' . escapeshellarg($path) . ' . 2>&1', 300);
        } finally {
            $this->run('rm -rf ' . escapeshellarg($workDir), 60, false);
        }

        $this->prune();
        $info = $this->fileInfo($path);
        $remote = $this->uploadRemoteBackup($path, $info['filename']);
        if ($remote) {
            $info['remote'] = $remote;
        }

        return $info;
    }

    public function list(): array
    {
        $files = glob($this->backupDir() . '/*.tar.gz') ?: [];
        rsort($files);

        return array_map(fn (string $path): array => $this->fileInfo($path), $files);
    }

    public function listRemote(): array
    {
        $profiles = app(StorageProfileService::class);
        if (!$profiles->isCloud(StorageProfileService::PURPOSE_BACKUP)) {
            return [];
        }

        $disk = $profiles->disk(StorageProfileService::PURPOSE_BACKUP);
        $files = $disk->allFiles('system-backups');
        rsort($files);

        return array_values(array_filter(array_map(function (string $key) use ($disk, $profiles): ?array {
            if (!str_ends_with($key, '.tar.gz')) {
                return null;
            }

            $filename = basename($key);
            if (!$this->isValidBackupFilename($filename)) {
                return null;
            }

            $modified = null;
            try {
                $modified = $disk->lastModified($key);
            } catch (\Throwable) {
                $modified = null;
            }

            $size = 0;
            try {
                $size = (int) $disk->size($key);
            } catch (\Throwable) {
                $size = 0;
            }

            return [
                'key' => $key,
                'filename' => $filename,
                'driver' => $profiles->driverForPurpose(StorageProfileService::PURPOSE_BACKUP),
                'size' => $size,
                'size_human' => $this->humanSize($size),
                'created_at' => $modified ? date('Y-m-d H:i:s', $modified) : '',
                'local_exists' => is_file($this->backupDir() . '/' . $filename),
            ];
        }, $files)));
    }

    public function delete(string $filename): void
    {
        $path = $this->pathFor($filename);
        if (!is_file($path)) {
            throw new RuntimeException('备份文件不存在');
        }

        unlink($path);
    }

    public function import(string $sourcePath, ?string $originalName = null): array
    {
        if (!is_file($sourcePath)) {
            throw new RuntimeException('上传的备份文件不存在');
        }

        $this->ensureCommandExists('tar');
        $this->assertArchiveIsSafe($sourcePath);
        $manifest = $this->readManifest($sourcePath);
        if (($manifest['app'] ?? '') !== 'cang-ai') {
            throw new RuntimeException('备份包不是 cang-ai 备份');
        }

        $this->ensureDirectory($this->backupDir());
        $filename = $this->safeImportFilename($originalName);
        $target = $this->backupDir() . '/' . $filename;
        if (is_file($target)) {
            $filename = 'cang-ai-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.tar.gz';
            $target = $this->backupDir() . '/' . $filename;
        }

        if (!copy($sourcePath, $target)) {
            throw new RuntimeException('备份包导入失败');
        }
        @chmod($target, 0600);

        $this->prune();

        return $this->fileInfo($target);
    }

    public function importRemote(string $key): array
    {
        if (!preg_match('#^system-backups/\d{8}/cang-ai-backup-\d{8}-\d{6}(-[a-f0-9]{6})?\.tar\.gz$#', $key)) {
            throw new RuntimeException('非法远端备份 Key');
        }

        $profiles = app(StorageProfileService::class);
        if (!$profiles->isCloud(StorageProfileService::PURPOSE_BACKUP)) {
            throw new RuntimeException('未配置系统备份远端存储');
        }

        $disk = $profiles->disk(StorageProfileService::PURPOSE_BACKUP);
        if (!$disk->exists($key)) {
            throw new RuntimeException('远端备份不存在');
        }

        $tempPath = sys_get_temp_dir() . '/cang-ai-remote-backup-' . bin2hex(random_bytes(4)) . '.tar.gz';
        $stream = $disk->readStream($key);
        if ($stream === false) {
            throw new RuntimeException('无法读取远端备份');
        }

        $target = fopen($tempPath, 'wb');
        if ($target === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('无法创建远端备份临时文件');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_resource($target)) {
                fclose($target);
            }
        }

        try {
            $info = $this->import($tempPath, basename($key));
        } finally {
            @unlink($tempPath);
        }

        return $info;
    }

    protected function safeImportFilename(?string $originalName): string
    {
        $name = $originalName ? basename($originalName) : '';
        if ($name !== '' && $this->isValidBackupFilename($name)) {
            return $name;
        }

        return 'cang-ai-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.tar.gz';
    }

    public function pathFor(string $filename): string
    {
        if (!$this->isValidBackupFilename($filename)) {
            throw new RuntimeException('非法备份文件名');
        }

        $path = realpath($this->backupDir() . '/' . $filename);
        $base = realpath($this->backupDir());
        if (!$path || !$base || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('备份文件不存在');
        }

        return $path;
    }

    protected function isValidBackupFilename(string $filename): bool
    {
        return (bool) preg_match('/^cang-ai-backup-\d{8}-\d{6}(-[a-f0-9]{6})?\.tar\.gz$/', $filename);
    }

    protected function backupEnv(string $workDir): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $this->ensureDirectory($workDir . '/env');
            copy($env, $workDir . '/env/.env');
        }
    }

    protected function backupDatabase(string $workDir, array $manifest): void
    {
        $this->ensureDirectory($workDir . '/database');
        $connection = config('database.default');
        $config = config("database.connections.{$connection}", []);

        if (($config['driver'] ?? '') === 'sqlite') {
            $database = $config['database'] ?? '';
            if ($database && $database !== ':memory:' && is_file($database)) {
                copy($database, $workDir . '/database/database.sqlite');
            }
            return;
        }

        if (in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            $this->ensureCommandExists('mysqldump');
            $defaultsFile = $this->writeMysqlDefaultsFile($workDir, $config);

            try {
                $cmd = implode(' ', array_filter([
                    'mysqldump',
                    '--no-defaults',
                    '--defaults-extra-file=' . escapeshellarg($defaultsFile),
                    '--single-transaction',
                    '--quick',
                    '--skip-lock-tables',
                    '--no-tablespaces',
                    escapeshellarg((string) ($config['database'] ?? '')),
                    '2>&1',
                    '> ' . escapeshellarg($workDir . '/database/database.sql'),
                ]));

                $this->run($cmd, 300);
            } finally {
                @unlink($defaultsFile);
            }
            return;
        }

        throw new RuntimeException('暂不支持的数据库备份类型: ' . ($config['driver'] ?? 'unknown'));
    }

    protected function writeMysqlDefaultsFile(string $workDir, array $config): string
    {
        $path = sys_get_temp_dir() . '/cang-ai-mysql-client-' . bin2hex(random_bytes(6)) . '.cnf';
        $content = implode("\n", [
            '[client]',
            'host=' . (string) ($config['host'] ?? '127.0.0.1'),
            'port=' . (string) ($config['port'] ?? '3306'),
            'user=' . (string) ($config['username'] ?? ''),
            'password=' . (string) ($config['password'] ?? ''),
            '',
        ]);

        file_put_contents($path, $content);
        @chmod($path, 0600);

        return $path;
    }

    protected function backupStorage(string $workDir): void
    {
        $this->ensureCommandExists('rsync');

        foreach (['public', 'private'] as $diskDir) {
            $source = storage_path("app/{$diskDir}");
            if (!is_dir($source)) {
                continue;
            }

            $target = $workDir . "/storage/app/{$diskDir}";
            $this->ensureDirectory(dirname($target));

            $excludeArgs = '';
            if ($diskDir === 'private') {
                $excludeArgs = implode(' ', [
                    '--exclude=' . escapeshellarg('backups'),
                    '--exclude=' . escapeshellarg('frontend-backups'),
                    '--exclude=' . escapeshellarg('livewire-tmp'),
                    '--exclude=' . escapeshellarg('backup-work-*'),
                    '--exclude=' . escapeshellarg('restore-work-*'),
                    '--exclude=' . escapeshellarg('*.tmp'),
                ]);
            }

            $this->run('rsync -a ' . $excludeArgs . ' ' . escapeshellarg($source . '/') . ' ' . escapeshellarg($target . '/') . ' 2>&1', 300);
        }
    }

    protected function manifest(string $reason): array
    {
        return [
            'app' => 'cang-ai',
            'reason' => $reason,
            'version' => trim(shell_exec('git describe --tags --abbrev=0 2>/dev/null') ?: ''),
            'commit' => trim(shell_exec("git log -1 --format='%h %s' 2>/dev/null") ?: ''),
            'created_at' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'connection' => config('database.default'),
                'driver' => config('database.connections.' . config('database.default') . '.driver'),
                'database' => config('database.connections.' . config('database.default') . '.database'),
            ],
        ];
    }

    protected function fileInfo(string $path): array
    {
        $manifest = $this->readManifest($path);

        return [
            'filename' => basename($path),
            'path' => $path,
            'size' => is_file($path) ? filesize($path) : 0,
            'size_human' => is_file($path) ? $this->humanSize(filesize($path)) : '0B',
            'created_at' => is_file($path) ? date('Y-m-d H:i:s', filemtime($path)) : '',
            'version' => $manifest['version'] ?? '',
            'commit' => $manifest['commit'] ?? '',
            'reason' => $manifest['reason'] ?? '',
            'remote' => $manifest['remote'] ?? null,
        ];
    }

    protected function uploadRemoteBackup(string $path, string $filename): ?array
    {
        $profiles = app(StorageProfileService::class);
        if (!$profiles->isCloud(StorageProfileService::PURPOSE_BACKUP)) {
            return null;
        }

        $key = 'system-backups/' . date('Ymd') . '/' . $filename;
        $manifest = $this->readManifest($path);
        $manifest['remote'] = [
            'key' => $key,
            'driver' => $profiles->driverForPurpose(StorageProfileService::PURPOSE_BACKUP),
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $uploadPath = $this->copyWithManifest($path, $manifest);

        $disk = $profiles->disk(StorageProfileService::PURPOSE_BACKUP);
        $stream = fopen($uploadPath, 'rb');
        if ($stream === false) {
            @unlink($uploadPath);
            throw new RuntimeException('无法读取本地备份文件用于远端同步');
        }

        try {
            $disk->put($key, $stream, ['ContentType' => 'application/gzip', 'visibility' => 'private']);
            fclose($stream);
            $stream = null;

            if (!rename($uploadPath, $path)) {
                throw new RuntimeException('远端备份已上传，但本地备份清单更新失败');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($uploadPath)) {
                @unlink($uploadPath);
            }
        }

        return $manifest['remote'];
    }

    protected function readManifest(string $archive): array
    {
        if (!is_file($archive)) {
            return [];
        }

        foreach (['./manifest.json', 'manifest.json'] as $path) {
            $output = $this->run('tar -xOf ' . escapeshellarg($archive) . ' ' . escapeshellarg($path) . ' 2>/dev/null', 30, false);
            $json = json_decode($output, true);

            if (is_array($json)) {
                return $json;
            }
        }

        return [];
    }

    protected function rewriteManifest(string $archive, array $manifest): void
    {
        $rewritten = $this->copyWithManifest($archive, $manifest);
        if (!rename($rewritten, $archive)) {
            @unlink($rewritten);
            throw new RuntimeException('备份清单更新失败');
        }
    }

    protected function copyWithManifest(string $archive, array $manifest): string
    {
        $this->ensureCommandExists('tar');

        $workDir = sys_get_temp_dir() . '/cang-ai-backup-manifest-' . bin2hex(random_bytes(3));
        $target = sys_get_temp_dir() . '/cang-ai-backup-rewrite-' . bin2hex(random_bytes(3)) . '.tar.gz';
        $this->ensureDirectory($workDir);

        try {
            $this->run('tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg($workDir) . ' 2>&1', 300);
            file_put_contents($workDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->run('cd ' . escapeshellarg($workDir) . ' && tar -czf ' . escapeshellarg($target) . ' . 2>&1', 300);

            return $target;
        } finally {
            $this->run('rm -rf ' . escapeshellarg($workDir), 60, false);
        }
    }

    protected function assertArchiveIsSafe(string $archive): void
    {
        $listing = $this->run('tar -tzf ' . escapeshellarg($archive) . ' 2>&1', 120);
        foreach (explode("\n", $listing) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if ($this->isUnsafeArchivePath($entry)) {
                throw new RuntimeException('备份包包含非法路径: ' . $entry);
            }
        }
    }

    protected function isUnsafeArchivePath(string $entry): bool
    {
        if (str_starts_with($entry, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $entry)) {
            return true;
        }

        foreach (preg_split('/[\/\\\\]+/', $entry) ?: [] as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }

    protected function prune(): void
    {
        $keep = max(1, (int) config('system.backup_keep', 10));
        $files = glob($this->backupDir() . '/*.tar.gz') ?: [];
        rsort($files);

        foreach (array_slice($files, $keep) as $file) {
            @unlink($file);
        }
    }

    protected function backupDir(): string
    {
        $dir = rtrim((string) config('system.backup_dir'), '/');
        if ($dir === '' || $dir === '/' || !str_starts_with($dir, '/')) {
            throw new RuntimeException('备份目录配置无效，必须是绝对路径且不能是根目录');
        }

        return $dir;
    }

    protected function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("无法创建目录: {$dir}");
        }
    }

    protected function ensureCommandExists(string $command): void
    {
        $path = trim($this->run('command -v ' . escapeshellarg($command), 30, false));
        if ($path === '' || $path === '(无输出)') {
            throw new RuntimeException("服务器缺少命令: {$command}");
        }
    }

    protected function run(string $cmd, int $timeout = 60, bool $throwOnFailure = true): string
    {
        $timeoutBin = $this->timeoutBin();
        $fullCmd = ($timeoutBin ? escapeshellcmd($timeoutBin) . " {$timeout} " : '') . 'bash -lc ' . escapeshellarg($cmd);
        $lines = [];
        $exitCode = 0;
        exec($fullCmd, $lines, $exitCode);
        $output = trim(implode("\n", $lines));

        if ($throwOnFailure && $exitCode !== 0) {
            throw new RuntimeException("命令执行失败({$exitCode}): {$cmd}\n" . ($output ?: '(无输出)'));
        }

        return $output ?: '(无输出)';
    }

    protected function timeoutBin(): ?string
    {
        static $bin = false;

        if ($bin !== false) {
            return $bin ?: null;
        }

        $bin = trim((string) shell_exec('command -v timeout 2>/dev/null || command -v gtimeout 2>/dev/null'));

        return $bin ?: null;
    }

    protected function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, $unit === 'B' ? 0 : 1) . $unit;
            }
            $bytes /= 1024;
        }

        return $bytes . 'B';
    }
}
