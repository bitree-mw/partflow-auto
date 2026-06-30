<?php

namespace App\Services;

use App\Models\CarModel;
use App\Models\CarMake;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Collection;

class CarModelService
{
    public function list(array $filters = []): Collection
    {
        return CarModel::query()
            ->with(['carMake', 'vehicleModel'])
            ->search($filters['search'] ?? null)
            ->forMake($filters['make'] ?? null)
            ->forCarMake(isset($filters['car_make_id']) ? (int) $filters['car_make_id'] : null)
            ->forVehicleModel(isset($filters['vehicle_model_id']) ? (int) $filters['vehicle_model_id'] : null)
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
        $make = CarMake::query()->findOrFail($data['car_make_id']);
        $model = VehicleModel::query()
            ->where('car_make_id', $make->id)
            ->findOrFail($data['vehicle_model_id']);
        $data['engine_size'] = $this->normalizeEngineSize($data['engine_size'] ?? null);

        return CarModel::create([
            'car_make_id' => $make->id,
            'vehicle_model_id' => $model->id,
            'make' => $make->name,
            'make_code' => $make->code,
            'model' => $model->name,
            'model_code' => $model->code,
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
        if (array_key_exists('engine_size', $data)) {
            $data['engine_size'] = $this->normalizeEngineSize($data['engine_size']);
        }

        if (isset($data['car_make_id']) || isset($data['vehicle_model_id'])) {
            $make = CarMake::query()->findOrFail($data['car_make_id'] ?? $carModel->car_make_id);
            $model = VehicleModel::query()
                ->where('car_make_id', $make->id)
                ->findOrFail($data['vehicle_model_id'] ?? $carModel->vehicle_model_id);

            $data['car_make_id'] = $make->id;
            $data['vehicle_model_id'] = $model->id;
            $data['make'] = $make->name;
            $data['make_code'] = $make->code;
            $data['model'] = $model->name;
            $data['model_code'] = $model->code;
        }

        $carModel->update($data);

        return $carModel->refresh();
    }

    public function delete(CarModel $carModel): void
    {
        $carModel->delete();
    }

    private function normalizeEngineSize(mixed $engineSize): ?string
    {
        $value = trim((string) $engineSize);

        if ($value === '') {
            return null;
        }

        $numeric = (float) preg_replace('/[^0-9.]/', '', $value);

        if ($numeric > 0 && $numeric < 100) {
            $numeric *= 1000;
        }

        $number = rtrim(rtrim(number_format($numeric, 1, '.', ''), '0'), '.');

        return $number === '' ? null : "{$number}cc";
    }
}
