<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('template_categories')) {
            Schema::create('template_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50);
                $table->string('icon', 50)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('prompt_templates') && !Schema::hasColumn('prompt_templates', 'category_id')) {
            Schema::table('prompt_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('id');
                $table->foreign('category_id')->references('id')->on('template_categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('template_categories');
    }
};
