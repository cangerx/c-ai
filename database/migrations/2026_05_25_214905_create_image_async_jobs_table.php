<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 异步回调任务映射表
 *
 * 用于 async-oo provider：发起请求时生成 callback_token 发给上游，
 * 上游完成后回调 /api/channels/async-oo/callback/{token}，
 * 通过该映射找到对应 GenerationTask 与 index。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_async_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('callback_token', 64)->unique();
            $table->string('task_id', 64)->index();        // GenerationTask.task_id
            $table->unsignedInteger('index')->default(0);  // 多图任务中的第几张
            $table->unsignedBigInteger('channel_id');
            $table->string('upstream_id')->nullable();     // 上游返回的任务 id
            $table->string('status', 16)->default('pending'); // pending / completed / failed
            $table->json('payload')->nullable();           // 上游回调的原始 body
            $table->string('error')->nullable();
            $table->timestamp('expires_at')->nullable()->index(); // 超时清理用
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'index'], 'image_async_jobs_task_index_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_async_jobs');
    }
};
