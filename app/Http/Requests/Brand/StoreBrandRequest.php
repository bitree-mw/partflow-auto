<?php

namespace App\Http\Requests\Brand;

use App\Http\Requests\ApiRequest;

class StoreBrandRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:brands,name',
            ],

            'code' => [
                'nullable',
                'string',
                'max:4',
                'regex:/^[A-Za-z0-9]+$/',
                'unique:brands,code',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
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
            'code.regex' => 'The brand code may only contain letters and numbers.',
        ];
    }
}
