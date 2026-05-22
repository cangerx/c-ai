<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_sites', 'hero_title')) {
                $table->string('hero_title', 200)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'hero_subtitle')) {
                $table->string('hero_subtitle', 500)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'hero_bg_url')) {
                $table->string('hero_bg_url', 500)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'hero_bg_color')) {
                $table->string('hero_bg_color', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};
