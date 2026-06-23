<?php

namespace App\Services;

use App\Models\CarModel;
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
            $productCode = ! empty($data['product_code'])
                ? strtoupper($data['product_code'])
                : $this->generateProductCode($data);

            $this->ensureProductCodeIsAvailable($productCode);

            $product = Product::create([
                'product_code' => $productCode,
                'product_name' => $data['product_name'] ?? $this->generateProductName($data),
                'car_model_id' => $data['car_model_id'],
                'part_type_id' => $data['part_type_id'],
                'fuel_type_id' => $data['fuel_type_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'tax_profile_id' => $data['tax_profile_id'] ?? null,
                'part_country_of_origin' => $data['part_country_of_origin'] ?? null,
                'main_image_path' => $data['main_image_path'] ?? null,
                'description' => $data['description'] ?? null,
                'default_purchase_price' => $data['default_purchase_price'] ?? 0,
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
            $shouldRegenerateCode =
                empty($data['product_code']) &&
                (
                    array_key_exists('car_model_id', $data) ||
                    array_key_exists('part_type_id', $data) ||
                    array_key_exists('fuel_type_id', $data)
                );

            if (! empty($data['product_code'])) {
                $productCode = strtoupper($data['product_code']);
                $this->ensureProductCodeIsAvailable($productCode, $product->id);
                $data['product_code'] = $productCode;
            }

            if ($shouldRegenerateCode) {
                $mergedData = array_merge($product->toArray(), $data);
                $generatedCode = $this->generateProductCode($mergedData);

                $this->ensureProductCodeIsAvailable($generatedCode, $product->id);

                $data['product_code'] = $generatedCode;
            }

            $shouldRegenerateName =
                ! array_key_exists('product_name', $data) &&
                (
                    array_key_exists('car_model_id', $data) ||
                    array_key_exists('part_type_id', $data) ||
                    array_key_exists('fuel_type_id', $data)
                );

            if ($shouldRegenerateName) {
                $mergedData = array_merge($product->toArray(), $data);
                $data['product_name'] = $this->generateProductName($mergedData);
            }

            $references = $data['references'] ?? null;
            $compatibilities = $data['compatibilities'] ?? null;

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

    private function generateProductCode(array $data): string
    {
        $carModel = CarModel::findOrFail($data['car_model_id']);
        $partType = PartType::findOrFail($data['part_type_id']);

        $fuelType = null;

        if (! empty($data['fuel_type_id'])) {
            $fuelType = FuelType::find($data['fuel_type_id']);
        }

        $makeCode = $this->cleanCode($carModel->make_code);
        $modelCode = $this->cleanCode($carModel->model_code);
        $yearCode = substr((string) $carModel->year, -2);
        $partCode = $this->cleanCode($partType->code);

        $baseCode = $makeCode.$modelCode.$yearCode.$partCode;

        $fuelSuffix = $fuelType?->code
            ? '-'.$this->cleanCode($fuelType->code)
            : '';

        return strtoupper($baseCode.$fuelSuffix);
    }

    private function generateProductName(array $data): string
    {
        $carModel = CarModel::findOrFail($data['car_model_id']);
        $partType = PartType::findOrFail($data['part_type_id']);

        $fuelName = null;

        if (! empty($data['fuel_type_id'])) {
            $fuelName = FuelType::find($data['fuel_type_id'])?->name;
        }

        $name = "{$carModel->make} {$carModel->model} {$carModel->year} {$partType->name}";

        if ($fuelName && strtolower($fuelName) !== 'universal') {
            $name .= " ({$fuelName})";
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
            ->unique('car_model_id')
            ->each(function (array $compatibility) use ($product) {
                $product->compatibilities()->create([
                    'car_model_id' => $compatibility['car_model_id'],
                    'notes' => $compatibility['notes'] ?? null,
                ]);
            });
    }

    private function cleanCode(?string $value): string
    {
        return Str::of($value ?? '')
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->toString();
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
