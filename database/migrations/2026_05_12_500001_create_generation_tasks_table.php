<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_tasks', function (Blueprint $table) {
            $table->string('task_id', 32)->primary();
            $table->foreignId('user_id')->constrained('users');
            $table->string('status', 20)->default('pending')->index();
            $table->string('mode', 10)->default('text');
            $table->string('model', 100);
            $table->text('prompt');
            $table->string('size', 20)->default('auto');
            $table->string('quality', 10)->default('medium');
            $table->unsignedTinyInteger('count')->default(1);
            $table->boolean('is_public')->default(false);
            $table->unsignedTinyInteger('input_count')->default(0);
            $table->string('message')->nullable();
            $table->text('error')->nullable();
            $table->json('items')->nullable();
            $table->json('files')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['is_public', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_tasks');
    }
};
