<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseCategoryService
{
    public function list(array $filters = []): Collection
    {
        return ExpenseCategory::query()
            ->search($filters['search'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): ExpenseCategory
    {
        return DB::transaction(function () use ($data) {
            return ExpenseCategory::create([
                'name' => $data['name'],
                'code' => isset($data['code']) ? strtoupper($data['code']) : null,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function update(ExpenseCategory $expenseCategory, array $data): ExpenseCategory
    {
        if (array_key_exists('code', $data) && $data['code'] !== null) {
            $data['code'] = strtoupper($data['code']);
        }

        $expenseCategory->update($data);

        return $expenseCategory->refresh();
    }

    public function delete(ExpenseCategory $expenseCategory): void
    {
        $expenseCategory->delete();
    }
}
