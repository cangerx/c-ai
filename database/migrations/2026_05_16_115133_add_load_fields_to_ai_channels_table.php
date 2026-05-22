<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            $table->integer('current_load')->default(0)->after('rate_limit');
            $table->integer('error_count')->default(0)->after('current_load');
            $table->integer('max_errors')->default(5)->after('error_count');
            $table->timestamp('paused_at')->nullable()->after('max_errors');
        });
    }

    public function down(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            $table->dropColumn(['current_load', 'error_count', 'max_errors', 'paused_at']);
        });
    }
};
