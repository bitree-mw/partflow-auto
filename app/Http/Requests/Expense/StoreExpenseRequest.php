<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\ApiRequest;

class StoreExpenseRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'expense_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
