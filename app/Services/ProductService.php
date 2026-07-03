<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\FuelType;
use App\Models\PartType;
use App\Models\Product;
use App\Models\ProductReference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function list(array $filters = []): Collection
    {
        return Product::query()
            ->with([
                'carModel',
                'partType',
                'fuelType',
                'brand',
                'taxProfile',
                'references',
                'compatibilities.carModel',
            ])
            ->search($filters['search'] ?? null)
            ->when(isset($filters['car_model_id']), function ($query) use ($filters) {
                $query->where('car_model_id', $filters['car_model_id']);
            })
            ->when(isset($filters['compatible_car_model_id']), function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('car_model_id', $filters['compatible_car_model_id'])
                        ->orWhereHas('compatibilities', function ($query) use ($filters) {
                            $query->where('car_model_id', $filters['compatible_car_model_id']);
                        });
                });
            })
            ->when(isset($filters['part_type_id']), function ($query) use ($filters) {
                $query->where('part_type_id', $filters['part_type_id']);
            })
            ->when(isset($filters['fuel_type_id']), function ($query) use ($filters) {
                $query->where('fuel_type_id', $filters['fuel_type_id']);
            })
            ->when(isset($filters['brand_id']), function ($query) use ($filters) {
                $query->where('brand_id', $filters['brand_id']);
            })
            ->when(isset($filters['tax_profile_id']), function ($query) use ($filters) {
                $query->where('tax_profile_id', $filters['tax_profile_id']);
            })
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('product_name')
            ->get();
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $data['brand_id'] = $data['brand_id'] ?? $this->unknownBrandId();
            $data['car_model_id'] = $data['car_model_id'] ?? $this->primaryCompatibilityId($data['compatibilities'] ?? []);

            $productCode = ! empty($data['product_code'])
                ? $this->normalizeManualProductCode($data['product_code'])
                : $this->generateProductCode($data);

            $this->ensureProductCodeIsAvailable($productCode);

            $product = Product::create([
                'product_code' => $productCode,
                'product_name' => $data['product_name'] ?? $this->generateProductName($data),
                'car_model_id' => $data['car_model_id'],
                'part_type_id' => $data['part_type_id'],
                'fuel_type_id' => $data['fuel_type_id'] ?? null,
                'brand_id' => $data['brand_id'],
                'tax_profile_id' => $data['tax_profile_id'] ?? null,
                'part_country_of_origin' => $data['part_country_of_origin'] ?? null,
                'main_image_path' => $data['main_image_path'] ?? null,
                'description' => $data['description'] ?? null,
                'pos_description' => null,
                'default_purchase_price' => 0,
                'default_selling_price' => $data['default_selling_price'] ?? 0,
                'default_low_stock_level' => $data['default_low_stock_level'] ?? 0,
                'unit_name' => $data['unit_name'] ?? 'piece',
                'pack_size' => $data['pack_size'] ?? 1,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncReferences($product, $data['references'] ?? []);
            $this->syncCompatibilities($product, $data['compatibilities'] ?? []);

            return $product->load($this->defaultRelations());
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            if (array_key_exists('brand_id', $data) && empty($data['brand_id'])) {
                $data['brand_id'] = $this->unknownBrandId();
            }

            $shouldRegenerateCode =
                empty($data['product_code']) &&
                (
                    array_key_exists('car_model_id', $data) ||
                    array_key_exists('part_type_id', $data) ||
                    array_key_exists('fuel_type_id', $data) ||
                    array_key_exists('brand_id', $data) ||
                    array_key_exists('part_country_of_origin', $data)
                );

            if (! empty($data['product_code'])) {
                $productCode = $this->normalizeManualProductCode($data['product_code']);
                $this->ensureProductCodeIsAvailable($productCode, $product->id);
                $data['product_code'] = $productCode;
            }

            if ($shouldRegenerateCode) {
                $mergedData = array_merge($product->toArray(), $data);
                $generatedCode = $this->generateProductCode($mergedData, $product->id);

                $this->ensureProductCodeIsAvailable($generatedCode, $product->id);

                $data['product_code'] = $generatedCode;
            }

            $shouldRegenerateName =
                ! array_key_exists('product_name', $data) &&
                (
                    array_key_exists('car_model_id', $data) ||
                    array_key_exists('part_type_id', $data) ||
                    array_key_exists('fuel_type_id', $data) ||
                    array_key_exists('brand_id', $data) ||
                    array_key_exists('part_country_of_origin', $data)
                );

            if ($shouldRegenerateName) {
                $mergedData = array_merge($product->toArray(), $data);
                $data['product_name'] = $this->generateProductName($mergedData);
            }

            unset($data['default_purchase_price'], $data['pos_description']);

            $references = $data['references'] ?? null;
            $compatibilities = $data['compatibilities'] ?? null;

            if (is_array($compatibilities) && empty($data['car_model_id'])) {
                $data['car_model_id'] = $this->primaryCompatibilityId($compatibilities);
            }

            unset($data['references'], $data['compatibilities']);

            $product->update($data);

            if (is_array($references)) {
                $this->syncReferences($product, $references);
            }

            if (is_array($compatibilities)) {
                $this->syncCompatibilities($product, $compatibilities);
            }

            return $product->refresh()->load($this->defaultRelations());
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    private function generateProductCode(array $data, ?int $ignoreProductId = null): string
    {
        $brand = Brand::find($data['brand_id'] ?? null) ?? Brand::find($this->unknownBrandId());
        $partType = PartType::findOrFail($data['part_type_id']);
        $brandCode = $this->brandCodePrefix($brand?->id);
        $partCode = $this->cleanCode($partType->code);
        $countryCode = $this->countryCode($data['part_country_of_origin'] ?? null);

        return $this->nextProductCode("{$brandCode}-{$partCode}-{$countryCode}", $ignoreProductId);
    }

    private function normalizeManualProductCode(string $productCode): string
    {
        $code = Str::of($productCode)
            ->upper()
            ->replaceMatches('/[^A-Z0-9\-]/', '')
            ->trim('-')
            ->toString();

        return $code !== '' ? $code : 'PART';
    }

    private function brandCodePrefix(?int $brandId): string
    {
        $brandCode = $brandId ? Brand::query()->whereKey($brandId)->value('code') : null;
        $prefix = Str::of($brandCode ?: 'GEN')
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, 4)
            ->padRight(4, 'X')
            ->toString();

        return $prefix !== '' ? $prefix : 'GENX';
    }

    private function nextProductCode(string $baseCode, ?int $ignoreProductId = null): string
    {
        $nextNumber = 1;

        Product::query()
            ->where('product_code', 'like', "{$baseCode}-%")
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->pluck('product_code')
            ->each(function (string $code) use (&$nextNumber): void {
                $number = (int) Str::of($code)->afterLast('-')->toString();

                if ($number >= $nextNumber) {
                    $nextNumber = $number + 1;
                }
            });

        do {
            $candidate = "{$baseCode}-".str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Product::query()
            ->where('product_code', $candidate)
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists());

        return $candidate;
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

    private function generateProductName(array $data): string
    {
        $brand = Brand::find($data['brand_id'] ?? null) ?? Brand::find($this->unknownBrandId());
        $partType = PartType::findOrFail($data['part_type_id']);

        $fuelName = null;

        if (! empty($data['fuel_type_id'])) {
            $fuelName = FuelType::find($data['fuel_type_id'])?->name;
        }

        $nameParts = [
            $brand?->name,
            $partType->name,
        ];

        $name = collect($nameParts)
            ->filter()
            ->join(' ');

        $name .= ' ('.$this->countryCode($data['part_country_of_origin'] ?? null).')';

        if ($fuelName && ! in_array(strtolower($fuelName), ['universal', 'not set'], true)) {
            $name .= " - {$fuelName}";
        }

        return Str::of($name)->squish()->toString();
    }

    private function ensureProductCodeIsAvailable(string $productCode, ?int $ignoreProductId = null): void
    {
        $exists = Product::query()
            ->where('product_code', strtoupper($productCode))
            ->when($ignoreProductId, function ($query) use ($ignoreProductId) {
                $query->where('id', '!=', $ignoreProductId);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'product_code' => ['This product code already exists.'],
            ]);
        }
    }

    private function syncReferences(Product $product, array $references): void
    {
        ProductReference::query()
            ->where('product_id', $product->id)
            ->forceDelete();

        foreach ($references as $reference) {
            $product->references()->create([
                'reference_type' => $reference['reference_type'],
                'reference_value' => strtoupper(trim($reference['reference_value'])),
                'is_primary' => $reference['is_primary'] ?? false,
                'notes' => $reference['notes'] ?? null,
            ]);
        }
    }

    private function syncCompatibilities(Product $product, array $compatibilities): void
    {
        $product->compatibilities()->delete();

        collect($compatibilities)
            ->reject(fn (array $compatibility) => (int) ($compatibility['car_model_id'] ?? 0) === (int) $product->car_model_id)
            ->unique('car_model_id')
            ->each(function (array $compatibility) use ($product) {
                $product->compatibilities()->create([
                    'car_model_id' => $compatibility['car_model_id'],
                    'notes' => $compatibility['notes'] ?? null,
                ]);
            });
    }

    private function primaryCompatibilityId(array $compatibilities): ?int
    {
        return collect($compatibilities)
            ->pluck('car_model_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->first();
    }

    private function cleanCode(?string $value): string
    {
        $code = Str::of($value ?? '')
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->toString();

        return $code !== '' ? $code : 'PT';
    }

    private function unknownBrandId(): int
    {
        return Brand::query()->firstOrCreate(
            ['code' => 'UNKN'],
            [
                'name' => 'Unknown',
                'country' => null,
                'description' => 'Fallback brand for parts whose manufacturer is not known.',
                'is_active' => true,
            ]
        )->id;
    }

    private function defaultRelations(): array
    {
        return [
            'carModel',
            'partType',
            'fuelType',
            'brand',
            'taxProfile',
            'references',
            'compatibilities.carModel',
        ];
    }
}
