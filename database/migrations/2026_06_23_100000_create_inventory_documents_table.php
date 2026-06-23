<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_documents');
    }
};
