<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->comment('create / query / notify / refund');
            $table->string('provider', 32)->default('tianque');
            $table->string('result', 16)->nullable()->comment('success/fail/pending');
            $table->string('provider_trade_no', 128)->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
