<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
