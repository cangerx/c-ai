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
        Schema::table('agent_sites', function (Blueprint $table) {
            $table->string('subdomain_domain', 255)->nullable()->after('subdomain');
        });
    }

    public function down(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            $table->dropColumn('subdomain_domain');
        });
    }
};
