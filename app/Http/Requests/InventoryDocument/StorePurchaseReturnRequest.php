<?php

namespace App\Http\Requests\InventoryDocument;

use App\Http\Requests\ApiRequest;

class StorePurchaseReturnRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'source_site_id' => ['required', 'integer', 'exists:sites,id'],
            'document_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,completed'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
