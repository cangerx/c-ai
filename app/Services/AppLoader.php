<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class AppLoader
{
    protected array $apps = [];

    public function discover(): array
    {
        if (!empty($this->apps)) {
            return $this->apps;
        }

        $cachePath = base_path('bootstrap/cache/discovered_apps.php');
        if (file_exists($cachePath)) {
            try {
                $this->apps = require $cachePath;
                return $this->apps;
            } catch (\Throwable $e) {
                // Corrupted cache file, fallback to scanning
            }
        }

        $appsDir = app_path('Apps');
        if (!is_dir($appsDir)) {
            return [];
        }

        foreach (File::directories($appsDir) as $dir) {
            $manifestPath = $dir . '/manifest.json';
            if (!file_exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest || empty($manifest['name'])) {
                continue;
            }

            $manifest['path'] = $dir;
            $manifest['provider'] = $this->resolveProvider($dir, $manifest);
            $this->apps[$manifest['name']] = $manifest;
        }

        // Auto-compile cache in production
        if (app()->environment('production')) {
            try {
                $this->writeCacheFile($cachePath);
            } catch (\Throwable $e) {
                // Ignore if write permission fails
            }
        }

        return $this->apps;
    }

    public function writeCacheFile(string $path): void
    {
        $content = '<?php return ' . var_export($this->apps, true) . ';';
        file_put_contents($path, $content);
    }

    public function clearCacheFile(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function getMenuItems(): array
    {
        $items = [];
        foreach ($this->discover() as $app) {
            if (!empty($app['menu'])) {
                $items[] = [
                    'app' => $app['name'],
                    'title' => $app['title'] ?? $app['name'],
                    'icon' => $app['icon'] ?? 'box',
                    'items' => $app['menu'],
                ];
            }
        }
        return $items;
    }

    public function getProviders(): array
    {
        $providers = [];
        foreach ($this->discover() as $app) {
            if ($app['provider']) {
                $providers[] = $app['provider'];
            }
        }
        return $providers;
    }

    protected function resolveProvider(string $dir, array $manifest): ?string
    {
        $baseName = basename($dir);
        $class = "App\\Apps\\{$baseName}\\{$baseName}ServiceProvider";
        if (class_exists($class)) {
            return $class;
        }
        return null;
    }
}
