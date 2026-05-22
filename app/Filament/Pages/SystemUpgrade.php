<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SystemUpgrade extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = '系统升级';
    protected static ?string $title = '系统升级';
    protected static string | UnitEnum | null $navigationGroup = '系统';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.system-upgrade';

    public string $log = '';
    public bool $running = false;
    public array $versionInfo = [];

    public function mount(): void
    {
        $this->versionInfo = $this->loadVersionInfo();
    }

    protected function loadVersionInfo(): array
    {
        $appDir = base_path();
        $frontendDir = env('FRONTEND_DIR', dirname($appDir) . '/cang-ai-web');

        $backendCommit = trim(shell_exec("cd {$appDir} && git log -1 --format='%h %s' 2>/dev/null") ?: '未知');
        $backendDate = trim(shell_exec("cd {$appDir} && git log -1 --format='%ci' 2>/dev/null") ?: '');
        $frontendCommit = is_dir($frontendDir)
            ? trim(shell_exec("cd {$frontendDir} && git log -1 --format='%h %s' 2>/dev/null") ?: '未知')
            : '未部署';
        $frontendDate = is_dir($frontendDir)
            ? trim(shell_exec("cd {$frontendDir} && git log -1 --format='%ci' 2>/dev/null") ?: '')
            : '';

        $workerCount = trim(shell_exec("pgrep -f 'task:worker' 2>/dev/null | wc -l") ?: '0');
        $pm2 = $this->findPm2Bin();
        if ($pm2) {
            $pm2Online = trim(shell_exec("{$pm2} list 2>/dev/null | grep -c 'online'") ?: '0');
            $workerCount = $pm2Online;
        }

        return [
            'backend_commit' => $backendCommit,
            'backend_date' => $backendDate,
            'frontend_commit' => $frontendCommit,
            'frontend_date' => $frontendDate,
            'worker_count' => $workerCount,
        ];
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
            $this->upgradeBackendInternal();
            $this->upgradeFrontendInternal();
            $this->appendLog('');
            $this->appendLog('=== 全栈升级完成 ===');
            Notification::make()->title('全栈升级完成')->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 升级失败：' . $e->getMessage());
            Notification::make()->title('升级失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->versionInfo = $this->loadVersionInfo();
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
            $this->upgradeBackendInternal();
            Notification::make()->title('后端升级完成')->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 后端升级失败：' . $e->getMessage());
            Notification::make()->title('后端升级失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->versionInfo = $this->loadVersionInfo();
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
            $this->upgradeFrontendInternal();
            Notification::make()->title('前端升级完成')->success()->send();
        } catch (\Throwable $e) {
            $this->appendLog('✗ 前端升级失败：' . $e->getMessage());
            Notification::make()->title('前端升级失败')->body($e->getMessage())->danger()->send();
        } finally {
            $this->versionInfo = $this->loadVersionInfo();
            $this->running = false;
        }
    }

    protected function upgradeBackendInternal(): void
    {
        $appDir = base_path();
        $phpBin = $this->findPhpBin();

        $this->appendLog('--- 后端升级 ---');

        // 1. Git pull
        $this->appendLog('→ 拉取最新代码...');
        $result = $this->runShell("cd {$appDir} && git pull origin main 2>&1");
        $this->appendLog($result);

        // 2. Composer install
        $this->appendLog('→ 检查 PHP 依赖...');
        $composerBin = $this->findComposerBin();
        if ($phpBin && $composerBin) {
            $result = $this->runShell("cd {$appDir} && {$phpBin} {$composerBin} install --no-dev --optimize-autoloader --no-interaction 2>&1", 120);
            $this->appendLog($result);
        } else {
            $this->appendLog('⚠ PHP/Composer 路径未找到，跳过依赖安装');
        }

        // 3. Migrate
        $this->appendLog('→ 数据库迁移...');
        $result = $this->runShell("cd {$appDir} && {$phpBin} artisan migrate --force 2>&1");
        $this->appendLog($result);

        // 4. Clear caches now, rebuild after the Livewire response is sent
        $this->appendLog('→ 清除缓存...');
        $cacheCommands = [
            'config:clear', 'cache:clear', 'route:clear', 'view:clear', 'event:clear',
        ];
        foreach ($cacheCommands as $cmd) {
            $this->runShell("cd {$appDir} && {$phpBin} artisan {$cmd} 2>&1");
        }
        $this->appendLog('✓ 缓存已清除');

        if ($this->scheduleCacheRebuild($appDir, $phpBin)) {
            $this->appendLog('✓ 缓存将在页面响应后自动重建');
        } else {
            $this->appendLog('⚠ 缓存重建未能加入后台任务');
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
        $result = $this->runShell("cd {$appDir} && {$phpBin} artisan queue:restart 2>&1");
        $this->appendLog($result);

        $pm2 = $this->findPm2Bin();
        if ($pm2) {
            $this->runShell("{$pm2} restart cang-ai-worker 2>&1");
            $this->runShell("{$pm2} restart cang-ai-worker-2 2>&1");
            $this->appendLog('✓ PM2 Worker 已重启');
        }

        $this->appendLog('✓ 后端升级完成');
    }

    protected function upgradeFrontendInternal(): void
    {
        $appDir = base_path();
        $frontendDir = env('FRONTEND_DIR', dirname($appDir) . '/cang-ai-web');

        $this->appendLog('--- 前端升级 ---');

        if (!is_dir($frontendDir)) {
            $this->appendLog('⚠ 前端目录不存在: ' . $frontendDir);
            return;
        }

        // 1. Git pull
        $this->appendLog('→ 拉取前端代码...');
        $result = $this->runShell("cd {$frontendDir} && git pull origin main 2>&1");
        $this->appendLog($result);

        $npmBin = $this->findNpmBin();
        if (!$npmBin) {
            $this->appendLog('⚠ npm 未找到，跳过构建');
            return;
        }

        // 2. Install dependencies
        $this->appendLog('→ 安装前端依赖...');
        $result = $this->runShell("cd {$frontendDir} && {$npmBin} install 2>&1", 120);
        $this->appendLog($result);

        // 3. Build (next build with cleanDistDir:true will clean .next automatically)
        $this->appendLog('→ 构建前端（需要 1-3 分钟，期间前端短暂不可用）...');
        $result = $this->runShell("cd {$frontendDir} && NODE_OPTIONS='--max-old-space-size=1024' {$npmBin} run build 2>&1", 300);
        $this->appendLog($result);

        // 5. Verify build succeeded
        $standaloneDir = "{$frontendDir}/.next/standalone";
        if (!is_file("{$standaloneDir}/server.js")) {
            $this->appendLog('✗ 构建失败：standalone/server.js 不存在');
            Notification::make()->title('前端构建失败')->danger()->send();
            return;
        }
        $this->appendLog('✓ 构建成功');

        // 6. Deploy standalone assets (critical step!)
        $this->appendLog('→ 部署 standalone 资源...');

        // Copy static files into standalone
        $this->runShell("cp -r {$frontendDir}/.next/static {$standaloneDir}/.next/static 2>&1");
        $this->appendLog('  ✓ static 文件已复制');

        // Copy public files into standalone
        if (is_dir("{$frontendDir}/public")) {
            $this->runShell("cp -r {$frontendDir}/public {$standaloneDir}/public 2>&1");
            $this->appendLog('  ✓ public 文件已复制');
        }

        // Copy server.js to project root (PM2 start.sh uses root server.js)
        $this->runShell("cp {$standaloneDir}/server.js {$frontendDir}/server.js 2>&1");
        $this->appendLog('  ✓ server.js 已更新到项目根目录');

        // 7. Restart PM2
        $pm2 = $this->findPm2Bin();
        if ($pm2) {
            $this->appendLog('→ 重启前端服务...');
            $result = $this->runShell("{$pm2} restart cang-ai-web 2>&1");
            $this->appendLog($result);

            // Verify process is online
            sleep(2);
            $status = trim($this->runShell("{$pm2} show cang-ai-web 2>&1 | grep status"));
            $this->appendLog('  状态: ' . $status);
        }

        $this->appendLog('✓ 前端升级完成');
    }

    protected function runShell(string $cmd, int $timeout = 60): string
    {
        $fullCmd = "timeout {$timeout} {$cmd}";
        $output = shell_exec($fullCmd) ?? '';
        return trim($output) ?: '(无输出)';
    }

    protected function appendLog(string $line): void
    {
        $this->log .= $line . "\n";
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
            'config:cache',
            'route:cache',
            'view:cache',
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
