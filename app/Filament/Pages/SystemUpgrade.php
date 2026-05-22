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

        return [
            'backend_commit' => $backendCommit,
            'backend_date' => $backendDate,
            'frontend_commit' => $frontendCommit,
            'frontend_date' => $frontendDate,
        ];
    }

    /**
     * 一键升级全部
     */
    public function upgradeAll(): void
    {
        $this->log = '';
        $this->running = true;

        $this->appendLog('=== 开始全栈升级 ===');

        $this->upgradeBackendInternal();
        $this->upgradeFrontendInternal();

        $this->appendLog('');
        $this->appendLog('=== 全栈升级完成 ===');
        $this->versionInfo = $this->loadVersionInfo();
        $this->running = false;

        Notification::make()->title('全栈升级完成')->success()->send();
    }

    /**
     * 仅升级后端
     */
    public function upgradeBackend(): void
    {
        $this->log = '';
        $this->running = true;

        $this->upgradeBackendInternal();

        $this->versionInfo = $this->loadVersionInfo();
        $this->running = false;
        Notification::make()->title('后端升级完成')->success()->send();
    }

    /**
     * 仅升级前端
     */
    public function upgradeFrontend(): void
    {
        $this->log = '';
        $this->running = true;

        $this->upgradeFrontendInternal();

        $this->versionInfo = $this->loadVersionInfo();
        $this->running = false;
        Notification::make()->title('前端升级完成')->success()->send();
    }

    protected function upgradeBackendInternal(): void
    {
        $appDir = base_path();

        $this->appendLog('--- 后端升级 ---');

        // 1. Git pull
        $this->appendLog('→ 拉取最新代码...');
        $result = $this->runShell("cd {$appDir} && git pull origin main 2>&1");
        $this->appendLog($result);

        // 2. Composer install (if needed)
        $this->appendLog('→ 检查 PHP 依赖...');
        $phpBin = $this->findPhpBin();
        $composerBin = $this->findComposerBin();
        if ($phpBin && $composerBin) {
            $result = $this->runShell("{$phpBin} {$composerBin} install --no-dev --optimize-autoloader --no-interaction 2>&1", 120);
            $this->appendLog($result);
        } else {
            $this->appendLog('⚠ PHP/Composer 路径未找到，跳过依赖安装');
        }

        // 3. Migrate
        $this->appendLog('→ 数据库迁移...');
        $result = $this->runShell("cd {$appDir} && {$phpBin} artisan migrate --force 2>&1");
        $this->appendLog($result);

        // 4. Clear cache
        $this->appendLog('→ 清除缓存...');
        $this->runShell("cd {$appDir} && {$phpBin} artisan config:clear 2>&1");
        $this->runShell("cd {$appDir} && {$phpBin} artisan cache:clear 2>&1");
        $this->runShell("cd {$appDir} && {$phpBin} artisan route:clear 2>&1");
        $this->runShell("cd {$appDir} && {$phpBin} artisan view:clear 2>&1");
        $this->appendLog('✓ 缓存已清除');

        // 5. Restart workers
        $this->appendLog('→ 重启 Worker...');
        $result = $this->runShell("cd {$appDir} && {$phpBin} artisan queue:restart 2>&1");
        $this->appendLog($result);

        // Try PM2 restart
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

        // 2. npm install + build
        $nodeBin = $this->findNodeBin();
        $npmBin = $this->findNpmBin();

        if (!$npmBin) {
            $this->appendLog('⚠ npm 未找到，跳过构建');
            return;
        }

        $this->appendLog('→ 安装前端依赖...');
        $result = $this->runShell("cd {$frontendDir} && {$npmBin} install 2>&1", 120);
        $this->appendLog($result);

        $this->appendLog('→ 构建前端（需要 1-3 分钟）...');
        $result = $this->runShell("cd {$frontendDir} && NODE_OPTIONS='--max-old-space-size=1024' {$npmBin} run build 2>&1", 300);
        $this->appendLog($result);

        // 3. Restart PM2
        $pm2 = $this->findPm2Bin();
        if ($pm2) {
            $this->appendLog('→ 重启前端服务...');
            $result = $this->runShell("{$pm2} restart cang-ai-web 2>&1");
            $this->appendLog($result);
            $this->appendLog('✓ 前端服务已重启');
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
