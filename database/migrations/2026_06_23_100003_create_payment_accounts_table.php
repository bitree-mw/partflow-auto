<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
