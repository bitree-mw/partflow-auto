<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->restrictOnDelete();

            $table->integer('quantity_on_hand')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('low_stock_level')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'site_id']);
            $table->index(['site_id', 'product_id']);
            $table->index(['quantity_on_hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stocks');
    }
};
