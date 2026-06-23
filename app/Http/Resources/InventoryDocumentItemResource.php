<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryDocumentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'product' => $this->whenLoaded('product', function () {
                return new ProductResource($this->product);
            }),

            'inventory_document_id' => $this->inventory_document_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'unit_price' => (float) $this->unit_price,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
            'profit_amount' => (float) $this->profit_amount,
            'system_quantity' => $this->system_quantity,
            'counted_quantity' => $this->counted_quantity,
            'variance_quantity' => $this->variance_quantity,
            'notes' => $this->notes,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
