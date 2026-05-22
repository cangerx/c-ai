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
            $table->string('footer_text', 300)->nullable()->after('seo_keywords');
            $table->string('footer_icp', 100)->nullable()->after('footer_text');
            $table->json('footer_links')->nullable()->after('footer_icp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            $table->dropColumn(['footer_text', 'footer_icp', 'footer_links']);
        });
    }
};
