<?php

namespace Tests\Unit;

use App\Services\ImageStorageService;
use App\Services\StorageProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// ImageStorageService 单元测试
class ImageStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImageStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new ImageStorageService();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    // extensionFromMime 返回正确的扩展名
    public function test_extension_from_mime_returns_correct_extension(): void
    {
        $this->assertEquals('jpg', $this->service->extensionFromMime('image/jpeg'));
        $this->assertEquals('webp', $this->service->extensionFromMime('image/webp'));
        $this->assertEquals('png', $this->service->extensionFromMime('image/png'));
        $this->assertEquals('png', $this->service->extensionFromMime('image/unknown'));
    }

    // store 方法将二进制数据保存到磁盘
    public function test_store_saves_binary_to_disk(): void
    {
        Storage::fake('public');

        $binary = random_bytes(100);
        $key = $this->service->store($binary, 'image/png');

        $this->assertNotEmpty($key);
        $this->assertStringEndsWith('.png', $key);
        Storage::disk('public')->assertExists($key);
    }

    public function test_storage_profiles_route_uploads_to_temp_when_configured(): void
    {
        \App\Models\SiteSetting::set('storage_driver', 'r2', 'storage');
        \App\Models\SiteSetting::set('storage_access_key', 'default-key', 'storage');
        \App\Models\SiteSetting::set('storage_secret_key', 'default-secret', 'storage');
        \App\Models\SiteSetting::set('storage_bucket', 'long-term', 'storage');
        \App\Models\SiteSetting::set('storage_endpoint', 'https://account.r2.cloudflarestorage.com', 'storage');
        \App\Models\SiteSetting::set('storage_region', 'auto', 'storage');

        \App\Models\SiteSetting::set('storage_temp_driver', 'oss', 'storage');
        \App\Models\SiteSetting::set('storage_temp_access_key', 'temp-key', 'storage');
        \App\Models\SiteSetting::set('storage_temp_secret_key', 'temp-secret', 'storage');
        \App\Models\SiteSetting::set('storage_temp_bucket', 'short-term', 'storage');
        \App\Models\SiteSetting::set('storage_temp_endpoint', 'https://oss-cn-hangzhou.aliyuncs.com', 'storage');
        \App\Models\SiteSetting::set('storage_temp_region', 'oss-cn-hangzhou', 'storage');

        $profiles = app(StorageProfileService::class);

        $this->assertSame('default', $profiles->profileForPurpose(StorageProfileService::PURPOSE_GENERATED));
        $this->assertSame('temp', $profiles->profileForPurpose(StorageProfileService::PURPOSE_UPLOAD));

        $generated = $profiles->s3ConfigForPurpose(StorageProfileService::PURPOSE_GENERATED);
        $upload = $profiles->s3ConfigForPurpose(StorageProfileService::PURPOSE_UPLOAD);

        $this->assertSame('long-term', $generated['bucket']);
        $this->assertSame('short-term', $upload['bucket']);
        $this->assertSame('https://oss-cn-hangzhou.aliyuncs.com', $upload['endpoint']);
        $this->assertContains('short-term.oss-cn-hangzhou.aliyuncs.com', $profiles->allowedHosts());
    }

    public function test_storage_diagnostics_reports_active_profiles(): void
    {
        \App\Models\SiteSetting::set('storage_driver', 'r2', 'storage');
        \App\Models\SiteSetting::set('storage_access_key', 'default-key', 'storage');
        \App\Models\SiteSetting::set('storage_secret_key', 'default-secret', 'storage');
        \App\Models\SiteSetting::set('storage_bucket', 'long-term', 'storage');
        \App\Models\SiteSetting::set('storage_endpoint', 'https://account.r2.cloudflarestorage.com', 'storage');
        \App\Models\SiteSetting::set('storage_temp_driver', 'local', 'storage');
        \App\Models\SiteSetting::set('storage_backup_driver', 'local', 'storage');

        $diagnostics = app(StorageProfileService::class)->diagnostics();

        $this->assertSame('default', $diagnostics['purposes'][0]['profile']);
        $this->assertSame('长期生成图片', $diagnostics['purposes'][0]['label']);
        $this->assertSame('ready', $diagnostics['profiles']['default']['status']);
        $this->assertContains('长期生成图片', $diagnostics['profiles']['default']['active_purposes']);
        $this->assertSame('local', $diagnostics['profiles']['backup']['status']);
    }

    public function test_key_from_url_respects_storage_purpose_profile(): void
    {
        \App\Models\SiteSetting::set('storage_driver', 'r2', 'storage');
        \App\Models\SiteSetting::set('storage_access_key', 'default-key', 'storage');
        \App\Models\SiteSetting::set('storage_secret_key', 'default-secret', 'storage');
        \App\Models\SiteSetting::set('storage_bucket', 'long-term', 'storage');
        \App\Models\SiteSetting::set('storage_endpoint', 'https://account.r2.cloudflarestorage.com', 'storage');
        \App\Models\SiteSetting::set('storage_url', 'https://cdn.example.com/media', 'storage');

        \App\Models\SiteSetting::set('storage_temp_driver', 'oss', 'storage');
        \App\Models\SiteSetting::set('storage_temp_access_key', 'temp-key', 'storage');
        \App\Models\SiteSetting::set('storage_temp_secret_key', 'temp-secret', 'storage');
        \App\Models\SiteSetting::set('storage_temp_bucket', 'short-term', 'storage');
        \App\Models\SiteSetting::set('storage_temp_endpoint', 'https://oss-cn-hangzhou.aliyuncs.com', 'storage');
        \App\Models\SiteSetting::set('storage_temp_url', 'https://tmp.example.com/temp-media', 'storage');

        $this->assertSame(
            'images/20260523/result.png',
            $this->service->keyFromUrl('https://cdn.example.com/media/images/20260523/result.png', StorageProfileService::PURPOSE_GENERATED),
        );

        $this->assertSame(
            'uploads/20260523/input.png',
            $this->service->keyFromUrl('https://tmp.example.com/temp-media/uploads/20260523/input.png', StorageProfileService::PURPOSE_UPLOAD),
        );
    }

    public function test_generated_public_url_uses_bucket_endpoint_when_no_cdn_url(): void
    {
        \App\Models\SiteSetting::set('storage_driver', 'oss', 'storage');
        \App\Models\SiteSetting::set('storage_access_key', 'default-key', 'storage');
        \App\Models\SiteSetting::set('storage_secret_key', 'default-secret', 'storage');
        \App\Models\SiteSetting::set('storage_bucket', 'long-term', 'storage');
        \App\Models\SiteSetting::set('storage_endpoint', 'https://oss-cn-hangzhou.aliyuncs.com', 'storage');

        $url = app(StorageProfileService::class)->publicUrl(
            StorageProfileService::PURPOSE_GENERATED,
            'images/20260523/result.png',
        );

        $this->assertSame('https://long-term.oss-cn-hangzhou.aliyuncs.com/images/20260523/result.png', $url);
    }

    public function test_backup_profile_does_not_fall_back_to_default_storage(): void
    {
        \App\Models\SiteSetting::set('storage_driver', 'r2', 'storage');
        \App\Models\SiteSetting::set('storage_access_key', 'default-key', 'storage');
        \App\Models\SiteSetting::set('storage_secret_key', 'default-secret', 'storage');
        \App\Models\SiteSetting::set('storage_bucket', 'long-term', 'storage');
        \App\Models\SiteSetting::set('storage_endpoint', 'https://account.r2.cloudflarestorage.com', 'storage');

        $profiles = app(StorageProfileService::class);

        $this->assertSame('backup', $profiles->profileForPurpose(StorageProfileService::PURPOSE_BACKUP));
        $this->assertFalse($profiles->isCloud(StorageProfileService::PURPOSE_BACKUP));

        \App\Models\SiteSetting::set('storage_backup_driver', 'r2', 'storage');
        \App\Models\SiteSetting::set('storage_backup_access_key', 'backup-key', 'storage');
        \App\Models\SiteSetting::set('storage_backup_secret_key', 'backup-secret', 'storage');
        \App\Models\SiteSetting::set('storage_backup_bucket', 'backup-bucket', 'storage');
        \App\Models\SiteSetting::set('storage_backup_endpoint', 'https://account.r2.cloudflarestorage.com', 'storage');

        $this->assertTrue($profiles->isCloud(StorageProfileService::PURPOSE_BACKUP));
        $this->assertSame('backup-bucket', $profiles->s3ConfigForPurpose(StorageProfileService::PURPOSE_BACKUP)['bucket']);
    }
}
