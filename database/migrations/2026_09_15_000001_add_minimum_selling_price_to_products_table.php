<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('minimum_selling_price', 15, 2)
                ->default(0)
                ->after('default_selling_price');
        });

        DB::table('products')->update([
            'minimum_selling_price' => DB::raw('ROUND(default_selling_price * 0.80, 2)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('minimum_selling_price');
        });
    }
};
