<?php

namespace Tests\Feature;

use App\Models\GenerationTask;
use App\Models\User;
use App\Services\StorageProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanExpiredImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_temp_cleanup_removes_expired_upload_files_only(): void
    {
        Storage::fake('public');
        \App\Models\SiteSetting::set('storage_temp_ttl_days', '7', 'storage');

        Storage::disk('public')->put('uploads/old.png', 'old');
        Storage::disk('public')->put('uploads/new.png', 'new');

        $task = GenerationTask::factory()
            ->for(User::factory())
            ->completed()
            ->create([
                'created_at' => now()->subDays(10),
                'files' => [
                    [
                        'key' => 'uploads/old.png',
                        'url' => '/storage/uploads/old.png',
                        'purpose' => StorageProfileService::PURPOSE_UPLOAD,
                        'expires_at' => now()->subDay()->toDateTimeString(),
                    ],
                    [
                        'key' => 'uploads/new.png',
                        'url' => '/storage/uploads/new.png',
                        'purpose' => StorageProfileService::PURPOSE_UPLOAD,
                        'expires_at' => now()->addDay()->toDateTimeString(),
                    ],
                ],
            ]);

        Artisan::call('images:clean-expired', ['--temp' => true]);

        Storage::disk('public')->assertMissing('uploads/old.png');
        Storage::disk('public')->assertExists('uploads/new.png');

        $task->refresh();
        $this->assertCount(1, $task->files);
        $this->assertSame('uploads/new.png', $task->files[0]['key']);
    }

    public function test_temp_cleanup_honors_expired_at_even_for_recent_tasks(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/recent-expired.png', 'old');

        $task = GenerationTask::factory()
            ->for(User::factory())
            ->completed()
            ->create([
                'created_at' => now(),
                'files' => [
                    [
                        'key' => 'uploads/recent-expired.png',
                        'purpose' => StorageProfileService::PURPOSE_UPLOAD,
                        'expires_at' => now()->subMinute()->toDateTimeString(),
                    ],
                ],
            ]);

        Artisan::call('images:clean-expired', ['--temp' => true]);

        Storage::disk('public')->assertMissing('uploads/recent-expired.png');
        $task->refresh();
        $this->assertSame([], $task->files);
    }

    public function test_temp_cleanup_falls_back_to_ttl_for_malformed_expires_at(): void
    {
        Storage::fake('public');
        \App\Models\SiteSetting::set('storage_temp_ttl_days', '7', 'storage');
        Storage::disk('public')->put('uploads/malformed-expiry.png', 'old');

        $task = GenerationTask::factory()
            ->for(User::factory())
            ->completed()
            ->create([
                'created_at' => now()->subDays(8),
                'files' => [
                    [
                        'key' => 'uploads/malformed-expiry.png',
                        'purpose' => StorageProfileService::PURPOSE_UPLOAD,
                        'expires_at' => 'bad-date',
                    ],
                ],
            ]);

        Artisan::call('images:clean-expired', ['--temp' => true]);

        Storage::disk('public')->assertMissing('uploads/malformed-expiry.png');
        $task->refresh();
        $this->assertSame([], $task->files);
    }
}
