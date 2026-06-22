<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'make' => $this->make,
            'make_code' => $this->make_code,
            'model' => $this->model,
            'model_code' => $this->model_code,
            'year' => $this->year,
            'country_of_origin' => $this->country_of_origin,
            'display_name' => "{$this->make} {$this->model} {$this->year}",
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
