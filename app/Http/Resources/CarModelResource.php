<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class CarModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayName = collect([
            $this->make,
            $this->model,
            $this->year,
            $this->engine_size,
            $this->variant_name,
        ])->filter()->join(' ');

        return [
            'id' => $this->id,

            'make' => $this->make,
            'make_code' => $this->make_code,

            'model' => $this->model,
            'model_code' => $this->model_code,

            'year' => $this->year,
            'engine_size' => $this->engine_size,
            'variant_name' => $this->variant_name,
            'country_of_origin' => $this->country_of_origin,

            'display_name' => Str::squish($displayName),

            'notes' => $this->notes,
            'is_active' => $this->is_active,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
