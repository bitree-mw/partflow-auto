<?php

namespace App\Services;

use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentAccountService
{
    public function list(array $filters = []): Collection
    {
        return PaymentAccount::query()
            ->search($filters['search'] ?? null)
            ->type($filters['account_type'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('account_name')
            ->get();
    }

    public function create(array $data): PaymentAccount
    {
        return DB::transaction(function () use ($data) {
            return PaymentAccount::create([
                'account_name' => $data['account_name'],
                'account_type' => $data['account_type'],
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'mobile_number' => $data['mobile_number'] ?? null,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function update(PaymentAccount $paymentAccount, array $data): PaymentAccount
    {
        $paymentAccount->update($data);

        return $paymentAccount->refresh();
    }

    public function delete(PaymentAccount $paymentAccount): void
    {
        $paymentAccount->delete();
    }
}
