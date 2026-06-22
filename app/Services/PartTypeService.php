<?php

namespace App\Services;

use App\Models\PartType;
use Illuminate\Database\Eloquent\Collection;

class PartTypeService
{
    public function list(array $filters = []): Collection
    {
        return PartType::query()
            ->search($filters['search'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): PartType
    {
        return PartType::create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(PartType $partType, array $data): PartType
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $partType->update($data);

        return $partType->refresh();
    }

    public function delete(PartType $partType): void
    {
        $partType->delete();
    }
}
