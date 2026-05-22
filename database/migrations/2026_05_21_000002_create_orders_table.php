<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->unique()->comment('本地订单号');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('payment_packages')->nullOnDelete();
            $table->decimal('amount', 10, 2)->comment('支付金额(元)');
            $table->integer('credits')->comment('购买积分(含赠送)');
            $table->string('subject', 200)->nullable();
            $table->string('pay_provider', 32)->default('tianque')->comment('支付提供商');
            $table->string('pay_method', 32)->nullable()->comment('WECHAT/ALIPAY/UNIONPAY');
            $table->string('status', 16)->default('pending')->comment('pending/paid/failed/cancelled/refunded');
            $table->string('provider_order_no', 128)->nullable()->index()->comment('天雀返回的 uuid');
            $table->string('provider_trade_no', 128)->nullable()->comment('天雀正交易落单号 transactionId');
            $table->text('qr_code')->nullable()->comment('二维码内容/链接');
            $table->json('provider_request')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('credits_granted')->default(false)->comment('是否已发放积分(幂等)');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
