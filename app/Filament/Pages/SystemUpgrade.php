<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Services\System\SystemBackupService;
use App\Services\System\SystemRestoreService;
use App\Services\System\VersionCheckService;
use App\Services\StorageProfileService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Livewire\WithFileUploads;

class SystemUpgrade extends Page
{
    use WithFileUploads;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = '系统升级';
    protected static ?string $title = '系统升级';
    protected static string | UnitEnum | null $navigationGroup = '系统';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.system-upgrade';

    public string $log = '';
    public bool $running = false;
    public array $versionInfo = [];
    public array $backups = [];
    public array $remoteBackups = [];
    public mixed $backupUpload = null;
    public string $backupImportPath = '';

    public function mount(): void
    {
        $this->refreshState();
    }

    protected function refreshState(?VersionCheckService $versions = null, ?SystemBackupService $backups = null, bool $forceVersions = false): void
    {
        $versions ??= app(VersionCheckService::class);
        $backups ??= app(SystemBackupService::class);
        $info = $versions->load($forceVersions);
        $workerCount = trim(shell_exec("pgrep -f 'task:worker' 2>/dev/null | wc -l") ?: '0');
        $pm2 = $this->findPm2Bin();
        if ($pm2) {
            $pm2Online = trim(shell_exec("{$pm2} list 2>/dev/null | grep -c 'online'") ?: '0');
            $workerCount = $pm2Online;
        }

        $info['worker_count'] = $workerCount;
        $this->versionInfo = $info;
        $this->backups = $backups->list();
        try {
            $this->remoteBackups = $backups->listRemote();
        } catch (\Throwable) {
            $this->remoteBackups = [];
        }
    }

    public function refreshVersions(): void
    {
        $this->refreshState(forceVersions: true);
        Notification::make()->title('版本检测已刷新')->success()->send();
    }

