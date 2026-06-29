<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_makes', function (Blueprint $table) {
            if (Schema::hasColumn('car_makes', 'country')) {
                $table->dropColumn('country');
            }
        });

        Schema::table('vehicle_models', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_models', 'start_year')) {
                $table->dropColumn('start_year');
            }

            if (Schema::hasColumn('vehicle_models', 'end_year')) {
                $table->dropColumn('end_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('car_makes', function (Blueprint $table) {
            if (! Schema::hasColumn('car_makes', 'country')) {
                $table->string('country')->nullable()->after('code');
            }
        });

        Schema::table('vehicle_models', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_models', 'start_year')) {
                $table->year('start_year')->nullable()->after('code');
            }

            if (! Schema::hasColumn('vehicle_models', 'end_year')) {
                $table->year('end_year')->nullable()->after('start_year');
            }
        });
    }
};
