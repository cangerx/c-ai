<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('generation_tasks', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
