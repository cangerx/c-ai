<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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

        return $this->disk()->url($key);
    }

    public function delete(string $key): void
    {
        $this->disk()->delete($key);
    }

    public function fetchRemoteImage(string $url): array
    {
        $response = Http::timeout(120)->connectTimeout(15)->withHeaders(['Accept' => 'image/*'])->get($url);

        if (!$response->successful()) {
            throw new RuntimeException("Failed to fetch image: HTTP {$response->status()}");
        }

        $contentType = $response->header('Content-Type', 'image/png');
        $mimeType = $this->normalizeMime(explode(';', $contentType)[0]);

        return [$response->body(), $mimeType];
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

        if (in_array($driver, ['oss', 'r2'])) {
            $this->configureDynamicDisk($driver);
            return Storage::disk('dynamic_s3');
        }

        $diskName = config('services.image_storage.disk', 'public');
        return Storage::disk($diskName);
    }

    protected function configureDynamicDisk(string $driver): void
    {
        $region = \App\Models\SiteSetting::get('storage_region', 'auto');
        $config = [
            'driver' => 's3',
            'key' => \App\Models\SiteSetting::get('storage_access_key', ''),
            'secret' => \App\Models\SiteSetting::get('storage_secret_key', ''),
            'region' => $region ?: 'auto',
            'bucket' => \App\Models\SiteSetting::get('storage_bucket', ''),
            'endpoint' => \App\Models\SiteSetting::get('storage_endpoint', ''),
            'url' => \App\Models\SiteSetting::get('storage_url', ''),
            'use_path_style_endpoint' => $driver === 'oss',
            'throw' => true,
        ];

        config(['filesystems.disks.dynamic_s3' => $config]);
    }
}
