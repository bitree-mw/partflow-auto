<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_document_id')
                ->constrained('inventory_documents')
                ->cascadeOnDelete();

            $table->foreignId('payment_account_id')
                ->constrained('payment_accounts')
                ->restrictOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->string('transaction_reference')->nullable();
            $table->dateTime('payment_date');

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['inventory_document_id', 'payment_date']);
            $table->index(['payment_account_id', 'payment_date']);
            $table->index(['payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
