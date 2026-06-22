<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class BrandService
{
    public function list(array $filters = []): Collection
    {
        return Brand::query()
            ->search($filters['search'] ?? null)
            ->country($filters['country'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Brand
    {
        return Brand::create([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code'] ?? $data['name']),
            'country' => $data['country'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(Brand $brand, array $data): Brand
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = $this->normalizeCode($data['code']);
        }

        $brand->update($data);

        return $brand->refresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return Str::of($code)
            ->upper()
            ->replaceMatches('/[^A-Z0-9\-]/', '')
            ->substr(0, 50)
            ->toString();
    }
}
