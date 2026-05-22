<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            $table->string('provider', 50)->default('openai')->after('name');
            $table->string('model', 100)->nullable()->after('api_key');
            $table->string('status', 20)->default('active')->after('is_active');
            $table->json('config')->nullable()->after('app_name');
        });

        // Migrate is_active to status
        \DB::table('ai_channels')->where('is_active', false)->update(['status' => 'disabled']);
    }

    public function down(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            $table->dropColumn(['provider', 'model', 'status', 'config']);
        });
    }
};
