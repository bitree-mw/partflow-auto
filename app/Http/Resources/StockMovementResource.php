<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'product' => $this->whenLoaded('product', function () {
                return new ProductResource($this->product);
            }),

            'site' => $this->whenLoaded('site', function () {
                return new SiteResource($this->site);
            }),

            'inventory_document' => $this->whenLoaded('inventoryDocument', function () {
                return [
                    'id' => $this->inventoryDocument->id,
                    'document_number' => $this->inventoryDocument->document_number,
                    'document_type' => $this->inventoryDocument->document_type,
                    'status' => $this->inventoryDocument->status,
                    'document_date' => $this->inventoryDocument->document_date?->toDateTimeString(),
                ];
            }),

            'product_id' => $this->product_id,
            'site_id' => $this->site_id,
            'movement_type' => $this->movement_type,
            'quantity_change' => $this->quantity_change,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'inventory_document_id' => $this->inventory_document_id,
            'inventory_document_item_id' => $this->inventory_document_item_id,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'created_by' => $this->created_by,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
