<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

            $table->unique(['car_make_id', 'name']);
            $table->unique(['car_make_id', 'code']);
            $table->index(['car_make_id', 'is_active']);
        });

        Schema::table('car_models', function (Blueprint $table) {
            $table->foreignId('car_make_id')
                ->nullable()
                ->after('id')
                ->constrained('car_makes')
                ->nullOnDelete();

            $table->foreignId('vehicle_model_id')
                ->nullable()
                ->after('car_make_id')
                ->constrained('vehicle_models')
                ->nullOnDelete();
        });

        $this->backfillExistingFitments();
    }

    public function down(): void
    {
        Schema::table('car_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_model_id');
            $table->dropConstrainedForeignId('car_make_id');
        });

        Schema::dropIfExists('vehicle_models');
        Schema::dropIfExists('car_makes');
    }

    private function backfillExistingFitments(): void
    {
        DB::table('car_models')
            ->select(['make', 'make_code', 'model', 'model_code'])
            ->distinct()
            ->orderBy('make')
            ->get()
            ->each(function (object $row): void {
                $makeId = DB::table('car_makes')->where('name', $row->make)->value('id');

                if (! $makeId) {
                    $makeId = DB::table('car_makes')->insertGetId([
                        'name' => $row->make,
                        'code' => $this->uniqueCode('car_makes', $row->make_code ?: $row->make, 10),
                        'description' => 'Backfilled from existing vehicle fitment records.',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $modelId = DB::table('vehicle_models')->insertGetId([
                    'car_make_id' => $makeId,
                    'name' => $row->model,
                    'code' => $this->uniqueModelCode($makeId, $row->model_code ?: $row->model),
                    'year' => null,
                    'body_style' => null,
                    'description' => 'Backfilled from existing vehicle fitment records.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('car_models')
                    ->where('make', $row->make)
                    ->where('model', $row->model)
                    ->update([
                        'car_make_id' => $makeId,
                        'vehicle_model_id' => $modelId,
                    ]);
            });
    }

    private function uniqueCode(string $table, string $value, int $length): string
    {
        $base = str($value)->upper()->replaceMatches('/[^A-Z0-9]/', '')->substr(0, $length)->toString() ?: 'MAKE';
        $code = $base;
        $suffix = 1;

        while (DB::table($table)->where('code', $code)->exists()) {
            $code = substr($base, 0, max(1, $length - 2)).str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $code;
    }

    private function uniqueModelCode(int $makeId, string $value): string
    {
        $base = str($value)->upper()->replaceMatches('/[^A-Z0-9]/', '')->substr(0, 20)->toString() ?: 'MODEL';
        $code = $base;
        $suffix = 1;

        while (DB::table('vehicle_models')->where('car_make_id', $makeId)->where('code', $code)->exists()) {
            $code = substr($base, 0, 17).str_pad((string) $suffix, 3, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $code;
    }
};
