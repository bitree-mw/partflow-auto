<?php

namespace App\Services;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Collection;

class ProductTypeService
{
    public function list(array $filters = []): Collection
    {
        return ProductType::query()
            ->search($filters['search'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): ProductType
    {
        return ProductType::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(ProductType $productType, array $data): ProductType
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $productType->update($data);

        return $productType->refresh();
    }

    public function delete(ProductType $productType): void
    {
        $productType->delete();
    }
}
