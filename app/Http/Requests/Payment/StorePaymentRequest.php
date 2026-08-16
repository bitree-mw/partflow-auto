<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\ApiRequest;
use App\Models\InventoryDocument;
use App\Services\SiteAccessService;

class StorePaymentRequest extends ApiRequest
{
    public function authorize(): bool
    {
        if (! is_numeric($this->input('inventory_document_id'))) {
            return true;
        }

        $document = InventoryDocument::query()->find($this->integer('inventory_document_id'));

        if (! $document || ! $this->user()) {
            return $document === null;
        }

        try {
            app(SiteAccessService::class)->authorizeInventoryDocumentOperation($this->user(), $document);

            return true;
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return false;
        }
    }

    public function rules(): array
    {
        return [
            'inventory_document_id' => ['required', 'integer', 'exists:inventory_documents,id'],
            'payment_account_id' => ['required', 'integer', 'exists:payment_accounts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:cash,bank,mobile_money,card'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
