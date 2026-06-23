<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'inventory_document' => $this->whenLoaded('inventoryDocument', function () {
                return [
                    'id' => $this->inventoryDocument->id,
                    'document_number' => $this->inventoryDocument->document_number,
                    'document_type' => $this->inventoryDocument->document_type,
                    'total_amount' => (float) $this->inventoryDocument->total_amount,
                    'paid_amount' => (float) $this->inventoryDocument->paid_amount,
                    'balance_amount' => (float) $this->inventoryDocument->balance_amount,
                    'payment_status' => $this->inventoryDocument->payment_status,
                ];
            }),

            'payment_account' => $this->whenLoaded('paymentAccount', function () {
                return new PaymentAccountResource($this->paymentAccount);
            }),

            'received_by_user' => $this->whenLoaded('receiver', function () {
                return new UserResource($this->receiver);
            }),

            'inventory_document_id' => $this->inventory_document_id,
            'payment_account_id' => $this->payment_account_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'transaction_reference' => $this->transaction_reference,
            'payment_date' => $this->payment_date?->toDateTimeString(),
            'received_by' => $this->received_by,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
