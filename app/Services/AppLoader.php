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

        return $this->apps;
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
