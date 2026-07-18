<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->mergeDuplicateFitments();

        if (! Schema::hasColumn('car_models', 'fitment_hash')) {
            Schema::table('car_models', function (Blueprint $table): void {
                $table->char('fitment_hash', 40)->nullable()->after('country_of_origin');
            });
        }

        DB::table('car_models')
            ->select(['id', 'car_make_id', 'vehicle_model_id', 'year', 'engine_size', 'variant_name', 'country_of_origin'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('car_models')
                        ->where('id', $row->id)
                        ->update(['fitment_hash' => $this->fitmentHash($row)]);
                }
            });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE car_models MODIFY fitment_hash CHAR(40) NOT NULL');
        }

        DB::statement('CREATE UNIQUE INDEX car_models_fitment_hash_unique ON car_models (fitment_hash)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS car_models_fitment_hash_unique');
        } else {
            DB::statement('DROP INDEX car_models_fitment_hash_unique ON car_models');
        }

        if (Schema::hasColumn('car_models', 'fitment_hash')) {
            Schema::table('car_models', function (Blueprint $table): void {
                $table->dropColumn('fitment_hash');
            });
        }
    }

    private function mergeDuplicateFitments(): void
    {
        $groups = DB::table('car_models')
            ->selectRaw("
                MIN(id) as keeper_id,
                GROUP_CONCAT(id ORDER BY id) as ids,
                COUNT(*) as duplicate_count
            ")
            ->groupByRaw("
                COALESCE(car_make_id, 0),
                COALESCE(vehicle_model_id, 0),
                COALESCE(year, 0),
                COALESCE(engine_size, ''),
                COALESCE(variant_name, ''),
                COALESCE(country_of_origin, '')
            ")
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $keeperId = (int) $group->keeper_id;
            $duplicateIds = collect(explode(',', (string) $group->ids))
                ->map(fn (string $id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0 && $id !== $keeperId)
                ->values();

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            DB::table('products')
                ->whereIn('car_model_id', $duplicateIds)
                ->update(['car_model_id' => $keeperId]);

            DB::table('product_compatibilities')
                ->whereIn('car_model_id', $duplicateIds)
                ->orderBy('id')
                ->get()
                ->each(function (object $compatibility) use ($keeperId): void {
                    $alreadyLinked = DB::table('product_compatibilities')
                        ->where('product_id', $compatibility->product_id)
                        ->where('car_model_id', $keeperId)
                        ->exists();

                    if ($alreadyLinked) {
                        DB::table('product_compatibilities')->where('id', $compatibility->id)->delete();

                        return;
                    }

                    DB::table('product_compatibilities')
                        ->where('id', $compatibility->id)
                        ->update(['car_model_id' => $keeperId]);
                });

            DB::table('car_models')->whereIn('id', $duplicateIds)->delete();
        }
    }

    private function fitmentHash(object $row): string
    {
        return sha1(implode('|', [
            (int) ($row->car_make_id ?? 0),
            (int) ($row->vehicle_model_id ?? 0),
            (int) ($row->year ?? 0),
            (string) ($row->engine_size ?? ''),
            (string) ($row->variant_name ?? ''),
            (string) ($row->country_of_origin ?? ''),
        ]));
    }
};
