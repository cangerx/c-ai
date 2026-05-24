<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StorageProfileService
{
    public const PURPOSE_GENERATED = 'generated';
    public const PURPOSE_UPLOAD = 'upload';
    public const PURPOSE_DOWNLOAD = 'download';
    public const PURPOSE_BACKUP = 'backup';

    private const PROFILE_LABELS = [
        'default' => '默认长期存储',
        'temp' => '上传/下载临时存储',
        'backup' => '系统备份远端存储',
    ];

    private const PURPOSE_LABELS = [
        self::PURPOSE_GENERATED => '长期生成图片',
        self::PURPOSE_UPLOAD => '上传参考图',
        self::PURPOSE_DOWNLOAD => '下载临时图',
        self::PURPOSE_BACKUP => '系统备份',
    ];

    public function disk(string $purpose): Filesystem
    {
        $profile = $this->profileForPurpose($purpose);
        $driver = $this->driver($profile);

        if (!in_array($driver, ['oss', 'cos', 'r2'], true)) {
            return Storage::disk('public');
        }

        $diskName = 'dynamic_s3_' . $profile;
        config(["filesystems.disks.{$diskName}" => $this->s3Config($profile, $driver)]);

        return Storage::disk($diskName);
    }

    public function isCloud(string $purpose): bool
    {
        $profile = $this->profileForPurpose($purpose);

        return in_array($this->driver($profile), ['oss', 'cos', 'r2'], true)
            && $this->isConfigured($profile);
    }

    public function driverForPurpose(string $purpose): string
    {
        return $this->driver($this->profileForPurpose($purpose));
    }

    public function publicUrl(string $purpose, string $key): string
    {
        $profile = $this->profileForPurpose($purpose);
        $driver = $this->driver($profile);

        if (!in_array($driver, ['oss', 'cos', 'r2'], true)) {
            return '/storage/' . ltrim($key, '/');
        }

        $customUrl = rtrim($this->setting($profile, 'url'), '/');
        if ($customUrl !== '') {
            return $customUrl . '/' . ltrim($key, '/');
        }

        $bucket = $this->setting($profile, 'bucket');
        $endpoint = rtrim($this->setting($profile, 'endpoint'), '/');
        if ($endpoint === '') {
            return $key;
        }

        $parts = parse_url($endpoint);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = rtrim($parts['path'] ?? '', '/');

        if ($host === '') {
            return $endpoint . '/' . ltrim($key, '/');
        }

        if ($bucket !== '' && in_array($driver, ['oss', 'cos'], true) && !str_starts_with($host, $bucket . '.')) {
            $host = $bucket . '.' . $host;
        }

        return "{$scheme}://{$host}{$port}{$path}/" . ltrim($key, '/');
    }

    public function keyFromUrl(string $purpose, string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (preg_match('#^/storage/(.+)$#', $url, $m)) {
            return ltrim($m[1], '/');
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return ltrim($url, '/');
        }

        $profile = $this->profileForPurpose($purpose);
        $key = ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        if ($key === '') {
            return null;
        }

        foreach (['url', 'endpoint'] as $field) {
            $basePath = trim(parse_url($this->setting($profile, $field), PHP_URL_PATH) ?: '', '/');
            if ($basePath !== '' && str_starts_with($key, $basePath . '/')) {
                $key = substr($key, strlen($basePath) + 1);
                break;
            }
        }

        $bucket = $this->setting($profile, 'bucket');
        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) {
            $key = substr($key, strlen($bucket) + 1);
        }

        return $key !== '' ? $key : null;
    }

    public function s3ConfigForPurpose(string $purpose): array
    {
        $profile = $this->profileForPurpose($purpose);
        $driver = $this->driver($profile);

        return $this->s3Config($profile, $driver);
    }

    public function testWrite(string $purpose): void
    {
        $disk = $this->disk($purpose);
        $key = 'test/' . bin2hex(random_bytes(4)) . '.txt';

        $disk->put($key, 'connection-test');
        $disk->delete($key);
    }

    public function allowedHosts(array $profiles = ['default', 'temp']): array
    {
        $hosts = [];
        foreach (array_unique($profiles) as $profile) {
            $driver = $this->driver($profile);
            if (!in_array($driver, ['oss', 'cos', 'r2'], true)) {
                continue;
            }

            foreach (['url', 'endpoint'] as $field) {
                $value = $this->setting($profile, $field);
                $host = $value ? (parse_url($value, PHP_URL_HOST) ?: '') : '';
                if ($host !== '') {
                    $hosts[] = $host;
                }
            }

            $bucket = $this->setting($profile, 'bucket');
            $endpoint = $this->setting($profile, 'endpoint');
            $endpointHost = $endpoint ? (parse_url($endpoint, PHP_URL_HOST) ?: '') : '';
            if ($bucket !== '' && $endpointHost !== '' && !str_starts_with($endpointHost, $bucket . '.')) {
                $hosts[] = $bucket . '.' . $endpointHost;
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    public function diagnostics(): array
    {
        $activePurposes = [];
        foreach (array_keys(self::PURPOSE_LABELS) as $purpose) {
            $activePurposes[$purpose] = $this->profileForPurpose($purpose);
        }

        $profiles = [];
        foreach (array_keys(self::PROFILE_LABELS) as $profile) {
            $purposes = array_keys(array_filter($activePurposes, fn (string $activeProfile): bool => $activeProfile === $profile));
            $profiles[$profile] = $this->profileDiagnostics($profile, $purposes);
        }

        return [
            'purposes' => collect($activePurposes)
                ->map(fn (string $profile, string $purpose): array => [
                    'purpose' => $purpose,
                    'label' => self::PURPOSE_LABELS[$purpose] ?? $purpose,
                    'profile' => $profile,
                    'profile_label' => self::PROFILE_LABELS[$profile] ?? $profile,
                ])
                ->values()
                ->all(),
            'profiles' => $profiles,
        ];
    }

    public function profileDiagnostics(string $profile, array $purposes = []): array
    {
        $driver = $this->driver($profile);
        $isCloud = in_array($driver, ['oss', 'cos', 'r2'], true);
        $configured = $this->isConfigured($profile);

        return [
            'profile' => $profile,
            'label' => self::PROFILE_LABELS[$profile] ?? $profile,
            'driver' => $driver,
            'driver_label' => $this->driverLabel($driver),
            'is_cloud' => $isCloud,
            'configured' => $configured,
            'status' => $isCloud ? ($configured ? 'ready' : 'incomplete') : 'local',
            'bucket' => $this->setting($profile, 'bucket'),
            'endpoint' => $this->setting($profile, 'endpoint'),
            'url' => $this->setting($profile, 'url'),
            'region' => $this->setting($profile, 'region'),
            'has_secret' => $this->setting($profile, 'secret_key') !== '',
            'active_purposes' => array_values(array_map(
                fn (string $purpose): string => self::PURPOSE_LABELS[$purpose] ?? $purpose,
                $purposes,
            )),
        ];
    }

    public function profileForPurpose(string $purpose): string
    {
        return match ($purpose) {
            self::PURPOSE_UPLOAD, self::PURPOSE_DOWNLOAD => $this->routedProfile('temp'),
            self::PURPOSE_BACKUP => $this->routedProfile('backup'),
            default => 'default',
        };
    }

    protected function routedProfile(string $profile): string
    {
        $driver = $this->driver($profile);

        if ($driver === 'default' || ($profile === 'temp' && $driver === 'local')) {
            return 'default';
        }

        if ($profile === 'temp' && in_array($driver, ['oss', 'cos', 'r2'], true) && !$this->isConfigured($profile)) {
            Log::warning('storage temp profile incomplete, falling back to default profile', [
                'profile' => $profile,
                'driver' => $driver,
            ]);

            return 'default';
        }

        return $profile;
    }

    protected function isConfigured(string $profile): bool
    {
        return $this->driver($profile) !== 'local'
            && $this->setting($profile, 'bucket') !== ''
            && $this->setting($profile, 'endpoint') !== ''
            && $this->setting($profile, 'access_key') !== ''
            && $this->setting($profile, 'secret_key') !== '';
    }

    protected function s3Config(string $profile, string $driver): array
    {
        $missing = [];
        foreach (['access_key' => 'Access Key', 'secret_key' => 'Secret', 'bucket' => 'Bucket', 'endpoint' => 'Endpoint'] as $field => $label) {
            if ($this->setting($profile, $field) === '') {
                $missing[] = $label;
            }
        }
        if ($missing) {
            throw new RuntimeException('存储配置不完整，缺少：' . implode('、', $missing));
        }

        $bucket = $this->setting($profile, 'bucket');
        $endpoint = $this->setting($profile, 'endpoint');

        return [
            'driver' => 's3',
            'key' => $this->setting($profile, 'access_key'),
            'secret' => $this->setting($profile, 'secret_key'),
            'region' => $this->setting($profile, 'region') ?: 'auto',
            'bucket' => $bucket,
            'endpoint' => $this->apiEndpoint($driver, $endpoint, $bucket),
            'url' => $this->setting($profile, 'url'),
            'use_path_style_endpoint' => $driver === 'r2',
            'throw' => true,
        ];
    }

    protected function driver(string $profile): string
    {
        $driver = $this->setting($profile, 'driver') ?: 'local';

        if ($profile !== 'default' && $driver === 'default') {
            return 'default';
        }

        return in_array($driver, ['local', 'oss', 'cos', 'r2'], true) ? $driver : 'local';
    }

    protected function driverLabel(string $driver): string
    {
        return match ($driver) {
            'default' => '复用默认长期存储',
            'oss' => '阿里云 OSS',
            'cos' => '腾讯云 COS',
            'r2' => 'Cloudflare R2',
            default => '本地存储',
        };
    }

    protected function setting(string $profile, string $field): string
    {
        $driverKey = $profile === 'default' ? 'storage_driver' : "storage_{$profile}_driver";
        $driver = (string) SiteSetting::get($driverKey, 'local');
        
        if ($profile !== 'default' && $driver === 'default') {
            $driver = (string) SiteSetting::get('storage_driver', 'local');
        }

        if (in_array($driver, ['oss', 'cos', 'r2'], true)) {
            $specificKey = $profile === 'default' 
                ? "storage_{$driver}_{$field}" 
                : "storage_{$profile}_{$driver}_{$field}";
                
            $val = (string) SiteSetting::get($specificKey, '');
            if ($val !== '') {
                return $val;
            }
        }

        $key = $profile === 'default' ? "storage_{$field}" : "storage_{$profile}_{$field}";

        return (string) SiteSetting::get($key, '');
    }

    protected function apiEndpoint(string $driver, string $endpoint, string $bucket): string
    {
        if ($bucket === '' || !in_array($driver, ['oss', 'cos'], true)) {
            return $endpoint;
        }

        $parts = parse_url($endpoint);
        $host = $parts['host'] ?? '';
        if ($host === '' || !str_starts_with($host, $bucket . '.')) {
            return $endpoint;
        }

        $parts['host'] = substr($host, strlen($bucket) + 1);

        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';

        return "{$scheme}://{$parts['host']}{$port}{$path}";
    }
}
