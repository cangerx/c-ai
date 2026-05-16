<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('once'); // once, subscription
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('credits')->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->integer('duration_days')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->text('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('redeem_codes', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });
        Schema::dropIfExists('plans');
    }
};
