<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'document_type' => $this->document_type,

            'contact' => $this->whenLoaded('contact', function () {
                return new ContactResource($this->contact);
            }),

            'source_site' => $this->whenLoaded('sourceSite', function () {
                return new SiteResource($this->sourceSite);
            }),

            'destination_site' => $this->whenLoaded('destinationSite', function () {
                return new SiteResource($this->destinationSite);
            }),

            'created_by_user' => $this->whenLoaded('creator', function () {
                return new UserResource($this->creator);
            }),

            'approved_by_user' => $this->whenLoaded('approver', function () {
                return $this->approver ? new UserResource($this->approver) : null;
            }),

            'contact_id' => $this->contact_id,
            'source_site_id' => $this->source_site_id,
            'destination_site_id' => $this->destination_site_id,
            'document_date' => $this->document_date?->toDateTimeString(),
            'status' => $this->status,

            'subtotal_amount' => (float) $this->subtotal_amount,
            'discount_amount' => (float) $this->discount_amount,
            'taxable_amount' => (float) $this->taxable_amount,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'balance_amount' => (float) $this->balance_amount,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,

            'items' => InventoryDocumentItemResource::collection(
                $this->whenLoaded('items')
            ),

            'payments' => PaymentResource::collection(
                $this->whenLoaded('payments')
            ),

            'stock_movements' => StockMovementResource::collection(
                $this->whenLoaded('stockMovements')
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
