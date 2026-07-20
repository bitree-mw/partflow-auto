<?php

namespace App\Http\Requests\ProductType;

use App\Http\Requests\ApiRequest;
use App\Models\ProductType;
use Illuminate\Validation\Rule;

class UpdateProductTypeRequest extends ApiRequest
{
    public function rules(): array
    {
        $productType = $this->route('product_type');
        $productTypeId = $productType instanceof ProductType ? $productType->id : $productType;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('product_types', 'code')->ignore($productTypeId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The product type code may only contain letters and numbers.',
        ];
    }
}
