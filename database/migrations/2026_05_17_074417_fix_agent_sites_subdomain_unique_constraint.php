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
            $table->dropUnique('agent_sites_subdomain_unique');
            $table->unique(['subdomain', 'subdomain_domain'], 'agent_sites_subdomain_domain_unique');
        });
    }

    public function down(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            $table->dropUnique('agent_sites_subdomain_domain_unique');
            $table->unique('subdomain', 'agent_sites_subdomain_unique');
        });
    }
};
