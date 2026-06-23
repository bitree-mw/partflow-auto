<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_references', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->enum('reference_type', [
                'barcode',
                'oem_number',
                'supplier_code',
                'aftermarket_code',
                'other',
            ]);

            $table->string('reference_value');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'product_id',
                'reference_type',
                'reference_value',
            ], 'product_reference_unique');

            $table->index(['reference_type', 'reference_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_references');
    }
};
