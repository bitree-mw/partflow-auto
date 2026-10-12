<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An admin approving an actual selling price on a cashier's till.
 */
class ApprovePosPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'admin_password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_password.required' => 'An admin needs to enter their password to approve the price.',
        ];
    }
}
