<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentAccount;
use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function list(array $filters = [], ?User $user = null): Collection
    {
        return $this->query($filters, $user)->get();
    }

    public function paginate(array $filters, User $user, int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters, $user)->paginate($perPage)->withQueryString();
    }

    /**
     * Totals for the filtered expenses, calculated in the database.
     *
     * @return array{total: float, count: int, top_category: ?string, top_category_total: float}
     */
    public function summary(array $filters, User $user): array
    {
        $base = $this->query($filters, $user)->reorder();
        $topCategory = (clone $base)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.name')
            ->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')
            ->orderByDesc('total')
            ->first();

        return [
            'total' => round((float) (clone $base)->sum('expenses.amount'), 2),
            'count' => (clone $base)->count(),
            'top_category' => $topCategory?->name,
            'top_category_total' => round((float) ($topCategory?->total ?? 0), 2),
        ];
    }

    /**
     * Choices for the expense form and filters, limited to what the user may use.
     */
    public function formOptions(User $user): array
    {
        $siteIds = $this->siteAccessService->allowedSiteIds($user);

        return [
            'categories' => ExpenseCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'paymentAccounts' => PaymentAccount::query()->active()->orderBy('account_name')->get(['id', 'account_name', 'account_type']),
            'sites' => Site::query()->active()->whereIn('id', $siteIds)->orderBy('name')->get(['id', 'name', 'code']),
            // Expenses without a branch are visible only to system administrators (see SiteAccessService).
            'canUseNoSite' => $this->siteAccessService->isSystemAdministrator($user),
        ];
    }

    public function createCategory(array $data): ExpenseCategory
    {
        return ExpenseCategory::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);
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

    private function query(array $filters, ?User $user): Builder
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters);
        }

        return Expense::query()
            ->with(['expenseCategory', 'paymentAccount', 'site', 'creator'])
            ->when(isset($filters['expense_category_id']), function ($query) use ($filters) {
                $query->where('expenses.expense_category_id', $filters['expense_category_id']);
            })
            ->when(isset($filters['payment_account_id']), function ($query) use ($filters) {
                $query->where('expenses.payment_account_id', $filters['payment_account_id']);
            })
            ->when(isset($filters['site_id']), function ($query) use ($filters) {
                $query->where('expenses.site_id', $filters['site_id']);
            })
            ->when(array_key_exists('site_ids', $filters), function ($query) use ($filters) {
                if ($filters['include_unassigned_site'] ?? false) {
                    $query->where(function ($query) use ($filters) {
                        $query->whereNull('expenses.site_id')->orWhereIn('expenses.site_id', $filters['site_ids']);
                    });

                    return;
                }

                $query->whereIn('expenses.site_id', $filters['site_ids']);
            })
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('expense_date')
            ->latest('id');
    }
}
