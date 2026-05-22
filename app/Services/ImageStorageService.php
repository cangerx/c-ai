<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageStorageService
{
    public function store(string $binary, string $mimeType): string
    {
        $extension = $this->extensionFromMime($mimeType);
        $key = $this->buildKey($extension);

        $disk = $this->disk();
        $disk->put($key, $binary, ['ContentType' => $mimeType, 'visibility' => 'public']);

        return $key;
    }

    public function url(string $key): string
    {
        if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return $key;
        }

        $driver = \App\Models\SiteSetting::get('storage_driver', 'local');

        if (in_array($driver, ['oss', 'cos', 'r2'])) {
            return $this->publicUrl($driver, $key);
        }

        // 本地存储：返回相对路径，避免 APP_URL 配置不一致导致图片无法加载
        return '/storage/' . ltrim($key, '/');
    }

    public function delete(string $key): void
    {
        $this->disk()->delete($key);
    }

    public function keyFromUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        if (preg_match('#^/storage/(.+)$#', $url, $m)) {
            return ltrim($m[1], '/');
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return ltrim($url, '/');
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $key = ltrim($path, '/');
        if ($key === '') {
            return null;
        }

        $customPath = trim(parse_url(\App\Models\SiteSetting::get('storage_url', ''), PHP_URL_PATH) ?: '', '/');
        if ($customPath !== '' && str_starts_with($key, $customPath . '/')) {
            $key = substr($key, strlen($customPath) + 1);
        }

        $bucket = \App\Models\SiteSetting::get('storage_bucket', '');
        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) {
            $key = substr($key, strlen($bucket) + 1);
        }

        return $key !== '' ? $key : null;
    }

    public function fetchRemoteImage(string $url): array
    {
        $resp = CurlClient::getRaw($url, ['Accept' => 'image/*'], 120, 15);

        if ($resp['status'] < 200 || $resp['status'] >= 300) {
            throw new RuntimeException("Failed to fetch image: HTTP {$resp['status']}");
        }

        $contentType = $resp['headers']['content-type'] ?? 'image/png';
        $mimeType = $this->normalizeMime(explode(';', $contentType)[0]);

        return [$resp['body'], $mimeType];
    }

    public function detectMimeFromBinary(string $binary): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = finfo_buffer($finfo, $binary);
        finfo_close($finfo);

        if ($detected) {
            return $this->normalizeMime($detected);
        }

        return 'image/png';
    }

    public function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
    }

    protected function normalizeMime(string $mime): string
    {
        $mime = strtolower(trim($mime));
        $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif'];

        if (!in_array($mime, $allowed, true)) {
            return 'image/png';
        }

        return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
    }

    public function generatePresign(string $mimeType): ?array
    {
        $driver = \App\Models\SiteSetting::get('storage_driver', 'local');
        if (!in_array($driver, ['oss', 'cos', 'r2'])) {
            return null;
        }

        $extension = $this->extensionFromMime($mimeType);
        $key = $this->buildKey($extension);
        $this->configureDynamicDisk($driver);

        $client = Storage::disk('dynamic_s3')->getClient();
        $bucket = \App\Models\SiteSetting::get('storage_bucket', '');

        $cmd = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
            'ACL' => 'public-read',
        ]);

        $presigned = $client->createPresignedRequest($cmd, '+5 minutes');
        $url = (string) $presigned->getUri();

        return [
            'direct' => true,
            'method' => 'PUT',
            'url' => $url,
            'key' => $key,
            'final_url' => $this->url($key),
            'headers' => ['Content-Type' => $mimeType, 'x-amz-acl' => 'public-read'],
        ];
    }

    protected function buildKey(string $extension): string
    {
        $prefix = config('services.image_storage.prefix', 'images');
        $date = now()->format('Ymd');
        $hash = bin2hex(random_bytes(8));

        return "{$prefix}/{$date}/{$hash}.{$extension}";
    }

    protected function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $driver = \App\Models\SiteSetting::get('storage_driver', 'local');

        if (in_array($driver, ['oss', 'cos', 'r2'])) {
            $this->configureDynamicDisk($driver);
            return Storage::disk('dynamic_s3');
        }

        $diskName = config('services.image_storage.disk', 'public');
        return Storage::disk($diskName);
    }

    protected function configureDynamicDisk(string $driver): void
    {
        $region = \App\Models\SiteSetting::get('storage_region', 'auto');
        $bucket = \App\Models\SiteSetting::get('storage_bucket', '');
        $endpoint = \App\Models\SiteSetting::get('storage_endpoint', '');
        $config = [
            'driver' => 's3',
            'key' => \App\Models\SiteSetting::get('storage_access_key', ''),
            'secret' => \App\Models\SiteSetting::get('storage_secret_key', ''),
            'region' => $region ?: 'auto',
            'bucket' => $bucket,
            'endpoint' => $this->apiEndpoint($driver, $endpoint, $bucket),
            'url' => \App\Models\SiteSetting::get('storage_url', ''),
            'use_path_style_endpoint' => $driver === 'r2',
            'throw' => true,
        ];

        config(['filesystems.disks.dynamic_s3' => $config]);
    }

    protected function publicUrl(string $driver, string $key): string
    {
        $customUrl = rtrim(\App\Models\SiteSetting::get('storage_url', ''), '/');
        if ($customUrl !== '') {
            return $customUrl . '/' . ltrim($key, '/');
        }

        $bucket = \App\Models\SiteSetting::get('storage_bucket', '');
        $endpoint = rtrim(\App\Models\SiteSetting::get('storage_endpoint', ''), '/');
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
