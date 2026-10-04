<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Frozen schema baseline through 2026-09-15. Use schema:install-baseline only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('account_name')->unique();
            $table->enum('account_type', [
                'cash',
                'bank',
                'mobile_money',
                'card',
            ]);
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_type', 'is_active']);
        });

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

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('expense_category_id')
                ->constrained('expense_categories')
                ->restrictOnDelete();

            $table->foreignId('payment_account_id')
                ->nullable()
                ->constrained('payment_accounts')
                ->nullOnDelete();

            $table->foreignId('site_id')
                ->nullable()
                ->constrained('sites')
                ->nullOnDelete();

            $table->dateTime('expense_date');
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->string('reference')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['expense_date']);
            $table->index(['expense_category_id', 'expense_date']);
            $table->index(['payment_account_id', 'expense_date']);
            $table->index(['site_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_accounts');
    }
};
