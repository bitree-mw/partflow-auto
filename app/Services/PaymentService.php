<?php

namespace App\Services;

use App\Models\InventoryDocument;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function list(array $filters = [], ?User $user = null): Collection
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters);
        }

        return Payment::query()
            ->with(['inventoryDocument', 'paymentAccount', 'receiver'])
            ->when(array_key_exists('site_ids', $filters), function ($query) use ($filters) {
                $query->whereHas('inventoryDocument', fn ($query) => $query->forSites($filters['site_ids']));
            })
            ->when(isset($filters['inventory_document_id']), function ($query) use ($filters) {
                $query->where('inventory_document_id', $filters['inventory_document_id']);
            })
            ->when(isset($filters['payment_account_id']), function ($query) use ($filters) {
                $query->where('payment_account_id', $filters['payment_account_id']);
            })
            ->when(isset($filters['payment_method']), function ($query) use ($filters) {
                $query->where('payment_method', $filters['payment_method']);
            })
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('payment_date')
            ->get();
    }

    public function create(array $data, User $user): Payment
    {
        return DB::transaction(function () use ($data, $user) {
            $document = InventoryDocument::query()
                ->lockForUpdate()
                ->findOrFail($data['inventory_document_id']);

            $this->siteAccessService->authorizeInventoryDocumentOperation($user, $document);

            return $this->createForDocument($document, $data, $user);
        });
    }

    public function createForDocument(InventoryDocument $document, array $data, User $user): Payment
    {
        $this->siteAccessService->authorizeInventoryDocumentOperation($user, $document);

        $account = PaymentAccount::findOrFail($data['payment_account_id']);
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must be greater than zero.'],
            ]);
        }

        $currentPaid = (float) $document->payments()->sum('amount');
        $totalAmount = (float) $document->total_amount;

        if ($currentPaid + $amount > $totalAmount + 0.01) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount exceeds the document balance.'],
            ]);
        }

        $payment = Payment::create([
            'inventory_document_id' => $document->id,
            'payment_account_id' => $account->id,
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? $account->account_type,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'payment_date' => $data['payment_date'] ?? now(),
            'received_by' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->refreshDocumentPaymentStatus($document);

        return $payment->load(['inventoryDocument', 'paymentAccount', 'receiver']);
    }

    public function delete(Payment $payment, User $user): void
    {
        DB::transaction(function () use ($payment, $user) {
            $document = InventoryDocument::query()
                ->lockForUpdate()
                ->findOrFail($payment->inventory_document_id);

            $this->siteAccessService->authorizeInventoryDocumentOperation($user, $document);

            $payment->delete();
            $this->refreshDocumentPaymentStatus($document);
        });
    }

    public function refreshDocumentPaymentStatus(InventoryDocument $document): InventoryDocument
    {
        $paidAmount = round((float) $document->payments()->sum('amount'), 2);
        $totalAmount = round((float) $document->total_amount, 2);
        $balanceAmount = max(0, round($totalAmount - $paidAmount, 2));

        $paymentStatus = match (true) {
            $totalAmount <= 0 => 'paid',
            $paidAmount <= 0 => 'unpaid',
            $paidAmount + 0.01 >= $totalAmount => 'paid',
            default => 'partial',
        };

        $document->forceFill([
            'paid_amount' => min($paidAmount, $totalAmount),
            'balance_amount' => $balanceAmount,
            'payment_status' => $paymentStatus,
        ])->save();

        return $document->refresh();
    }
}
