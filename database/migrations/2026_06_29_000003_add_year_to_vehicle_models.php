<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_models', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->after('code');
            }
        });

        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->dropUnique(['car_make_id', 'name']);
            $table->unique(['car_make_id', 'name', 'year'], 'vehicle_models_make_name_year_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            $table->dropUnique('vehicle_models_make_name_year_unique');
            $table->unique(['car_make_id', 'name']);
        });

        Schema::table('vehicle_models', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_models', 'year')) {
                $table->dropColumn('year');
            }
        });
    }
};
