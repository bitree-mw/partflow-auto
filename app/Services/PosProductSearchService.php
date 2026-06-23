<?php

namespace App\Services;

use App\Models\SiteStock;
use Illuminate\Database\Eloquent\Collection;

class PosProductSearchService
{
    public function search(array $filters = []): Collection
    {
        return SiteStock::query()
            ->with([
                'site',
                'product.carModel',
                'product.partType',
                'product.fuelType',
                'product.brand',
                'product.taxProfile',
                'product.references',
                'product.compatibilities.carModel',
            ])
            ->forSite(isset($filters['site_id']) ? (int) $filters['site_id'] : null)
            ->when(isset($filters['compatible_car_model_id']), function ($query) use ($filters) {
                $query->whereHas('product', function ($query) use ($filters) {
                    $query->where('car_model_id', $filters['compatible_car_model_id'])
                        ->orWhereHas('compatibilities', function ($query) use ($filters) {
                            $query->where('car_model_id', $filters['compatible_car_model_id']);
                        });
                });
            })
            ->when(isset($filters['search']) && $filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->whereHas('product', function ($query) use ($search) {
                    $query->where('product_code', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%")
                        ->orWhere('pos_description', 'like', "%{$search}%")
                        ->orWhere('part_country_of_origin', 'like', "%{$search}%")
                        ->orWhereHas('references', function ($query) use ($search) {
                            $query->where('reference_value', 'like', "%{$search}%");
                        })
                        ->orWhereHas('carModel', function ($query) use ($search) {
                            $query->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('engine_size', 'like', "%{$search}%")
                                ->orWhere('variant_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('compatibilities.carModel', function ($query) use ($search) {
                            $query->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('engine_size', 'like', "%{$search}%")
                                ->orWhere('variant_name', 'like', "%{$search}%");
                        });
                });
            })
            ->whereHas('product', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('site_id')
            ->orderBy(
                \App\Models\Product::select('product_name')
                    ->whereColumn('products.id', 'site_stocks.product_id')
                    ->limit(1)
            )
            ->get();
    }
}
