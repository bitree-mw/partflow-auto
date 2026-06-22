<?php

namespace App\Http\Requests\Brand;

use App\Http\Requests\ApiRequest;
use App\Models\Brand;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends ApiRequest
{
    public function rules(): array
    {
        $brand = $this->route('brand');
        $brandId = $brand instanceof Brand ? $brand->id : $brand;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')->ignore($brandId),
            ],

            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9\-]+$/',
                Rule::unique('brands', 'code')->ignore($brandId),
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
            'code.regex' => 'The brand code may only contain letters, numbers, and hyphens.',
        ];
    }
}
