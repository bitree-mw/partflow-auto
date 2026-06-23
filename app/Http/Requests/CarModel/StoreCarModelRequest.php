<?php

namespace App\Http\Requests\CarModel;

use App\Http\Requests\ApiRequest;
use App\Models\CarModel;

class StoreCarModelRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'make' => [
                'required',
                'string',
                'max:100',
            ],

            'model' => [
                'required',
                'string',
                'max:100',
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
            $exists = CarModel::query()
                ->where('make', $this->input('make'))
                ->where('model', $this->input('model'))
                ->where('year', $this->input('year'))
                ->where('engine_size', $this->input('engine_size'))
                ->where('variant_name', $this->input('variant_name'))
                ->where('country_of_origin', $this->input('country_of_origin'))
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
