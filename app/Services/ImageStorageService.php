<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageStorageService
{
    public function store(string $binary, string $mimeType, string $purpose = StorageProfileService::PURPOSE_GENERATED): string
    {
        $extension = $this->extensionFromMime($mimeType);
        $key = $this->buildKey($extension, $purpose);

        $disk = $this->disk($purpose);
        $disk->put($key, $binary, ['ContentType' => $mimeType, 'visibility' => 'public']);

        return $key;
    }

    public function url(string $key, string $purpose = StorageProfileService::PURPOSE_GENERATED): string
    {
        if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return $key;
        }

        return app(StorageProfileService::class)->publicUrl($purpose, $key);
    }

    public function delete(string $key, string $purpose = StorageProfileService::PURPOSE_GENERATED): void
    {
        $this->disk($purpose)->delete($key);
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
        if (!app(StorageProfileService::class)->isCloud(StorageProfileService::PURPOSE_UPLOAD)) {
            return null;
        }

        $extension = $this->extensionFromMime($mimeType);
        $key = $this->buildKey($extension, StorageProfileService::PURPOSE_UPLOAD);

        $config = app(StorageProfileService::class)->s3ConfigForPurpose(StorageProfileService::PURPOSE_UPLOAD);
        config(['filesystems.disks.dynamic_s3_upload_presign' => $config]);
        $client = Storage::disk('dynamic_s3_upload_presign')->getClient();
        $bucket = $config['bucket'] ?? '';

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
            'final_url' => $this->url($key, StorageProfileService::PURPOSE_UPLOAD),
            'headers' => ['Content-Type' => $mimeType, 'x-amz-acl' => 'public-read'],
        ];
    }

    protected function buildKey(string $extension, string $purpose = StorageProfileService::PURPOSE_GENERATED): string
    {
        $prefix = match ($purpose) {
            StorageProfileService::PURPOSE_UPLOAD => 'uploads',
            StorageProfileService::PURPOSE_DOWNLOAD => 'downloads',
            StorageProfileService::PURPOSE_BACKUP => 'backups',
            default => config('services.image_storage.prefix', 'images'),
        };
        $date = now()->format('Ymd');
        $hash = bin2hex(random_bytes(8));

        return "{$prefix}/{$date}/{$hash}.{$extension}";
    }

    protected function disk(string $purpose = StorageProfileService::PURPOSE_GENERATED): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return app(StorageProfileService::class)->disk($purpose);
    }

    /*
     * Kept for compatibility with older callers that may still normalize keys
     * from the default storage URL.
     */
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
