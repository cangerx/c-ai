<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('task_id', 32)->nullable()->index();
            $table->string('title', 100);
            $table->text('original_prompt');
            $table->text('template_prompt');
            $table->json('variables')->nullable();
            $table->string('tags', 255)->nullable();
            $table->string('preview_url', 500)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('status', 10)->default('draft');
            $table->timestamps();

            $table->foreign('task_id')->references('task_id')->on('generation_tasks')->nullOnDelete();
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
