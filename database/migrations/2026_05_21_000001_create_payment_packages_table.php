<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('内部编码');
            $table->string('name', 100)->comment('显示名称');
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2)->comment('支付金额(元)');
            $table->integer('credits')->comment('到账积分(基础)');
            $table->integer('bonus_credits')->default(0)->comment('赠送积分');
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_packages');
    }
};
