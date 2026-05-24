<?php

namespace App\Console\Commands;

use App\Services\AppLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AppOptimizeCommand extends Command
{
    protected $signature = 'app:optimize {--clear : 清除所有优化缓存}';
    protected $description = '一键优化系统启动与请求速度（整合配置/路由/视图/Filament/动态应用缓存）';

    public function handle(AppLoader $loader): int
    {
        $cachePath = base_path('bootstrap/cache/discovered_apps.php');

        if ($this->option('clear')) {
            $this->info('正在清理系统优化缓存...');

            // 1. 清理动态应用缓存
            $loader->clearCacheFile($cachePath);
            $this->comment('✓ 已清理动态应用缓存');

            // 2. 清理 Laravel 核心缓存
            Artisan::call('config:clear', [], $this->getOutput());
            Artisan::call('route:clear', [], $this->getOutput());
            Artisan::call('view:clear', [], $this->getOutput());
            Artisan::call('event:clear', [], $this->getOutput());

            // 3. 清理 Filament 缓存
            try {
                Artisan::call('filament:optimize-clear', [], $this->getOutput());
            } catch (\Throwable $e) {
                // 如果未配置或不支持该指令，静默跳过
            }

            $this->info('系统缓存已全部清理！');
            return 0;
        }

        $this->info('正在生成系统优化缓存...');

        // 1. 生成动态应用缓存
        // 首先清除已有的缓存文件，以确保重新扫描
        $loader->clearCacheFile($cachePath);
        
        // 重新扫描并写入缓存文件
        $apps = $loader->discover();
        try {
            $loader->writeCacheFile($cachePath);
            $this->comment('✓ 已缓存动态应用 (共计 ' . count($apps) . ' 个)');
        } catch (\Throwable $e) {
            $this->error('缓存动态应用失败: ' . $e->getMessage());
        }

        // 2. 生成 Laravel 核心缓存
        $this->comment('正在生成 Laravel 核心缓存...');
        Artisan::call('config:cache', [], $this->getOutput());
        Artisan::call('route:cache', [], $this->getOutput());
        Artisan::call('view:cache', [], $this->getOutput());
        Artisan::call('event:cache', [], $this->getOutput());

        // 3. 生成 Filament 缓存
        try {
            $this->comment('正在生成 Filament 组件与图标缓存...');
            Artisan::call('filament:optimize', [], $this->getOutput());
        } catch (\Throwable $e) {
            // 如果未配置或不支持该指令，静默跳过
        }

        $this->info('系统优化缓存生成成功！');
        return 0;
    }
}
