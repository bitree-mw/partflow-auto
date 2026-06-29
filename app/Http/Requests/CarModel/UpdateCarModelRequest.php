<?php

namespace App\Http\Requests\CarModel;

use App\Http\Requests\ApiRequest;
use App\Models\CarModel;
use App\Models\VehicleModel;

class UpdateCarModelRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'car_make_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:car_makes,id',
            ],

            'vehicle_model_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:vehicle_models,id',
            ],

            'year' => [
                'sometimes',
                'required',
                'integer',
                'min:1950',
                'max:'.((int) date('Y') + 1),
            ],

            'engine_size' => [
                'nullable',
                'string',
                'max:50',
            ],

            'variant_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'country_of_origin' => [
                'nullable',
                'string',
                'max:100',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $carModel = $this->route('car_model');

            if (! $carModel instanceof CarModel) {
                return;
            }

            $makeId = $this->input('car_make_id', $carModel->car_make_id);
            $modelId = $this->input('vehicle_model_id', $carModel->vehicle_model_id);
            $year = $this->input('year', $carModel->year);
            $engineSize = $this->input('engine_size', $carModel->engine_size);
            $variantName = $this->input('variant_name', $carModel->variant_name);
            $country = $this->input('country_of_origin', $carModel->country_of_origin);

            $modelBelongsToMake = VehicleModel::query()
                ->whereKey($modelId)
                ->where('car_make_id', $makeId)
                ->exists();

            if (! $modelBelongsToMake) {
                $validator->errors()->add(
                    'vehicle_model_id',
                    'Choose a model that belongs to the selected make.'
                );

                return;
            }

            $exists = CarModel::query()
                ->where('id', '!=', $carModel->id)
                ->where('car_make_id', $makeId)
                ->where('vehicle_model_id', $modelId)
                ->where('year', $year)
                ->where('engine_size', $engineSize)
                ->where('variant_name', $variantName)
                ->where('country_of_origin', $country)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'vehicle_model_id',
                    'This car make, model, year, engine, variant, and country of origin already exists.'
                );
            }
        });
    }
}
