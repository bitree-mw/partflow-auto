<?php

namespace App\Http\Requests\FuelType;

use App\Http\Requests\ApiRequest;
use App\Models\FuelType;
use Illuminate\Validation\Rule;

class UpdateFuelTypeRequest extends ApiRequest
{
    public function rules(): array
    {
        $fuelType = $this->route('fuel_type');
        $fuelTypeId = $fuelType instanceof FuelType ? $fuelType->id : $fuelType;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('fuel_types', 'name')->ignore($fuelTypeId),
            ],

            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('fuel_types', 'code')->ignore($fuelTypeId),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The fuel type code may only contain letters and numbers.',
        ];
    }
}
