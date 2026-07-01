<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $usedCodes = DB::table('products')
            ->pluck('product_code', 'id')
            ->map(fn ($code): string => strtoupper((string) $code))
            ->all();

        DB::table('products')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->select([
                'products.id',
                'products.product_code',
                'brands.code as brand_code',
            ])
            ->orderBy('products.id')
            ->chunkById(100, function ($products) use (&$usedCodes): void {
                foreach ($products as $product) {
                    unset($usedCodes[$product->id]);

                    $productCode = $this->normalizeProductCode(
                        (string) $product->product_code,
                        (string) $product->brand_code,
                        $usedCodes
                    );

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['product_code' => $productCode]);

                    $usedCodes[$product->id] = $productCode;
                }
            }, 'products.id', 'id');
    }

    public function down(): void
    {
        $usedCodes = DB::table('products')
            ->pluck('product_code', 'id')
            ->map(fn ($code): string => strtoupper((string) $code))
            ->all();

        DB::table('products')
            ->select(['id', 'product_code'])
            ->orderBy('id')
            ->chunkById(100, function ($products) use (&$usedCodes): void {
                foreach ($products as $product) {
                    unset($usedCodes[$product->id]);

                    $parts = explode('-', (string) $product->product_code, 2);

                    if (count($parts) !== 2 || strlen($parts[0]) > 4) {
                        $usedCodes[$product->id] = strtoupper((string) $product->product_code);

                        continue;
                    }

                    $productCode = $this->uniqueCode($parts[1], $usedCodes);

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['product_code' => $productCode]);

                    $usedCodes[$product->id] = $productCode;
                }
            });
    }

    private function normalizeProductCode(string $productCode, string $brandCode, array $usedCodes): string
    {
        $prefix = $this->cleanPrefix($brandCode);
        $cleaned = Str::of($productCode)
            ->upper()
            ->replaceMatches('/[^A-Z0-9\-]/', '')
            ->trim('-')
            ->toString();
        $parts = explode('-', $cleaned, 2);
        $body = count($parts) === 2 && strlen($parts[0]) <= 4
            ? $parts[1]
            : $cleaned;

        if ($body === '') {
            $body = 'PART';
        }

        return $this->uniqueCode("{$prefix}-{$body}", $usedCodes);
    }

    private function cleanPrefix(string $brandCode): string
    {
        $prefix = Str::of($brandCode ?: 'GEN')
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, 4)
            ->toString();

        return $prefix !== '' ? $prefix : 'GEN';
    }

    private function uniqueCode(string $code, array $usedCodes): string
    {
        $code = substr($code, 0, 100);
        $candidate = $code;
        $suffix = 2;

        while (in_array($candidate, $usedCodes, true)) {
            $append = "-{$suffix}";
            $candidate = substr($code, 0, 100 - strlen($append)).$append;
            $suffix++;
        }

        return $candidate;
    }
};
