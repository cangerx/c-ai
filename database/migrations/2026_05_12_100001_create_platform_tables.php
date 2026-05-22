<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redeem_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->enum('type', ['credits', 'balance', 'mixed'])->default('mixed');
            $table->integer('credits')->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->enum('status', ['unused', 'used', 'disabled'])->default('unused');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('batch_id', 64)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ai_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('base_url');
            $table->text('api_key');
            $table->json('models')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('rate_limit')->default(60);
            $table->string('app_name', 64)->default('image-gen')->index();
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group', 64)->default('general')->index();
            $table->timestamps();
        });

        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('app_name', 64)->index();
            $table->string('task_id', 64)->nullable()->index();
            $table->foreignId('channel_id')->nullable()->constrained('ai_channels')->nullOnDelete();
            $table->string('model')->nullable();
            $table->string('quality')->nullable();
            $table->integer('cost_credits')->default(0);
            $table->decimal('cost_balance', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('billing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('app_name', 64)->index();
            $table->string('model_pattern');
            $table->string('quality')->nullable();
            $table->integer('cost_credits')->default(1);
            $table->decimal('cost_balance', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_rules');
        Schema::dropIfExists('usage_logs');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('ai_channels');
        Schema::dropIfExists('redeem_codes');
    }
};
