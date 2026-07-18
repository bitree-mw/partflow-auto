<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('product_code')->unique();
            $table->string('product_name');

            $table->foreignId('car_model_id')
                ->nullable()
                ->constrained('car_models')
                ->nullOnDelete();

            $table->foreignId('part_type_id')
                ->constrained('part_types')
                ->restrictOnDelete();

            $table->foreignId('fuel_type_id')
                ->nullable()
                ->constrained('fuel_types')
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->foreignId('tax_profile_id')
                ->nullable()
                ->constrained('tax_profiles')
                ->nullOnDelete();

            $table->string('part_country_of_origin')->nullable();
            $table->string('main_image_path')->nullable();

            $table->text('description')->nullable();

            $table->decimal('default_purchase_price', 15, 2)->default(0);
            $table->decimal('default_selling_price', 15, 2)->default(0);

            $table->integer('default_low_stock_level')->default(0);

            $table->string('unit_name')->default('piece');
            $table->decimal('pack_size', 12, 2)->default(1);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['car_model_id', 'part_type_id']);
            $table->index(['fuel_type_id', 'brand_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
