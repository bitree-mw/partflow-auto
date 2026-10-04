<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Frozen schema baseline through 2026-09-15. Use schema:install-baseline only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_make_id')->constrained('car_makes')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('body_style')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['car_make_id', 'name', 'year'], 'vehicle_models_make_name_year_unique');
            $table->unique(['car_make_id', 'code']);
            $table->index(['car_make_id', 'is_active']);
        });

        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_make_id')->nullable()->constrained('car_makes')->nullOnDelete();
            $table->foreignId('vehicle_model_id')->nullable()->constrained('vehicle_models')->nullOnDelete();
            $table->string('engine_size')->nullable();
            $table->string('variant_name')->nullable();
            $table->char('fitment_hash', 40)->nullable(DB::getDriverName() === 'sqlite')->unique();
            $table->index(['make', 'model', 'year', 'engine_size'], 'car_model_variant_index');

            $table->string('make');
            $table->string('make_code', 10);

            $table->string('model');
            $table->string('model_code', 10);

            $table->year('year');
            $table->string('country_of_origin')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['make', 'model', 'year']);
            $table->index(['make_code', 'model_code']);
            $table->index(['is_active']);
        });

        Schema::create('product_types', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 20)->unique('part_types_code_unique');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'code'], 'part_types_name_code_index');
            $table->index('is_active', 'part_types_is_active_index');
        });

        Schema::create('fuel_types', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('code', 10)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('code', 50)->nullable()->unique();
            $table->string('country')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['name', 'code']);
            $table->index('is_active');
        });

        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('code', 50)->unique();

            $table->enum('tax_type', [
                'vat',
                'none',
            ])->default('vat');

            $table->decimal('tax_rate', 5, 2)->default(0);

            $table->enum('price_mode', [
                'inclusive',
                'exclusive',
                'exempt',
                'none',
            ])->default('inclusive');

            $table->boolean('is_exempt')->default(false);
            $table->text('exemption_reason')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tax_type', 'price_mode']);
            $table->index(['is_default', 'is_active']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('product_code')->unique();
            $table->string('product_name');

            $table->foreignId('car_model_id')
                ->nullable()
                ->constrained('car_models')
                ->nullOnDelete();

            $table->foreignId('product_type_id')
                ->constrained('product_types')
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
            $table->text('pos_description')->nullable();
            $table->decimal('minimum_selling_price', 15, 2)->default(0);
            $table->index('car_model_id');

            $table->decimal('default_purchase_price', 15, 2)->default(0);
            $table->decimal('default_selling_price', 15, 2)->default(0);

            $table->integer('default_low_stock_level')->default(0);

            $table->string('unit_name')->default('piece');
            $table->decimal('pack_size', 12, 2)->default(1);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['car_model_id', 'product_type_id']);
            $table->index(['fuel_type_id', 'brand_id']);
            $table->index('is_active');
        });

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

        Schema::create('product_compatibilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('car_model_id')
                ->constrained('car_models')
                ->cascadeOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'product_id',
                'car_model_id',
            ], 'product_compatibility_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_compatibilities');
        Schema::dropIfExists('product_references');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_profiles');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('fuel_types');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('car_models');
        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('car_makes');
    }
};
