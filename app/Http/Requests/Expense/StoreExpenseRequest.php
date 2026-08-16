<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\ApiRequest;
use App\Services\SiteAccessService;

class StoreExpenseRequest extends ApiRequest
{
    public function authorize(): bool
    {
        if ($this->filled('site_id')) {
            return $this->canAccessSiteInput('site_id');
        }

        return $this->user()
            && app(SiteAccessService::class)->isSystemAdministrator($this->user());
    }

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
