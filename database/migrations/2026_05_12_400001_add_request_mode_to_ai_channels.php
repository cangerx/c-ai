<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_channels', 'request_mode')) {
                $table->string('request_mode', 10)->default('sync')->after('priority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            $table->dropColumn('request_mode');
        });
    }
};
