<?php

namespace App\Http\Requests\PaymentAccount;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentAccountRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'account_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('payment_accounts', 'account_name')->ignore($this->route('payment_account')),
            ],
            'account_type' => ['sometimes', 'required', 'string', 'in:cash,bank,mobile_money,card'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
