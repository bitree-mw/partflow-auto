<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\ApiRequest;

class UpdateExpenseRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'expense_category_id' => ['sometimes', 'required', 'integer', 'exists:expense_categories,id'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'expense_date' => ['nullable', 'date'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
