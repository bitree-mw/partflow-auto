<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\ApiRequest;

class StorePaymentRequest extends ApiRequest
{
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
