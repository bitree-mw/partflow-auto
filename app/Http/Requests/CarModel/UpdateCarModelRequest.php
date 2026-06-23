<?php

namespace App\Http\Requests\CarModel;

use App\Http\Requests\ApiRequest;
use App\Models\CarModel;

class UpdateCarModelRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'make' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'model' => [
                'sometimes',
                'required',
                'string',
                'max:100',
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

            $make = $this->input('make', $carModel->make);
            $model = $this->input('model', $carModel->model);
            $year = $this->input('year', $carModel->year);
            $engineSize = $this->input('engine_size', $carModel->engine_size);
            $variantName = $this->input('variant_name', $carModel->variant_name);
            $country = $this->input('country_of_origin', $carModel->country_of_origin);

            $exists = CarModel::query()
                ->where('id', '!=', $carModel->id)
                ->where('make', $make)
                ->where('model', $model)
                ->where('year', $year)
                ->where('engine_size', $engineSize)
                ->where('variant_name', $variantName)
                ->where('country_of_origin', $country)
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'model',
                    'This car make, model, year, and country of origin already exists.'
                );
            }
        });
    }
}
