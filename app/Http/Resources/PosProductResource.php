<?php

namespace App\Http\Resources;

use App\Models\CarModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PosProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'product_id' => $product->id,
            'product_code' => $product->product_code,
            'product_name' => $product->product_name,
            'pos_description' => $product->pos_description,
            'compatible_cars' => $this->compatibleCars(),
            'site_id' => $this->site_id,
            'site_name' => $this->site?->name,
            'quantity_on_hand' => $this->quantity_on_hand,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,
            'selling_price' => (float) $product->default_selling_price,
            'fuel_type' => $product->fuelType ? [
                'id' => $product->fuelType->id,
                'name' => $product->fuelType->name,
                'code' => $product->fuelType->code,
            ] : null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
            ] : null,
            'part_country_of_origin' => $product->part_country_of_origin,
            'tax_profile' => $product->taxProfile ? new TaxProfileResource($product->taxProfile) : null,
        ];
    }

    private function compatibleCars(): array
    {
        $product = $this->product;

        $cars = collect();

        if ($product->relationLoaded('carModel') && $product->carModel) {
            $cars->push($this->formatCarModel($product->carModel));
        }

        if ($product->relationLoaded('compatibilities')) {
            $product->compatibilities->each(function ($compatibility) use ($cars) {
                if ($compatibility->relationLoaded('carModel') && $compatibility->carModel) {
                    $cars->push($this->formatCarModel($compatibility->carModel));
                }
            });
        }

        return $cars->filter()->unique()->values()->all();
    }

    private function formatCarModel(CarModel $carModel): string
    {
        return Str::squish(collect([
            $carModel->make,
            $carModel->model,
            $carModel->year,
            $carModel->engine_size,
            $carModel->variant_name,
        ])->filter()->join(' '));
    }
}
