<?php

namespace App\Http\Requests\Web;

use App\Models\Product;
use Illuminate\Validation\Rule;

class UpdateCatalogProductRequest extends StoreCatalogProductRequest
{
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : $product;
        $rules = parent::rules();

        $rules['product_code'] = [
            'nullable',
            'string',
            'max:100',
            Rule::unique('products', 'product_code')->ignore($productId),
        ];
        $rules['minimum_selling_price'] = ['nullable', 'numeric', 'min:0'];
        $rules['is_active'] = ['nullable', 'boolean'];

        return $rules;
    }
}
