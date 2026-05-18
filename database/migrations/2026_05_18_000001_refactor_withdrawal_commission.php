<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable()->after('user_id');
            $table->text('agent_note')->nullable()->after('admin_note');
            $table->timestamp('agent_processed_at')->nullable()->after('processed_at');

            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
            $table->index('agent_id');
        });

        Schema::table('commission_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable()->after('user_id');
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn(['agent_id', 'agent_note', 'agent_processed_at']);
        });

        Schema::table('commission_logs', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn('agent_id');
        });
    }
};
