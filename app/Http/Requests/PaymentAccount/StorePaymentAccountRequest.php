<?php

namespace App\Http\Requests\PaymentAccount;

use App\Http\Requests\ApiRequest;

class StorePaymentAccountRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'account_name' => ['required', 'string', 'max:255', 'unique:payment_accounts,account_name'],
            'account_type' => ['required', 'string', 'in:cash,bank,mobile_money,card'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
