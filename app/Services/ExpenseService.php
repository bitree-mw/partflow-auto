<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function list(array $filters = []): Collection
    {
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
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('expense_date')
            ->get();
    }

    public function create(array $data, User $user): Expense
    {
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

    public function update(Expense $expense, array $data): Expense
    {
        $expense->update($data);

        return $expense->refresh()->load(['expenseCategory', 'paymentAccount', 'site', 'creator']);
    }

    public function delete(Expense $expense): void
    {
        $expense->delete();
    }
}
