<?php

namespace App\Http\Resources;

use App\Models\CarModel;
use App\Models\InventoryDocumentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PosProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $unitCost = $this->latestPurchaseCost();
        $sellingPrice = (float) $product->default_selling_price;

        return [
            'product_id' => $product->id,
            'product_code' => $product->product_code,
            'product_name' => $product->product_name,
            'pos_description' => $product->pos_description ?: $product->description ?: $product->product_name,
            'compatible_cars' => $this->compatibleCars(),
            'site_id' => $this->site_id,
            'site_name' => $this->site?->name,
            'quantity_on_hand' => $this->quantity_on_hand,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,
            'selling_price' => $sellingPrice,
            'minimum_selling_price' => (float) $product->minimum_selling_price,
            'unit_cost' => $unitCost,
            'margin' => $sellingPrice - $unitCost,
            'product_type' => $product->productType ? [
                'id' => $product->productType->id,
                'name' => $product->productType->name,
                'code' => $product->productType->code,
            ] : null,
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
            'references' => $this->references(),
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

    private function references(): array
    {
        if (! $this->product->relationLoaded('references')) {
            return [];
        }

        return $this->product->references
            ->map(fn ($reference): array => [
                'reference_type' => $reference->reference_type,
                'reference_value' => $reference->reference_value,
                'is_primary' => $reference->is_primary,
            ])
            ->values()
            ->all();
    }

    private function latestPurchaseCost(): float
    {
        $cost = InventoryDocumentItem::query()
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->where('inventory_document_items.product_id', $this->product_id)
            ->where('inventory_documents.document_type', 'purchase')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->where('inventory_document_items.unit_cost', '>', 0)
            ->latest('inventory_documents.document_date')
            ->latest('inventory_document_items.id')
            ->value('inventory_document_items.unit_cost');

        return (float) ($cost ?? $this->product->default_purchase_price ?? 0);
    }
}
