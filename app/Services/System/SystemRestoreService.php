<?php

namespace App\Services\System;

use RuntimeException;

class SystemRestoreService
{
    public function restore(string $archivePath, callable $logger = null): void
    {
        if (!is_file($archivePath)) {
            throw new RuntimeException('备份文件不存在');
        }

        $this->ensureCommandExists('tar');
        $workDir = sys_get_temp_dir() . '/cang-ai-restore-work-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $this->ensureDirectory($workDir);

        try {
            $this->log($logger, '→ 解压备份包...');
            $this->assertArchiveIsSafe($archivePath);
            $this->run('tar -xzf ' . escapeshellarg($archivePath) . ' -C ' . escapeshellarg($workDir) . ' 2>&1', 300);

            $manifest = $this->readManifest($workDir);
            if (($manifest['app'] ?? '') !== 'cang-ai') {
                throw new RuntimeException('备份包不是 cang-ai 备份');
            }

            $this->log($logger, '→ 恢复前创建安全备份...');
            app(SystemBackupService::class)->create('pre-restore');
            $this->log($logger, '✓ 安全备份已创建');

            $this->restoreDatabase($workDir, $logger);
            $this->restoreEnv($workDir, $logger);
            $this->restoreStorage($workDir, $logger);
            $this->postRestore($logger);
        } finally {
            $this->run('rm -rf ' . escapeshellarg($workDir), 60, false);
        }
    }

    protected function readManifest(string $workDir): array
    {
        $path = $workDir . '/manifest.json';
        if (!is_file($path)) {
            throw new RuntimeException('备份包缺少 manifest.json');
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        if (!is_array($manifest)) {
            throw new RuntimeException('manifest.json 格式无效');
        }

        return $manifest;
    }

    protected function restoreEnv(string $workDir, ?callable $logger): void
    {
        $env = $workDir . '/env/.env';
        if (!is_file($env)) {
            $this->log($logger, '⚠ 备份包没有 .env，跳过');
            return;
        }

        copy($env, base_path('.env'));
        $this->log($logger, '✓ .env 已恢复');
    }

    protected function restoreDatabase(string $workDir, ?callable $logger): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}", []);

        if (($config['driver'] ?? '') === 'sqlite') {
            $backup = $workDir . '/database/database.sqlite';
            $target = $config['database'] ?? '';
            if ($target && is_file($backup)) {
                $this->ensureDirectory(dirname($target));
                copy($backup, $target);
                $this->log($logger, '✓ SQLite 数据库已恢复');
            }
            return;
        }

        if (in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            $dump = $workDir . '/database/database.sql';
            if (!is_file($dump)) {
                $this->log($logger, '⚠ 备份包没有 database.sql，跳过数据库恢复');
                return;
            }

            $this->ensureCommandExists('mysql');
            $defaultsFile = $this->writeMysqlDefaultsFile($workDir, $config);
            $cmd = implode(' ', array_filter([
                'mysql',
                '--defaults-extra-file=' . escapeshellarg($defaultsFile),
                escapeshellarg((string) ($config['database'] ?? '')),
                '< ' . escapeshellarg($dump),
                '2>&1',
            ]));

            try {
                $this->run($cmd, 300);
            } finally {
                @unlink($defaultsFile);
            }
            $this->log($logger, '✓ MySQL 数据库已恢复');
            return;
        }

        throw new RuntimeException('暂不支持的数据库恢复类型: ' . ($config['driver'] ?? 'unknown'));
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

    protected function restoreStorage(string $workDir, ?callable $logger): void
    {
        $this->ensureCommandExists('rsync');

        foreach (['public', 'private'] as $diskDir) {
            $source = $workDir . "/storage/app/{$diskDir}";
            $target = storage_path("app/{$diskDir}");
            if (!is_dir($source)) {
                continue;
            }

            $this->ensureDirectory($target);
            $excludeArgs = '';
            if ($diskDir === 'private') {
                $excludeArgs = implode(' ', [
                    '--exclude=' . escapeshellarg('backups'),
                    '--exclude=' . escapeshellarg('frontend-backups'),
                    '--exclude=' . escapeshellarg('livewire-tmp'),
                    '--exclude=' . escapeshellarg('system-version-info.json'),
                    '--exclude=' . escapeshellarg('system-installed-versions.json'),
                    '--exclude=' . escapeshellarg('*.tmp'),
                ]);
            }

            $this->run('rsync -a --delete ' . $excludeArgs . ' ' . escapeshellarg($source . '/') . ' ' . escapeshellarg($target . '/') . ' 2>&1', 300);
            $this->log($logger, "✓ storage/app/{$diskDir} 已恢复");
        }
    }

    protected function assertArchiveIsSafe(string $archivePath): void
    {
        $listing = $this->run('tar -tzf ' . escapeshellarg($archivePath) . ' 2>&1', 120);
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

    protected function postRestore(?callable $logger): void
    {
        $php = PHP_BINARY;
        foreach (['storage:link', 'migrate --force', 'config:clear', 'cache:clear', 'route:clear', 'view:clear', 'queue:restart'] as $cmd) {
            $this->run('cd ' . escapeshellarg(base_path()) . ' && ' . escapeshellarg($php) . ' artisan ' . $cmd . ' 2>&1', 120, false);
        }
        $this->log($logger, '✓ 恢复后清理和迁移已执行');
    }

    protected function log(?callable $logger, string $line): void
    {
        if ($logger) {
            $logger($line);
        }
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
}
