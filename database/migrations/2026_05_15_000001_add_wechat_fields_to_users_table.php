<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('wechat_openid')->nullable()->unique()->after('github_id');
            $table->string('wechat_unionid')->nullable()->index()->after('wechat_openid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wechat_openid', 'wechat_unionid']);
        });
    }
};
