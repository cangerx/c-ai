<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Http;

class VersionCheckService
{
    public function load(bool $force = false): array
    {
        $cacheFile = $this->cacheFile();

        if (!$force && is_file($cacheFile) && filemtime($cacheFile) >= time() - 600) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $appDir = base_path();
        $frontendDir = env('FRONTEND_DIR', dirname($appDir) . '/cang-ai-web');

        $info = [
            'backend' => $this->componentInfo('backend', '后端', $appDir, config('system.backend_repo')),
            'frontend' => is_dir($frontendDir)
                ? $this->componentInfo('frontend', '前端', $frontendDir, config('system.frontend_repo'))
                : [
                    'name' => '前端',
                    'installed' => false,
                    'current_version' => null,
                    'current_commit' => '未部署',
                    'current_date' => '',
                    'latest_version' => null,
                    'latest_url' => null,
                    'has_update' => false,
                    'status' => 'missing',
                    'message' => '前端目录不存在',
                ],
            'checked_at' => now()->toDateTimeString(),
        ];

        if (!is_dir(dirname($cacheFile))) {
            @mkdir(dirname($cacheFile), 0755, true);
        }
        @file_put_contents($cacheFile, json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $info;
    }

    public function markInstalled(string $component, array $release): void
    {
        $version = $release['latest_version'] ?? $release['name'] ?? null;
        if (!$version || !in_array($component, ['backend', 'frontend'], true)) {
            return;
        }

        $versions = $this->installedVersions();
        $versions[$component] = [
            'version' => $version,
            'commit' => $release['latest_commit'] ?? $release['commit'] ?? '',
            'installed_at' => now()->toDateTimeString(),
        ];

        $path = $this->installedVersionsFile();
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        @file_put_contents($path, json_encode($versions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @unlink($this->cacheFile());
    }

    protected function componentInfo(string $component, string $name, string $dir, string $repo): array
    {
        $installed = $this->installedVersions()[$component] ?? [];
        $currentVersion = $installed['version'] ?? ($this->shell($dir, "git describe --tags --abbrev=0 2>/dev/null") ?: null);
        $currentCommit = $installed['commit'] ?? ($this->shell($dir, "git log -1 --format='%h %s' 2>/dev/null") ?: '未知');
        $currentDate = $installed['installed_at'] ?? ($this->shell($dir, "git log -1 --format='%ci' 2>/dev/null") ?: '');
        $latest = $this->latestTag($repo);

        $hasUpdate = false;
        $status = 'unknown';
        $message = '无法检测最新版本';

        if ($latest) {
            $status = 'current';
            $message = '已是最新';
            if ($currentVersion && $this->isVersionGreater($latest['name'], $currentVersion)) {
                $hasUpdate = true;
                $status = 'update';
                $message = '发现新版本';
            } elseif (!$currentVersion) {
                $status = 'unknown';
                $message = '当前版本未打 tag';
            }
        }

        return [
            'name' => $name,
            'installed' => true,
            'current_version' => $currentVersion,
            'current_commit' => $currentCommit,
            'current_date' => $currentDate,
            'latest_version' => $latest['name'] ?? null,
            'latest_url' => $latest['zipball_url'] ?? null,
            'latest_commit' => $latest['commit'] ?? null,
            'has_update' => $hasUpdate,
            'status' => $status,
            'message' => $message,
        ];
    }

    protected function latestTag(string $repo): ?array
    {
        if (!$repo) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'cang-ai-upgrade'])
                ->get("https://api.github.com/repos/{$repo}/tags");

            if (!$response->successful()) {
                return null;
            }

            $latest = null;
            foreach ($response->json() ?: [] as $tag) {
                $name = (string) ($tag['name'] ?? '');
                if (preg_match('/^v?\d+\.\d+\.\d+$/', $name)) {
                    if ($latest && !$this->isVersionGreater($name, $latest['name'])) {
                        continue;
                    }

                    $latest = [
                        'name' => $name,
                        'zipball_url' => "https://github.com/{$repo}/archive/refs/tags/{$name}.zip",
                        'commit' => (string) ($tag['commit']['sha'] ?? ''),
                    ];
                }
            }

            return $latest;
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    protected function isVersionGreater(string $latest, string $current): bool
    {
        return version_compare(ltrim($latest, 'vV'), ltrim($current, 'vV'), '>');
    }

    protected function shell(string $dir, string $command): string
    {
        $dir = escapeshellarg($dir);
        return trim(shell_exec("cd {$dir} && {$command}") ?: '');
    }

    protected function installedVersions(): array
    {
        $path = $this->installedVersionsFile();
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    protected function installedVersionsFile(): string
    {
        return storage_path('app/private/system-installed-versions.json');
    }

    protected function cacheFile(): string
    {
        return storage_path('app/private/system-version-info.json');
    }
}
