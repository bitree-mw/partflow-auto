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
                ->leftJoin('product_types', 'product_types.id', '=', 'products.part_type_id')
                ->leftJoin('car_models', 'car_models.id', '=', 'products.car_model_id')
                ->select([
                    'products.id',
                    'products.car_model_id',
                    'products.part_country_of_origin',
                    'brands.code as brand_code',
                    'product_types.code as product_type_code',
                    'car_models.make as make',
                    'car_models.make_code as make_code',
                    'car_models.model as model',
                    'car_models.model_code as model_code',
                    'car_models.year as year',
                    'car_models.engine_size as engine_size',
                ])
                ->orderBy('products.id')
                ->get();

            foreach ($products as $product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['product_code' => '__TMP_PRODUCT_'.$product->id]);
            }

            $nextNumbers = [];
            $usedCompactCodes = [];

            foreach ($products as $product) {
                $typeCode = $this->cleanCode((string) ($product->product_type_code ?: 'PT'), 0, 'PT');

                if ($product->car_model_id) {
                    $baseCode = implode('', [
                        $typeCode,
                        $this->cleanCode((string) ($product->make_code ?: $product->make), 0, 'MAKE'),
                        $this->cleanCode((string) ($product->model_code ?: $product->model), 0, 'MODEL'),
                        $this->yearCode($product->year),
                        $this->engineCode($product->engine_size),
                    ]);

                    $productCode = $this->uniqueCompactProductCode($baseCode, $usedCompactCodes);
                } else {
                    $baseCode = implode('-', [
                        $typeCode,
                        $this->cleanCode((string) ($product->brand_code ?: 'UNKN'), 4, 'UNKN'),
                        $this->countryCode($product->part_country_of_origin),
                    ]);

                    $nextNumbers[$baseCode] = ($nextNumbers[$baseCode] ?? 0) + 1;
                    $productCode = $baseCode.'-'.str_pad((string) $nextNumbers[$baseCode], 3, '0', STR_PAD_LEFT);
                }

                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['product_code' => $productCode]);
            }
        });
    }

    public function down(): void
    {
        //
    }

    private function uniqueCompactProductCode(string $baseCode, array &$usedCodes): string
    {
        $baseCode = $baseCode !== '' ? $baseCode : 'PART';
        $candidate = $baseCode;
        $suffix = 2;

        while (in_array($candidate, $usedCodes, true)) {
            $candidate = $baseCode.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        $usedCodes[] = $candidate;

        return $candidate;
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

    private function yearCode(int|string|null $year): string
    {
        if (! $year) {
            return '';
        }

        return substr((string) $year, -2);
    }

    private function engineCode(?string $engineSize): string
    {
        $value = Str::of($engineSize ?? '')
            ->lower()
            ->replace('cc', '')
            ->replace('l', '')
            ->replaceMatches('/[^0-9.]/', '')
            ->toString();

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '.')) {
            return str_replace('.', '', $value);
        }

        return $value;
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
