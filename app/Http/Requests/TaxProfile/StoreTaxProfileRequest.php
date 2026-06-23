<?php

namespace App\Http\Requests\TaxProfile;

use App\Http\Requests\ApiRequest;

class StoreTaxProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:tax_profiles,name',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9\-_]+$/',
                'unique:tax_profiles,code',
            ],

            'tax_type' => [
                'required',
                'string',
                'in:vat,none',
            ],

            'tax_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'price_mode' => [
                'required',
                'string',
                'in:inclusive,exclusive,exempt,none',
            ],

            'is_exempt' => [
                'nullable',
                'boolean',
            ],

            'exemption_reason' => [
                'nullable',
                'string',
            ],

            'is_default' => [
                'nullable',
                'boolean',
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
            'code.regex' => 'The tax profile code may only contain letters, numbers, hyphens, and underscores.',
        ];
    }
}
