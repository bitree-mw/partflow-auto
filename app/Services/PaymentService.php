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
    public function list(array $filters = []): Collection
    {
        return Payment::query()
            ->with(['inventoryDocument', 'paymentAccount', 'receiver'])
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

            return $this->createForDocument($document, $data, $user);
        });
    }

    public function createForDocument(InventoryDocument $document, array $data, User $user): Payment
    {
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

    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $document = InventoryDocument::query()
                ->lockForUpdate()
                ->findOrFail($payment->inventory_document_id);

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
