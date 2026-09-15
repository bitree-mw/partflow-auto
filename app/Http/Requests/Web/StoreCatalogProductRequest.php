<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $compatibleCarModelIds = $this->input('compatible_car_model_ids');

        if (! is_array($compatibleCarModelIds) && $this->filled('car_model_id')) {
            $compatibleCarModelIds = [$this->input('car_model_id')];
        }

        $this->merge([
            'compatible_car_model_ids' => collect($compatibleCarModelIds ?? [])
                ->filter(fn (mixed $id): bool => filled($id))
                ->map(fn (int|string $id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'product_code' => ['nullable', 'string', 'max:100', 'unique:products,product_code'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'car_model_id' => ['nullable', 'integer', 'exists:car_models,id'],
            'product_type_id' => ['required', 'integer', 'exists:product_types,id'],
            'fuel_type_id' => ['nullable', 'integer', 'exists:fuel_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'part_country_of_origin' => ['nullable', 'string', 'max:100'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_selling_price' => ['nullable', 'numeric', 'min:0', 'lte:default_selling_price'],
            'default_low_stock_level' => ['nullable', 'integer', 'min:0'],
            'pack_size' => ['nullable', 'numeric', 'min:0.01'],
            'compatible_car_model_ids' => ['nullable', 'array'],
            'compatible_car_model_ids.*' => ['required', 'integer', 'distinct', 'exists:car_models,id'],
            'compatibility_notes' => ['nullable', 'string'],
        ];
    }
}
