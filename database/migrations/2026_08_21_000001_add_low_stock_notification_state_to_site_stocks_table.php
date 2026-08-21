<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_stocks', function (Blueprint $table) {
            $table->timestamp('low_stock_notified_at')
                ->nullable()
                ->after('low_stock_level');
        });
    }

    public function down(): void
    {
        Schema::table('site_stocks', function (Blueprint $table) {
            $table->dropColumn('low_stock_notified_at');
        });
    }
};
