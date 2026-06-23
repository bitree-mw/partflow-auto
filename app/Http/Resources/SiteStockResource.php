<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteStockResource extends JsonResource
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

            'product_id' => $this->product_id,
            'site_id' => $this->site_id,

            'quantity_on_hand' => $this->quantity_on_hand,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,

            'low_stock_level' => $this->low_stock_level,
            'effective_low_stock_level' => $this->effective_low_stock_level,
            'is_low_stock' => $this->is_low_stock,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
