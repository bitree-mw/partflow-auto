<?php

namespace App\Services;

use App\Models\CarModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class CarModelService
{
    public function list(array $filters = []): Collection
    {
        return CarModel::query()
            ->search($filters['search'] ?? null)
            ->forMake($filters['make'] ?? null)
            ->year(isset($filters['year']) ? (int) $filters['year'] : null)
            ->engineSize($filters['engine_size'] ?? null)
            ->when(isset($filters['country_of_origin']), function ($query) use ($filters) {
                $query->where('country_of_origin', $filters['country_of_origin']);
            })
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('make')
            ->orderBy('model')
            ->orderByDesc('year')
            ->get();
    }

    public function create(array $data): CarModel
    {
        return CarModel::create([
            'make' => $data['make'],
            'make_code' => $this->generateMakeCode($data['make']),
            'model' => $data['model'],
            'model_code' => $this->generateModelCode($data['model']),
            'year' => $data['year'],
            'engine_size' => $data['engine_size'] ?? null,
            'variant_name' => $data['variant_name'] ?? null,
            'country_of_origin' => $data['country_of_origin'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(CarModel $carModel, array $data): CarModel
    {
        if (isset($data['make'])) {
            $data['make_code'] = $this->generateMakeCode($data['make']);
        }

        if (isset($data['model'])) {
            $data['model_code'] = $this->generateModelCode($data['model']);
        }

        $carModel->update($data);

        return $carModel->refresh();
    }

    public function delete(CarModel $carModel): void
    {
        $carModel->delete();
    }

    private function generateMakeCode(string $make): string
    {
        return Str::of($make)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, 3)
            ->toString();
    }

    private function generateModelCode(string $model): string
    {
        return Str::of($model)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, 4)
            ->toString();
    }
}
