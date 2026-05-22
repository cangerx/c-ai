<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('is_active');
            $table->text('reject_reason')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            $table->dropColumn(['status', 'reject_reason', 'approved_at']);
        });
    }
};
