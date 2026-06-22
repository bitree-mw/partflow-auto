<?php

namespace App\Http\Requests\FuelType;

use App\Http\Requests\ApiRequest;

class StoreFuelTypeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:fuel_types,name',
            ],

            'code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[A-Za-z0-9]+$/',
                'unique:fuel_types,code',
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
