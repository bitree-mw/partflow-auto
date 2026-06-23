<?php

namespace App\Http\Requests\InventoryDocument;

use App\Http\Requests\ApiRequest;

class StoreTransferRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'source_site_id' => ['required', 'integer', 'exists:sites,id', 'different:destination_site_id'],
            'destination_site_id' => ['required', 'integer', 'exists:sites,id'],
            'document_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,completed'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
