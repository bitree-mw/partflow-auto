<?php

namespace App\Http\Requests\InventoryDocument;

use App\Http\Requests\ApiRequest;
use App\Services\SiteAccessService;

class StorePurchaseRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->canAccessSiteInput('destination_site_id', SiteAccessService::RECEIVE_STOCK);
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'destination_site_id' => ['required', 'integer', 'exists:sites,id'],
            'document_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,completed'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'items.*.notes' => ['nullable', 'string'],

            'payment' => ['nullable', 'array'],
            'payment.payment_account_id' => ['required_with:payment.amount', 'integer', 'exists:payment_accounts,id'],
            'payment.amount' => ['required_with:payment', 'numeric', 'min:0.01'],
            'payment.payment_method' => ['nullable', 'string', 'in:cash,bank,mobile_money,card'],
            'payment.transaction_reference' => ['nullable', 'string', 'max:255'],
            'payment.payment_date' => ['nullable', 'date'],
            'payment.notes' => ['nullable', 'string'],
        ];
    }
}
