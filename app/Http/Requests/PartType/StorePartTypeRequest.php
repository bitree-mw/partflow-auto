<?php

namespace App\Http\Requests\PartType;

use App\Http\Requests\ApiRequest;

class StorePartTypeRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
                'unique:part_types,code',
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
            'code.regex' => 'The part type code may only contain letters and numbers.',
        ];
    }
}
