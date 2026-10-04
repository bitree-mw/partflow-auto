<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Frozen schema baseline through 2026-09-15. Use schema:install-baseline only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_documents', function (Blueprint $table) {
            $table->id();

            $table->string('document_number')->unique();
            $table->enum('document_type', [
                'purchase',
                'sale',
                'transfer',
                'sale_return',
                'purchase_return',
                'adjustment',
                'stock_take',
                'reservation',
            ]);

            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->foreignId('source_site_id')
                ->nullable()
                ->constrained('sites')
                ->restrictOnDelete();

            $table->foreignId('destination_site_id')
                ->nullable()
                ->constrained('sites')
                ->restrictOnDelete();

            $table->dateTime('document_date');
            $table->enum('status', [
                'draft',
                'pending',
                'completed',
                'approved',
                'cancelled',
            ])->default('draft');

            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            $table->enum('payment_status', [
                'unpaid',
                'partial',
                'paid',
            ])->default('unpaid');

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['document_type', 'status']);
            $table->index(['document_date']);
            $table->index(['source_site_id', 'destination_site_id']);
            $table->index(['contact_id', 'payment_status']);
        });

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

            $table->timestamp('low_stock_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'site_id']);
            $table->index(['site_id', 'product_id']);
            $table->index(['quantity_on_hand']);
        });

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
        Schema::dropIfExists('site_stocks');
        Schema::dropIfExists('inventory_document_items');
        Schema::dropIfExists('inventory_documents');
    }
};
