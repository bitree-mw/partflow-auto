<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_document_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_document_id')
                ->constrained('inventory_documents')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('profit_amount', 15, 2)->default(0);
            $table->integer('system_quantity')->nullable();
            $table->integer('counted_quantity')->nullable();
            $table->integer('variance_quantity')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['inventory_document_id', 'product_id'], 'inventory_document_item_product_index');
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_document_items');
    }
};
