<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $products = DB::table('products')
                ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
                ->leftJoin('part_types', 'part_types.id', '=', 'products.part_type_id')
                ->select([
                    'products.id',
                    'products.part_country_of_origin',
                    'brands.code as brand_code',
                    'part_types.code as part_type_code',
                ])
                ->orderBy('products.id')
                ->get();

            foreach ($products as $product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['product_code' => '__TMP_PRODUCT_'.$product->id]);
            }

            $nextNumbers = [];

            foreach ($products as $product) {
                $baseCode = implode('-', [
                    $this->cleanCode((string) ($product->brand_code ?: 'UNKN'), 4, 'UNKN'),
                    $this->cleanCode((string) ($product->part_type_code ?: 'PT'), 0, 'PT'),
                    $this->countryCode($product->part_country_of_origin),
                ]);

                $nextNumbers[$baseCode] = ($nextNumbers[$baseCode] ?? 0) + 1;

                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'product_code' => $baseCode.'-'.str_pad((string) $nextNumbers[$baseCode], 3, '0', STR_PAD_LEFT),
                    ]);
            }
        });
    }

    public function down(): void
    {
        //
    }

    private function cleanCode(string $value, int $length, string $fallback): string
    {
        $code = Str::of($value)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->when($length > 0, fn ($code) => $code->substr(0, $length)->padRight($length, 'X'))
            ->toString();

        return $code !== '' ? $code : $fallback;
    }

    private function countryCode(?string $country): string
    {
        $country = Str::of($country ?: 'Unknown')->lower()->trim()->toString();
        $codes = [
            'china' => 'CHN',
            'fiji' => 'FJI',
            'germany' => 'DEU',
            'india' => 'IND',
            'japan' => 'JPN',
            'malawi' => 'MWI',
            'singapore' => 'SGP',
            'south africa' => 'ZAF',
            'south korea' => 'KOR',
            'taiwan' => 'TWN',
            'thailand' => 'THA',
            'united kingdom' => 'GBR',
            'uk' => 'GBR',
            'united states' => 'USA',
            'usa' => 'USA',
            'unknown' => 'UNK',
        ];

        return $codes[$country] ?? Str::of($country)
            ->upper()
            ->replaceMatches('/[^A-Z]/', '')
            ->substr(0, 3)
            ->padRight(3, 'X')
            ->toString();
    }
};
