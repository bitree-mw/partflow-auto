<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('part_types') && ! Schema::hasTable('product_types')) {
            Schema::rename('part_types', 'product_types');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_types') && ! Schema::hasTable('part_types')) {
            Schema::rename('product_types', 'part_types');
        }
    }
};
