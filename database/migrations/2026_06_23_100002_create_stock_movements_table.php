<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('site_id')
                ->constrained('sites')
                ->restrictOnDelete();

            $table->enum('movement_type', [
                'purchase_in',
                'sale_out',
                'transfer_in',
                'transfer_out',
                'sale_return_in',
                'purchase_return_out',
                'adjustment_in',
                'adjustment_out',
                'stock_take_adjustment',
                'reservation_in',
                'reservation_out',
            ]);

            $table->integer('quantity_change');
            $table->integer('balance_before');
            $table->integer('balance_after');

            $table->foreignId('inventory_document_id')
                ->nullable()
                ->constrained('inventory_documents')
                ->nullOnDelete();

            $table->foreignId('inventory_document_item_id')
                ->nullable()
                ->constrained('inventory_document_items')
                ->nullOnDelete();

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['product_id', 'site_id']);
            $table->index(['movement_type', 'created_at']);
            $table->index(['inventory_document_id', 'inventory_document_item_id'], 'stock_movement_document_index');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
