<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->decimal('min_recharge', 10, 2)->default(0);
            $table->decimal('price_per_credit', 6, 4)->default(0.1000);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('agent_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug', 32)->unique();
            $table->string('subdomain', 32)->nullable()->unique();
            $table->string('custom_domain', 255)->nullable()->unique();
            $table->string('site_name', 100);
            $table->string('logo_url', 500)->nullable();
            $table->string('theme_color', 7)->default('#2d5bf0');
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('seo_keywords', 300)->nullable();
            $table->text('announcement')->nullable();
            $table->unsignedInteger('cost_per_generation')->nullable();
            $table->unsignedTinyInteger('commission_rate')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('credits')->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->text('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['recharge', 'generate', 'commission', 'withdraw']);
            $table->integer('credits')->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->unsignedInteger('credits_after')->default(0);
            $table->decimal('balance_after', 10, 2)->default(0);
            $table->string('description', 255)->default('');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agent_level_id')->nullable()->after('is_distributor')->constrained('agent_levels')->nullOnDelete();
            $table->decimal('total_recharged', 10, 2)->default(0)->after('agent_level_id');
        });

        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->foreignId('agent_plan_id')->nullable()->after('plan_id')->constrained('agent_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_plan_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_level_id');
            $table->dropColumn('total_recharged');
        });
        Schema::dropIfExists('agent_transactions');
        Schema::dropIfExists('agent_plans');
        Schema::dropIfExists('agent_sites');
        Schema::dropIfExists('agent_levels');
    }
};
