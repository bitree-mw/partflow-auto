<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'payment_account_id' => ['nullable', 'integer', Rule::exists('payment_accounts', 'id')->where('is_active', true)],
            // Branch access is checked by ExpenseService; blank means a business-wide expense (administrators only).
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')->whereNull('deleted_at')],
            'expense_date' => ['required', 'date', 'before_or_equal:now'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999'],
            'description' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'expense_category_id' => 'category',
            'payment_account_id' => 'paid from account',
            'site_id' => 'branch',
        ];
    }
}
