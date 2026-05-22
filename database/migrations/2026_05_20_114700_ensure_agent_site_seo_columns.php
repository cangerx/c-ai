<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_sites', function (Blueprint $table) {
            // SEO 字段（可能早期建表时遗漏）
            if (!Schema::hasColumn('agent_sites', 'seo_title')) {
                $table->string('seo_title', 200)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'seo_description')) {
                $table->string('seo_description', 500)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'seo_keywords')) {
                $table->string('seo_keywords', 300)->nullable();
            }
            // 页脚字段
            if (!Schema::hasColumn('agent_sites', 'footer_text')) {
                $table->string('footer_text', 300)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'footer_icp')) {
                $table->string('footer_icp', 100)->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'footer_links')) {
                $table->json('footer_links')->nullable();
            }
            // 子域名域名
            if (!Schema::hasColumn('agent_sites', 'subdomain_domain')) {
                $table->string('subdomain_domain', 255)->nullable();
            }
            // 状态审核字段
            if (!Schema::hasColumn('agent_sites', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
            }
            if (!Schema::hasColumn('agent_sites', 'reject_reason')) {
                $table->text('reject_reason')->nullable();
            }
            if (!Schema::hasColumn('agent_sites', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            // 首页可视化编辑字段
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
