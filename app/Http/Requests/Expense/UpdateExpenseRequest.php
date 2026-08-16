<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\ApiRequest;
use App\Models\Expense;
use App\Services\SiteAccessService;

class UpdateExpenseRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');
        $allowedSiteIds = $this->user()
            ? app(SiteAccessService::class)->allowedSiteIds($this->user())
            : [];

        $isAdministrator = $this->user()
            && app(SiteAccessService::class)->isSystemAdministrator($this->user());
        $canAccessExisting = $expense instanceof Expense
            && ($expense->site_id
                ? in_array($expense->site_id, $allowedSiteIds, true)
                : $isAdministrator);
        $canAccessNew = $this->filled('site_id')
            ? in_array((int) $this->input('site_id'), $allowedSiteIds, true)
            : (! $this->exists('site_id') || $isAdministrator);

        return $canAccessExisting && $canAccessNew;
    }

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