    public function createBackup(): void
    {
        $backups = app(SystemBackupService::class);
        $this->log = '';
        $this->running = true;

        try {
            $this->appendLog('=== 开始系统备份 ===');
            $backup = $backups->create('manual');
            $this->appendLog("✓ 备份完成: {$backup['filename']} ({$backup['size_human']})");
            $this->appendRemoteBackupLog($backup);
            $this->refreshState(backups: $backups);
            Notification::make()->title('备份完成')->body($backup['filename'])->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 备份失败：' . $e->getMessage());
            Notification::make()->title('备份失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->running = false;
        }
    }

    public function deleteBackup(string $filename): void
    {
        $backups = app(SystemBackupService::class);

        try {
            $backups->delete($filename);
            $this->refreshState(backups: $backups);
            Notification::make()->title('备份已删除')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('删除失败')->body($e->getMessage())->danger()->send();
        }
    }

    public function restoreBackup(string $filename): void
    {
        $backups = app(SystemBackupService::class);
        $restore = app(SystemRestoreService::class);
        $this->log = '';
        $this->running = true;

        try {
            $this->appendLog('=== 开始恢复备份 ===');
            $this->appendLog('→ 备份文件: ' . $filename);
            $restore->restore($backups->pathFor($filename), fn (string $line) => $this->appendLog($line));
            $this->appendLog('✓ 备份恢复完成');
            $this->refreshState(backups: $backups);
            Notification::make()->title('恢复完成')->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 恢复失败：' . $e->getMessage());
            Notification::make()->title('恢复失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->running = false;
        }
    }

    public function importBackup(): void
    {
        $this->log = '';
        $this->running = true;

        try {
            $this->appendLog('=== 开始导入备份 ===');

            if ($this->backupUpload) {
                $this->validate([
                    'backupUpload' => ['required', 'file', 'max:' . $this->maxUploadKilobytes()],
                ]);

                $backup = app(SystemBackupService::class)->import(
                    $this->backupUpload->getRealPath(),
                    $this->backupUpload->getClientOriginalName(),
                );
            } else {
                $path = trim($this->backupImportPath);
                if ($path === '') {
                    throw new \RuntimeException('请选择上传文件，或填写服务器备份包路径');
                }

                $backup = app(SystemBackupService::class)->import($path, basename($path));
            }

            $this->appendLog("✓ 备份导入完成: {$backup['filename']} ({$backup['size_human']})");
            $this->backupUpload = null;
            $this->backupImportPath = '';
            $this->refreshState(backups: app(SystemBackupService::class));
            Notification::make()->title('备份导入完成')->body($backup['filename'])->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 导入失败：' . $e->getMessage());
            Notification::make()->title('导入失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->running = false;
        }
    }

    public function refreshRemoteBackups(): void
    {
        try {
            $this->remoteBackups = app(SystemBackupService::class)->listRemote();
            Notification::make()->title('远端备份已刷新')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('刷新远端备份失败')->body($e->getMessage())->danger()->send();
        }
    }

    public function importRemoteBackup(string $key): void
    {
        $this->log = '';
        $this->running = true;

        try {
            $this->appendLog('=== 开始拉取远端备份 ===');
            $this->appendLog('→ 远端 Key: ' . $key);
            $backup = app(SystemBackupService::class)->importRemote($key);
            $this->appendLog("✓ 远端备份已导入本地: {$backup['filename']} ({$backup['size_human']})");
            $this->refreshState(backups: app(SystemBackupService::class));
            Notification::make()->title('远端备份已导入')->body($backup['filename'])->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 远端备份导入失败：' . $e->getMessage());
            Notification::make()->title('远端备份导入失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->running = false;
        }
    }

    /**
     * 一键升级全部
     */
    public function upgradeAll(): void
    {
        $this->log = '';
        $this->running = true;

        try {
            $this->appendLog('=== 开始全栈升级 ===');
            $this->prepareRuntimeForUpgrade();
            $this->createPreUpgradeBackup();
            $this->upgradeBackendInternal();
            $this->upgradeFrontendInternal();
            $this->appendLog('');
            $this->appendLog('=== 全栈升级完成 ===');
            Notification::make()->title('全栈升级完成')->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 升级失败：' . $e->getMessage());
            Notification::make()->title('升级失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->refreshState();
            $this->running = false;
        }
    }

    /**
     * 仅升级后端
     */
    public function upgradeBackend(): void
    {
        $this->log = '';
        $this->running = true;

        try {
            $this->prepareRuntimeForUpgrade();
            $this->createPreUpgradeBackup();
            $this->upgradeBackendInternal();
            Notification::make()->title('后端升级完成')->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 后端升级失败：' . $e->getMessage());
            Notification::make()->title('后端升级失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->refreshState();
            $this->running = false;
        }
    }

    /**
     * 仅升级前端
     */
    public function upgradeFrontend(): void
    {
        $this->log = '';
        $this->running = true;

        try {
            $this->prepareRuntimeForUpgrade();
            $this->upgradeFrontendInternal();
            Notification::make()->title('前端升级完成')->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 前端升级失败：' . $e->getMessage());
            Notification::make()->title('前端升级失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->refreshState();
            $this->running = false;
        }
    }

    protected function createPreUpgradeBackup(): void
    {
        try {
            $this->appendLog('→ 升级前自动备份...');
            $backup = app(SystemBackupService::class)->create('pre-upgrade');
            $this->appendLog("✓ 自动备份完成: {$backup['filename']} ({$backup['size_human']})");
            $this->appendRemoteBackupLog($backup);
        } catch (\Throwable $e) {
            $this->appendLog('✗ 自动备份失败：' . $e->getMessage());
            if (!$this->allowUpgradeWithoutBackup()) {
                throw $e;
            }
            $this->appendLog('⚠ 已配置允许无备份升级，继续执行');
        }
    }

    protected function prepareRuntimeForUpgrade(): void
    {
        $appDir = base_path();
        $phpBin = $this->findPhpBin();
        if (!$phpBin) {
            return;
        }

        $this->appendLog('→ 刷新升级运行缓存...');
        $result = $this->runShell(
            'cd ' . escapeshellarg($appDir) . ' && ' . escapeshellarg($phpBin) . ' artisan optimize:clear 2>&1',
            60,
            false,
        );
        $this->appendLog($result);
    }

    protected function allowUpgradeWithoutBackup(): bool
    {
        if (config('system.allow_upgrade_without_backup')) {
            return true;
        }

        $value = strtolower(trim((string) $this->readEnvValue('UPGRADE_ALLOW_WITHOUT_BACKUP')));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    protected function readEnvValue(string $key): ?string
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            if ($name !== $key) {
                continue;
            }

            return trim($value, " \t\n\r\0\x0B\"'");
        }

        return null;
    }

    protected function upgradeBackendInternal(): void
    {
        $appDir = base_path();
        $phpBin = $this->findPhpBin();
        $release = $this->versionInfo['backend'] ?? [];

        $this->appendLog('--- 后端升级 ---');

        $this->appendLog('→ 当前目录: ' . $appDir);
        $this->appendLog('→ 升级来源: ' . $this->backendReleaseUrl());
        $this->downloadAndOverlayRelease($this->backendReleaseUrl(), $appDir, 'c-ai', [
            '.env',
            '.git',
            'storage',
            'bootstrap/cache',
            'vendor',
            'public/storage',
        ]);

        // 2. Composer install
        $this->appendLog('→ 检查 PHP 依赖...');
        $composerBin = $this->findComposerBin();
        if ($phpBin && $composerBin) {
            $result = $this->runShell(
                'cd ' . escapeshellarg($appDir) . ' && ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($composerBin) . ' install --no-dev --optimize-autoloader --no-interaction 2>&1',
                120,
            );
            $this->appendLog($result);
        } else {
            $this->appendLog('⚠ PHP/Composer 路径未找到，跳过依赖安装');
        }

        // 3. Migrate
        $this->appendLog('→ 数据库迁移...');
        $result = $this->runShell('cd ' . escapeshellarg($appDir) . ' && ' . escapeshellarg($phpBin) . ' artisan migrate --force 2>&1');
        $this->appendLog($result);

        // 4. Clear caches now, rebuild after the Livewire response is sent
        $this->appendLog('→ 清除缓存...');
        $this->runShell('cd ' . escapeshellarg($appDir) . ' && ' . escapeshellarg($phpBin) . ' artisan app:optimize --clear 2>&1', 60, false);
        $this->appendLog('✓ 缓存已清除');

        if ($this->scheduleCacheRebuild($appDir, $phpBin)) {
            $this->appendLog('✓ 缓存与系统优化将在页面响应后自动重建');
        } else {
            $this->appendLog('⚠ 缓存与系统优化重建未能加入后台任务');
        }

        // 5. Restart PHP-FPM (critical: ensures new code is loaded)
        $this->appendLog('→ 安排 PHP-FPM 延迟重启...');
        if ($this->schedulePhpFpmRestart()) {
            $this->appendLog('✓ PHP-FPM 将在页面响应后自动重启');
        } else {
            $this->appendLog('⚠ PHP-FPM 重启命令未找到，已跳过');
        }

        // 6. Restart workers
        $this->appendLog('→ 重启 Worker...');
        $result = $this->runShell('cd ' . escapeshellarg($appDir) . ' && ' . escapeshellarg($phpBin) . ' artisan queue:restart 2>&1');
        $this->appendLog($result);

        $pm2 = $this->findPm2Bin();
        if ($pm2) {
            $this->runShell(escapeshellarg($pm2) . ' restart cang-ai-worker 2>&1', 60, false);
            $this->runShell(escapeshellarg($pm2) . ' restart cang-ai-worker-2 2>&1', 60, false);
            $this->appendLog('✓ PM2 Worker 已重启');
        }

        app(VersionCheckService::class)->markInstalled('backend', $release);
        $this->appendLog('✓ 后端升级完成');
    }

    protected function upgradeFrontendInternal(): void
    {
        $appDir = base_path();
        $frontendDir = env('FRONTEND_DIR', dirname($appDir) . '/cang-ai-web');
        $release = $this->versionInfo['frontend'] ?? [];

        $this->appendLog('--- 前端升级 ---');

        if (!is_dir($frontendDir)) {
            $this->appendLog('⚠ 前端目录不存在: ' . $frontendDir);
            return;
        }

        $this->backupFrontendDirectory($frontendDir);

        $this->appendLog('→ 当前目录: ' . $frontendDir);
        $this->appendLog('→ 升级来源: ' . $this->frontendReleaseUrl());
        $this->downloadAndOverlayRelease($this->frontendReleaseUrl(), $frontendDir, 'cang-ai-web', [
            '.env',
            '.env.local',
            '.env.production',
            '.git',
            'node_modules',
            '.next',
            'server.js',
        ]);

        $npmBin = $this->findNpmBin();
        if (!$npmBin) {
            $this->appendLog('⚠ npm 未找到，跳过构建');
            return;
        }

        // 2. Install dependencies
        $this->appendLog('→ 安装前端依赖...');
        $result = $this->runShell('cd ' . escapeshellarg($frontendDir) . ' && ' . escapeshellarg($npmBin) . ' install 2>&1', 120);
        $this->appendLog($result);

        // 3. Build (next build with cleanDistDir:true will clean .next automatically)
        $this->appendLog('→ 构建前端（需要 1-3 分钟，期间前端短暂不可用）...');
        $result = $this->runShell('cd ' . escapeshellarg($frontendDir) . ' && NODE_OPTIONS=' . escapeshellarg('--max-old-space-size=1024') . ' ' . escapeshellarg($npmBin) . ' run build 2>&1', 300);
        $this->appendLog($result);

        // 5. Verify build succeeded
        $standaloneDir = "{$frontendDir}/.next/standalone";
        if (!is_file("{$standaloneDir}/server.js")) {
            throw new \RuntimeException('前端构建失败：standalone/server.js 不存在');
        }
        $this->appendLog('✓ 构建成功');

        // 6. Deploy standalone assets (critical step!)
        $this->appendLog('→ 部署 standalone 资源...');

        // Copy static files into standalone
        $this->runShell('mkdir -p ' . escapeshellarg($standaloneDir . '/.next') . ' && rm -rf ' . escapeshellarg($standaloneDir . '/.next/static') . ' && cp -R ' . escapeshellarg($frontendDir . '/.next/static') . ' ' . escapeshellarg($standaloneDir . '/.next/static') . ' 2>&1');
        $this->appendLog('  ✓ static 文件已复制');

        // Copy public files into standalone
        if (is_dir("{$frontendDir}/public")) {
            $this->runShell('rm -rf ' . escapeshellarg($standaloneDir . '/public') . ' && cp -R ' . escapeshellarg($frontendDir . '/public') . ' ' . escapeshellarg($standaloneDir . '/public') . ' 2>&1');
            $this->appendLog('  ✓ public 文件已复制');
        }

        $this->assertFrontendStaticAssetsReady($standaloneDir);

        // 7. Restart or start PM2
        $this->restartOrStartFrontend($frontendDir, $standaloneDir);

        app(VersionCheckService::class)->markInstalled('frontend', $release);
        $this->appendLog('✓ 前端升级完成');
    }

    protected function backupFrontendDirectory(string $frontendDir): void
    {
        $this->ensureCommandExists('tar');

        $backupDir = storage_path('app/private/frontend-backups');
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            throw new \RuntimeException('无法创建前端备份目录: ' . $backupDir);
        }

        $filename = 'cang-ai-frontend-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.tar.gz';
        $path = $backupDir . '/' . $filename;
        $excludes = [
            '--exclude=' . escapeshellarg('node_modules'),
            '--exclude=' . escapeshellarg('.next/cache'),
        ];
        $cmd = 'cd ' . escapeshellarg(dirname($frontendDir)) .
            ' && tar -czf ' . escapeshellarg($path) . ' ' .
            implode(' ', $excludes) . ' ' .
            escapeshellarg(basename($frontendDir)) . ' 2>&1';

        $this->appendLog('→ 前端目录安全备份...');
        $this->runShell($cmd, 300);
        $this->uploadBackupArchiveToRemote($path, 'frontend-backups/' . date('Ymd') . '/' . basename($path));
        $this->pruneFrontendBackups($backupDir);
        $this->appendLog('✓ 前端目录备份完成: ' . basename($path));
    }

    protected function uploadBackupArchiveToRemote(string $path, string $key): void
    {
        $profiles = app(StorageProfileService::class);
        if (!$profiles->isCloud(StorageProfileService::PURPOSE_BACKUP)) {
            return;
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('无法读取备份文件用于远端同步');
        }

        try {
            $profiles
                ->disk(StorageProfileService::PURPOSE_BACKUP)
                ->put($key, $stream, ['ContentType' => 'application/gzip', 'visibility' => 'private']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->appendLog('  ✓ 已同步到远端备份存储: ' . $key);
    }

    protected function appendRemoteBackupLog(array $backup): void
    {
        if (!empty($backup['remote']['key'])) {
            $this->appendLog('  ✓ 远端备份: ' . strtoupper($backup['remote']['driver'] ?? 'cloud') . ' · ' . $backup['remote']['key']);
        } else {
            $this->appendLog('  远端备份: 未配置，已仅保留本地备份');
        }
    }

    protected function pruneFrontendBackups(string $backupDir): void
    {
        $keep = max(1, (int) config('system.frontend_backup_keep', 3));
        $files = glob(rtrim($backupDir, '/') . '/cang-ai-frontend-*.tar.gz') ?: [];
        rsort($files);

        foreach (array_slice($files, $keep) as $file) {
            @unlink($file);
        }
    }

    protected function assertFrontendStaticAssetsReady(string $standaloneDir): void
    {
        if (!is_dir($standaloneDir . '/.next/static/chunks')) {
            throw new \RuntimeException('前端构建失败：standalone 缺少 .next/static/chunks');
        }

        $chunks = glob($standaloneDir . '/.next/static/chunks/*.js') ?: [];
        if (empty($chunks)) {
            throw new \RuntimeException('前端构建失败：standalone 没有可用 chunk 文件');
        }

        $this->appendLog('  ✓ static chunks 校验通过');
    }

    protected function restartOrStartFrontend(string $frontendDir, string $standaloneDir): void
    {
        $pm2 = $this->findPm2Bin();
        $node = $this->findNodeBin();
        $processName = $this->frontendPm2Name();
        $port = $this->frontendPort();

        if (!$pm2) {
            $this->appendLog('⚠ pm2 未找到，前端已构建但未自动重启');
            $this->appendLog("  手动启动: cd {$standaloneDir} && PORT={$port} node server.js");
            return;
        }

        if (!$node) {
            $this->appendLog('⚠ node 未找到，前端已构建但未自动重启');
            return;
        }

        $this->writeFrontendStartScript($standaloneDir, $node, $port);

        $this->appendLog('→ 重建前端 PM2 服务...');
        if ($this->pm2ProcessExists($pm2, $processName)) {
            $result = $this->runShell(escapeshellarg($pm2) . ' delete ' . escapeshellarg($processName) . ' 2>&1', 60, false);
            $this->appendLog($result);
        }

        $result = $this->runShell(
            'PORT=' . escapeshellarg((string) $port) . ' ' .
            escapeshellarg($pm2) . ' start ' . escapeshellarg($standaloneDir . '/start.sh') .
            ' --name ' . escapeshellarg($processName) .
            ' --cwd ' . escapeshellarg($standaloneDir) . ' 2>&1',
            60,
        );
        $this->appendLog($result);

        $this->runShell(escapeshellarg($pm2) . ' save 2>&1', 60, false);

        sleep(2);
        $status = trim($this->runShell(escapeshellarg($pm2) . ' show ' . escapeshellarg($processName) . ' 2>&1 | grep status', 60, false));
        $this->appendLog('  状态: ' . ($status ?: '未获取到状态'));

        if (!$this->pm2ProcessOnline($pm2, $processName)) {
            throw new \RuntimeException("前端 PM2 进程 {$processName} 未处于 online 状态");
        }
    }

    protected function writeFrontendStartScript(string $frontendDir, string $node, int $port): void
    {
        $node = addcslashes($node, '"\\');
        $script = implode("\n", [
            '#!/bin/bash',
            'cd "$(dirname "$0")"',
            'export HOSTNAME="${HOSTNAME:-0.0.0.0}"',
            'export PORT="${PORT:-' . $port . '}"',
            'exec "' . $node . '" server.js',
        ]);

        file_put_contents(rtrim($frontendDir, '/') . '/start.sh', $script . "\n");
        @chmod(rtrim($frontendDir, '/') . '/start.sh', 0755);
    }

    protected function pm2ProcessExists(string $pm2, string $processName): bool
    {
        $list = $this->runShell(escapeshellarg($pm2) . ' jlist 2>/dev/null', 60, false);
        $processes = json_decode($list, true);

        if (is_array($processes)) {
            foreach ($processes as $process) {
                if (($process['name'] ?? null) === $processName) {
                    return true;
                }
            }
        }

        $plainList = $this->runShell(escapeshellarg($pm2) . ' list 2>/dev/null', 60, false);
        return str_contains($plainList, $processName);
    }

    protected function pm2ProcessOnline(string $pm2, string $processName): bool
    {
        $list = $this->runShell(escapeshellarg($pm2) . ' jlist 2>/dev/null', 60, false);
        $processes = json_decode($list, true);

        if (!is_array($processes)) {
            return false;
        }

        foreach ($processes as $process) {
            if (($process['name'] ?? null) === $processName) {
                return ($process['pm2_env']['status'] ?? null) === 'online';
            }
        }

        return false;
    }

    protected function frontendPm2Name(): string
    {
        return config('system.frontend_pm2_name', 'cang-ai-web');
    }

    protected function frontendPort(): int
    {
        return (int) config('system.frontend_port', 3000);
    }

    protected function maxUploadKilobytes(): int
    {
        return max(1, (int) config('system.backup_upload_max_mb', 512)) * 1024;
    }

    protected function runShell(string $cmd, int $timeout = 60, bool $throwOnFailure = true): string
    {
        $timeoutBin = $this->timeoutBin();
        $fullCmd = ($timeoutBin ? escapeshellcmd($timeoutBin) . " {$timeout} " : '') . 'bash -lc ' . escapeshellarg($cmd);
        $lines = [];
        $exitCode = 0;
        exec($fullCmd, $lines, $exitCode);
        $output = trim(implode("\n", $lines));

        if ($throwOnFailure && $exitCode !== 0) {
            throw new \RuntimeException("命令执行失败({$exitCode}): {$cmd}\n" . ($output ?: '(无输出)'));
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

    protected function appendLog(string $line): void
    {
        $this->log .= $line . "\n";
    }

    protected function backendReleaseUrl(): string
    {
        if (empty(env('BACKEND_RELEASE_ZIP_URL')) && !empty($this->versionInfo['backend']['latest_url'])) {
            return $this->versionInfo['backend']['latest_url'];
        }

        return env('BACKEND_RELEASE_ZIP_URL', 'https://github.com/cangerx/c-ai/archive/refs/heads/main.zip');
    }

    protected function frontendReleaseUrl(): string
    {
        if (empty(env('FRONTEND_RELEASE_ZIP_URL')) && !empty($this->versionInfo['frontend']['latest_url'])) {
            return $this->versionInfo['frontend']['latest_url'];
        }

        return env('FRONTEND_RELEASE_ZIP_URL', 'https://github.com/cangerx/cang-ai-web/archive/refs/heads/main.zip');
    }

    protected function downloadAndOverlayRelease(string $url, string $targetDir, string $projectHint, array $excludes): void
    {
        $this->ensureCommandExists('curl');
        $this->ensureCommandExists('unzip');
        $this->ensureCommandExists('rsync');

        $targetDir = rtrim($targetDir, '/');
        $workDir = sys_get_temp_dir() . '/cang-upgrade-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $zipFile = $workDir . '/release.zip';
        $extractDir = $workDir . '/extract';

        $this->runShell('mkdir -p ' . escapeshellarg($extractDir));

        try {
            $this->appendLog('→ 下载发版压缩包...');
            $this->runShell('curl -L --fail --connect-timeout 15 --max-time 180 -o ' . escapeshellarg($zipFile) . ' ' . escapeshellarg($url) . ' 2>&1', 210);

            $this->appendLog('→ 解压发版包...');
            $this->runShell('unzip -q ' . escapeshellarg($zipFile) . ' -d ' . escapeshellarg($extractDir) . ' 2>&1', 120);

            $sourceDir = $this->findExtractedProjectDir($extractDir, $projectHint);
            $this->appendLog('→ 覆盖代码: ' . $sourceDir);

            $excludeArgs = implode(' ', array_map(fn (string $item): string => '--exclude=' . escapeshellarg($item), $excludes));
            $cmd = 'rsync -a --delete ' . $excludeArgs . ' ' . escapeshellarg(rtrim($sourceDir, '/') . '/') . ' ' . escapeshellarg($targetDir . '/') . ' 2>&1';
            $this->runShell($cmd, 180);
            $this->appendLog('✓ 代码覆盖完成');
        } finally {
            $this->runShell('rm -rf ' . escapeshellarg($workDir), 60, false);
        }
    }

    protected function findExtractedProjectDir(string $extractDir, string $projectHint): string
    {
        $dirs = array_values(array_filter(glob(rtrim($extractDir, '/') . '/*') ?: [], 'is_dir'));
        if (empty($dirs)) {
            throw new \RuntimeException('发版包解压后没有找到项目目录');
        }

        foreach ($dirs as $dir) {
            if (str_contains(basename($dir), $projectHint)) {
                return $dir;
            }
        }

        return $dirs[0];
    }

    protected function ensureCommandExists(string $command): void
    {
        $path = trim($this->runShell('command -v ' . escapeshellarg($command), 30, false));
        if ($path === '' || $path === '(无输出)') {
            throw new \RuntimeException("服务器缺少命令: {$command}");
        }
    }

    protected function trustGitDirectory(string $directory): void
    {
        $path = realpath($directory) ?: $directory;
        $this->runShell('git config --global --add safe.directory ' . escapeshellarg($path) . ' 2>&1', 30, false);
    }

    protected function schedulePhpFpmRestart(): bool
    {
        $cmd = $this->phpFpmRestartCommand();
        if (!$cmd) {
            return false;
        }

        $output = shell_exec("(sleep 8; {$cmd}) >/dev/null 2>&1 & echo scheduled") ?: '';
        return trim($output) === 'scheduled';
    }

    protected function scheduleCacheRebuild(string $appDir, string $phpBin): bool
    {
        $commands = [
            'app:optimize',
        ];
        $artisanCommands = array_map(
            fn (string $command): string => escapeshellarg($phpBin) . ' artisan ' . escapeshellarg($command),
            $commands,
        );
        $cmd = 'cd ' . escapeshellarg($appDir) . ' && ' . implode(' && ', $artisanCommands);
        $output = shell_exec("(sleep 3; {$cmd}) >/dev/null 2>&1 & echo scheduled") ?: '';
        return trim($output) === 'scheduled';
    }

    protected function phpFpmRestartCommand(): ?string
    {
        foreach (['/etc/init.d/php-fpm-83', '/etc/init.d/php-fpm-84', '/etc/init.d/php-fpm-82'] as $initScript) {
            if (is_executable($initScript)) {
                return escapeshellarg($initScript) . ' restart';
            }
        }

        return 'systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true';
    }

    protected function findPhpBin(): string
    {
        foreach (['/www/server/php/83/bin/php', '/www/server/php/84/bin/php', '/www/server/php/82/bin/php', PHP_BINARY] as $p) {
            if (is_executable($p)) return $p;
        }
        return PHP_BINARY;
    }

    protected function findComposerBin(): string
    {
        foreach (['/usr/local/bin/composer', '/usr/bin/composer'] as $c) {
            if (is_executable($c)) return $c;
        }
        return trim(shell_exec('which composer 2>/dev/null') ?: '/usr/local/bin/composer');
    }

    protected function findNodeBin(): string
    {
        $candidates = glob('/www/server/nodejs/v*/bin/node');
        rsort($candidates);
        foreach (array_merge($candidates, ['/usr/local/bin/node', '/usr/bin/node']) as $n) {
            if (is_executable($n)) return $n;
        }
        return trim(shell_exec('which node 2>/dev/null') ?: '');
    }

    protected function findNpmBin(): string
    {
        $nodeBin = $this->findNodeBin();
        if ($nodeBin) {
            $npmBin = dirname($nodeBin) . '/npm';
            if (is_executable($npmBin)) return $npmBin;
        }
        foreach (['/usr/local/bin/npm', '/usr/bin/npm'] as $n) {
            if (is_executable($n)) return $n;
        }
        return trim(shell_exec('which npm 2>/dev/null') ?: '');
    }

    protected function findPm2Bin(): string
    {
        $nodeBin = $this->findNodeBin();
        if ($nodeBin) {
            $pm2 = dirname($nodeBin) . '/pm2';
            if (is_executable($pm2)) return $pm2;
            $pm2 = dirname(dirname($nodeBin)) . '/lib/node_modules/pm2/bin/pm2';
            if (is_executable($pm2)) return $pm2;
        }
        foreach (['/usr/local/bin/pm2'] as $p) {
            if (is_executable($p)) return $p;
        }
        return trim(shell_exec('which pm2 2>/dev/null') ?: '');
    }
}
