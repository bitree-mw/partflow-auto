<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,
            'code' => $this->code,

            'tax_type' => $this->tax_type,
            'tax_rate' => (float) $this->tax_rate,
            'price_mode' => $this->price_mode,

            'is_exempt' => $this->is_exempt,
            'exemption_reason' => $this->exemption_reason,

            'is_default' => $this->is_default,
            'is_active' => $this->is_active,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
