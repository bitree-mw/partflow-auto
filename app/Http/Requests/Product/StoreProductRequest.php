<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\ApiRequest;

class StoreProductRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'product_code' => [
                'nullable',
                'string',
                'max:100',
                'unique:products,product_code',
            ],

            'product_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'car_model_id' => [
                'nullable',
                'integer',
                'exists:car_models,id',
            ],

            'product_type_id' => [
                'required',
                'integer',
                'exists:product_types,id',
            ],

            'fuel_type_id' => [
                'nullable',
                'integer',
                'exists:fuel_types,id',
            ],

            'brand_id' => [
                'nullable',
                'integer',
                'exists:brands,id',
            ],

            'tax_profile_id' => [
                'nullable',
                'integer',
                'exists:tax_profiles,id',
            ],

            'part_country_of_origin' => [
                'nullable',
                'string',
                'max:100',
            ],

            'main_image_path' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'default_selling_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'default_low_stock_level' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'unit_name' => [
                'nullable',
                'string',
                'max:50',
            ],

            'pack_size' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'references' => [
                'nullable',
                'array',
            ],

            'references.*.reference_type' => [
                'required_with:references',
                'string',
                'in:barcode,oem_number,supplier_code,aftermarket_code,other',
            ],

            'references.*.reference_value' => [
                'required_with:references',
                'string',
                'max:255',
            ],

            'references.*.is_primary' => [
                'nullable',
                'boolean',
            ],

            'references.*.notes' => [
                'nullable',
                'string',
            ],

            'compatibilities' => [
                'nullable',
                'array',
            ],

            'compatibilities.*.car_model_id' => [
                'required_with:compatibilities',
                'integer',
                'exists:car_models,id',
            ],

            'compatibilities.*.notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
