<?php

namespace Tests\Unit;

use App\Services\ImageStorageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// ImageStorageService 单元测试
class ImageStorageServiceTest extends TestCase
{
    private ImageStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageStorageService();
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
}
