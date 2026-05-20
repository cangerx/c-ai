<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_sites', 'seo_title')) {
                $table->string('seo_title', 200)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'seo_description')) {
                $table->string('seo_description', 500)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'seo_keywords')) {
                $table->string('seo_keywords', 300)->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};
