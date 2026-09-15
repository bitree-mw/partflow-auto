<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'product_code' => $this->product_code,
            'product_name' => $this->product_name,

            'car_model' => $this->whenLoaded('carModel', function () {
                return new CarModelResource($this->carModel);
            }),

            'product_type' => $this->whenLoaded('productType', function () {
                return new ProductTypeResource($this->productType);
            }),

            'fuel_type' => $this->whenLoaded('fuelType', function () {
                return new FuelTypeResource($this->fuelType);
            }),

            'brand' => $this->whenLoaded('brand', function () {
                return new BrandResource($this->brand);
            }),

            'tax_profile' => $this->whenLoaded('taxProfile', function () {
                return new TaxProfileResource($this->taxProfile);
            }),

            'car_model_id' => $this->car_model_id,
            'product_type_id' => $this->product_type_id,
            'fuel_type_id' => $this->fuel_type_id,
            'brand_id' => $this->brand_id,
            'tax_profile_id' => $this->tax_profile_id,

            'part_country_of_origin' => $this->part_country_of_origin,
            'main_image_path' => $this->main_image_path,
            'description' => $this->description,
            'pos_description' => $this->pos_description,

            'default_purchase_price' => (float) $this->default_purchase_price,
            'default_selling_price' => (float) $this->default_selling_price,
            'minimum_selling_price' => (float) $this->minimum_selling_price,
            'default_low_stock_level' => $this->default_low_stock_level,

            'unit_name' => $this->unit_name,
            'pack_size' => (float) $this->pack_size,

            'references' => ProductReferenceResource::collection(
                $this->whenLoaded('references')
            ),

            'compatibilities' => ProductCompatibilityResource::collection(
                $this->whenLoaded('compatibilities')
            ),

            'compatible_cars' => $this->whenLoaded('compatibilities', function () {
                return $this->compatibilities
                    ->map(function ($compatibility) {
                        if (! $compatibility->relationLoaded('carModel') || ! $compatibility->carModel) {
                            return null;
                        }

                        return $compatibility->carModel->make.' '
                            .$compatibility->carModel->model.' '
                            .$compatibility->carModel->year
                            .($compatibility->carModel->engine_size ? ' '.$compatibility->carModel->engine_size : '')
                            .($compatibility->carModel->variant_name ? ' '.$compatibility->carModel->variant_name : '');
                    })
                    ->filter()
                    ->values();
            }),

            'is_active' => $this->is_active,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
