<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 合并：orders.package_id 由指向 payment_packages 改为指向 plans。
 * 历史 payment_packages 数据保留但不再使用；旧 package_id 引用置空（避免脏外键）。
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            try { $table->dropForeign(['package_id']); } catch (\Throwable $e) {}
        });

        // 旧引用清空（payment_packages 表已废弃）
        if (Schema::hasTable('payment_packages')) {
            DB::table('orders')->whereNotNull('package_id')->update(['package_id' => null]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('package_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;
        Schema::table('orders', function (Blueprint $table) {
            try { $table->dropForeign(['package_id']); } catch (\Throwable $e) {}
            if (Schema::hasTable('payment_packages')) {
                $table->foreign('package_id')->references('id')->on('payment_packages')->nullOnDelete();
            }
        });
    }
};
