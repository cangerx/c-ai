<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'bonus_balance')) {
                $table->decimal('bonus_balance', 10, 2)->default(0)->after('credits')
                    ->comment('套餐赠送余额快照');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'bonus_balance')) {
                $table->dropColumn('bonus_balance');
            }
        });
    }
};
