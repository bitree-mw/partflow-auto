<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_models', function (Blueprint $table) {
            $table->id();

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
    }

    public function down(): void
    {
        Schema::dropIfExists('car_models');
    }
};
