<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            $table->string('display_name', 100)->nullable()->after('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_distributor')->default(false)->after('role');
            $table->unsignedInteger('total_consumed_credits')->default(0)->after('credits');
            $table->unsignedInteger('commission_credits')->default(0)->after('total_consumed_credits');
        });
    }

    public function down(): void
    {
        Schema::table('ai_channels', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_distributor', 'total_consumed_credits', 'commission_credits']);
        });
    }
};
