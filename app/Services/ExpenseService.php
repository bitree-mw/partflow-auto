<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function list(array $filters = [], ?User $user = null): Collection
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters);
        }

        return Expense::query()
            ->with(['expenseCategory', 'paymentAccount', 'site', 'creator'])
            ->when(isset($filters['expense_category_id']), function ($query) use ($filters) {
                $query->where('expense_category_id', $filters['expense_category_id']);
            })
            ->when(isset($filters['payment_account_id']), function ($query) use ($filters) {
                $query->where('payment_account_id', $filters['payment_account_id']);
            })
            ->when(isset($filters['site_id']), function ($query) use ($filters) {
                $query->where('site_id', $filters['site_id']);
            })
            ->when(array_key_exists('site_ids', $filters), function ($query) use ($filters) {
                if ($filters['include_unassigned_site'] ?? false) {
                    $query->where(function ($query) use ($filters) {
                        $query->whereNull('site_id')->orWhereIn('site_id', $filters['site_ids']);
                    });

                    return;
                }

                $query->whereIn('site_id', $filters['site_ids']);
            })
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('expense_date')
            ->get();
    }

    public function create(array $data, User $user): Expense
    {
        $this->siteAccessService->authorizeOptionalSite(
            $user,
            ! empty($data['site_id']) ? (int) $data['site_id'] : null
        );

        return DB::transaction(function () use ($data, $user) {
            return Expense::create([
                'expense_category_id' => $data['expense_category_id'],
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'expense_date' => $data['expense_date'] ?? now(),
                'amount' => $data['amount'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'created_by' => $user->id,
            ])->load(['expenseCategory', 'paymentAccount', 'site', 'creator']);
        });
    }

    public function update(Expense $expense, array $data, User $user): Expense
    {
        $this->siteAccessService->authorizeOptionalSite($user, $expense->site_id);
        $this->siteAccessService->authorizeOptionalSite(
            $user,
            array_key_exists('site_id', $data)
                ? (! empty($data['site_id']) ? (int) $data['site_id'] : null)
                : $expense->site_id
        );

        $expense->update($data);

        return $expense->refresh()->load(['expenseCategory', 'paymentAccount', 'site', 'creator']);
    }

    public function delete(Expense $expense, User $user): void
    {
        $this->siteAccessService->authorizeOptionalSite($user, $expense->site_id);

        $expense->delete();
    }
}
