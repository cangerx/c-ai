<?php

namespace Tests\Feature;

use App\Models\GenerationTask;
use App\Models\ImageAsyncJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsyncOoCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** 1x1 透明 PNG 的 base64（避免出网） */
    protected function tinyPngB64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=';
    }

    public function test_unknown_token_returns_404(): void
    {
        $resp = $this->postJson('/api/channels/async-oo/callback/' . str_repeat('a', 64), []);
        $resp->assertStatus(404);
    }

    public function test_invalid_token_format_404(): void
    {
        $resp = $this->postJson('/api/channels/async-oo/callback/short-token', []);
        $resp->assertStatus(404); // 路由 where 约束未匹配
    }

    public function test_callback_marks_task_completed_for_single_image(): void
    {
        $user = User::factory()->create();
        $task = GenerationTask::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'count' => 1,
            'items' => [null],
            'size' => 'auto',
        ]);

        $token = str_repeat('b', 64);
        $job = ImageAsyncJob::create([
            'callback_token' => $token,
            'task_id' => $task->task_id,
            'index' => 0,
            'channel_id' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $resp = $this->postJson('/api/channels/async-oo/callback/' . $token, [
            'id' => 'upstream-task-1',
            'data' => [
                ['b64_json' => $this->tinyPngB64()],
            ],
        ]);

        $resp->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('completed', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->completed_at);
    }

    public function test_callback_with_error_marks_task_failed_and_refunds(): void
    {
        $user = User::factory()->create();
        $task = GenerationTask::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'count' => 1,
            'items' => [null],
        ]);

        $token = str_repeat('c', 64);
        ImageAsyncJob::create([
            'callback_token' => $token,
            'task_id' => $task->task_id,
            'index' => 0,
            'channel_id' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $resp = $this->postJson('/api/channels/async-oo/callback/' . $token, [
            'error' => ['message' => 'rate_limited'],
        ]);

        $resp->assertOk();
        $this->assertSame('failed', $task->fresh()->status);
    }

    public function test_callback_is_idempotent(): void
    {
        $user = User::factory()->create();
        $task = GenerationTask::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
            'count' => 1,
            'items' => [null],
            'size' => 'auto',
        ]);

        $token = str_repeat('d', 64);
        ImageAsyncJob::create([
            'callback_token' => $token,
            'task_id' => $task->task_id,
            'index' => 0,
            'channel_id' => 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $payload = [
            'id' => 'upstream-task-1',
            'data' => [['b64_json' => $this->tinyPngB64()]],
        ];

        $this->postJson('/api/channels/async-oo/callback/' . $token, $payload)->assertOk();
        // 第二次相同回调：状态保持 completed，不重复处理
        $resp = $this->postJson('/api/channels/async-oo/callback/' . $token, $payload);
        $resp->assertOk()->assertJson(['ok' => true, 'message' => 'already completed']);
    }
}
