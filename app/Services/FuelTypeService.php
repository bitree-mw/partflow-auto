<?php

namespace App\Services;

use App\Models\FuelType;
use Illuminate\Database\Eloquent\Collection;

class FuelTypeService
{
    public function list(array $filters = []): Collection
    {
        return FuelType::query()
            ->search($filters['search'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): FuelType
    {
        return FuelType::create([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code'] ?? null),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(FuelType $fuelType, array $data): FuelType
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode($data['code']);
        }

        $fuelType->update($data);

        return $fuelType->refresh();
    }

    public function delete(FuelType $fuelType): void
    {
        $fuelType->delete();
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return strtoupper(trim($code));
    }
}
