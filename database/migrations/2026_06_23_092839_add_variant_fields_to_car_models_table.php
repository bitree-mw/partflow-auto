<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_models', function (Blueprint $table) {
            $table->string('engine_size')->nullable()->after('year');
            $table->string('variant_name')->nullable()->after('engine_size');

            $table->index(['make', 'model', 'year', 'engine_size'], 'car_model_variant_index');
        });
    }

    public function down(): void
    {
        Schema::table('car_models', function (Blueprint $table) {
            $table->dropIndex('car_model_variant_index');

            $table->dropColumn([
                'engine_size',
                'variant_name',
            ]);
        });
    }
};
