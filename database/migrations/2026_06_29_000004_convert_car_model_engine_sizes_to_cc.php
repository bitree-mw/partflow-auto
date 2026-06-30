<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('car_models')
            ->where('engine_size', 'like', '%L')
            ->select(['id', 'engine_size'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $numeric = (float) preg_replace('/[^0-9.]/', '', (string) $row->engine_size);

                    if ($numeric <= 0) {
                        continue;
                    }

                    $cc = $numeric < 100 ? $numeric * 1000 : $numeric;
                    $value = rtrim(rtrim(number_format($cc, 1, '.', ''), '0'), '.').'cc';

                    DB::table('car_models')
                        ->where('id', $row->id)
                        ->update(['engine_size' => $value]);
                }
            });
    }

    public function down(): void
    {
        DB::table('car_models')
            ->where('engine_size', 'like', '%cc')
            ->select(['id', 'engine_size'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $numeric = (float) preg_replace('/[^0-9.]/', '', (string) $row->engine_size);

                    if ($numeric <= 0) {
                        continue;
                    }

                    $litres = $numeric >= 100 ? $numeric / 1000 : $numeric;
                    $value = rtrim(rtrim(number_format($litres, 1, '.', ''), '0'), '.').'L';

                    DB::table('car_models')
                        ->where('id', $row->id)
                        ->update(['engine_size' => $value]);
                }
            });
    }
};
