<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StorageProfileService
{
    public const PURPOSE_GENERATED = 'generated';
    public const PURPOSE_UPLOAD = 'upload';
    public const PURPOSE_DOWNLOAD = 'download';
    public const PURPOSE_BACKUP = 'backup';

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

    public function s3ConfigForPurpose(string $purpose): array
    {
        $profile = $this->profileForPurpose($purpose);
        $driver = $this->driver($profile);

        return $this->s3Config($profile, $driver);
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

    public function profileForPurpose(string $purpose): string
    {
        return match ($purpose) {
            self::PURPOSE_UPLOAD, self::PURPOSE_DOWNLOAD => $this->isConfigured('temp') ? 'temp' : 'default',
            self::PURPOSE_BACKUP => 'backup',
            default => 'default',
        };
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

        return in_array($driver, ['local', 'oss', 'cos', 'r2'], true) ? $driver : 'local';
    }

    protected function setting(string $profile, string $field): string
    {
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
