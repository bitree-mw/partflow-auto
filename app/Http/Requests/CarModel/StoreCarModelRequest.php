<?php

namespace App\Http\Requests\CarModel;

use App\Http\Requests\ApiRequest;
use App\Models\CarModel;
use App\Models\VehicleModel;

class StoreCarModelRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'car_make_id' => [
                'required',
                'integer',
                'exists:car_makes,id',
            ],

            'vehicle_model_id' => [
                'required',
                'integer',
                'exists:vehicle_models,id',
            ],

            'year' => [
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
            $modelBelongsToMake = VehicleModel::query()
                ->whereKey($this->input('vehicle_model_id'))
                ->where('car_make_id', $this->input('car_make_id'))
                ->exists();

            if (! $modelBelongsToMake) {
                $validator->errors()->add(
                    'vehicle_model_id',
                    'Choose a model that belongs to the selected make.'
                );

                return;
            }

            $exists = CarModel::query()
                ->where('car_make_id', $this->input('car_make_id'))
                ->where('vehicle_model_id', $this->input('vehicle_model_id'))
                ->where('year', $this->input('year'))
                ->where('engine_size', $this->input('engine_size'))
                ->where('variant_name', $this->input('variant_name'))
                ->where('country_of_origin', $this->input('country_of_origin'))
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
