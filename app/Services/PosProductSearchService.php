<?php

namespace App\Services;

use App\Models\SiteStock;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PosProductSearchService
{
    public function search(array $filters = []): Collection
    {
        return SiteStock::query()
            ->with([
                'site',
                'product.carModel',
                'product.productType',
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
            ->when(isset($filters['vehicle_search']) && $filters['vehicle_search'] !== '', function ($query) use ($filters) {
                $search = $filters['vehicle_search'];

                $query->whereHas('product', function ($query) use ($search) {
                    $query->whereHas('carModel', function ($query) use ($search) {
                        $this->vehicleSearchWhere($query, $search);
                    })
                        ->orWhereHas('compatibilities.carModel', function ($query) use ($search) {
                            $this->vehicleSearchWhere($query, $search);
                        });
                });
            })
            ->when(isset($filters['product_type']) && $filters['product_type'] !== '', function ($query) use ($filters) {
                $query->whereHas('product.productType', function ($query) use ($filters) {
                    $query->where('name', $filters['product_type'])
                        ->orWhere('code', $filters['product_type']);
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

    private function vehicleSearchWhere($query, string $search): void
    {
        $terms = collect(preg_split('/\s+/', trim($search)) ?: [])
            ->filter()
            ->values();

        $query->where(function ($query) use ($terms) {
            $terms->each(function (string $term) use ($query): void {
                $query->where(function ($query) use ($term) {
                    $query->where('make', 'like', "%{$term}%")
                        ->orWhere('model', 'like', "%{$term}%")
                        ->orWhere('make_code', 'like', "%{$term}%")
                        ->orWhere('model_code', 'like', "%{$term}%")
                        ->orWhere('year', 'like', "%{$term}%")
                        ->orWhere('engine_size', 'like', "%{$term}%")
                        ->orWhere('variant_name', 'like', "%{$term}%")
                        ->orWhere('country_of_origin', 'like', "%{$term}%");
                });
            });
        });
    }

    public function suggestions(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '') {
            return [];
        }

        return Product::query()
            ->with(['brand', 'productType'])
            ->select([
                'products.id',
                'products.product_code',
                'products.product_name',
                'products.brand_id',
                'products.product_type_id',
                DB::raw('COALESCE(SUM(inventory_document_items.quantity), 0) as sold_quantity'),
            ])
            ->join('inventory_document_items', 'inventory_document_items.product_id', '=', 'products.id')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->where('products.is_active', true)
            ->where('inventory_documents.document_type', 'sale')
            ->where('inventory_documents.status', 'completed')
            ->when(isset($filters['site_id']), function ($query) use ($filters) {
                $query->where('inventory_documents.source_site_id', (int) $filters['site_id']);
            })
            ->where(function ($query) use ($search) {
                $query->where('products.product_code', 'like', "%{$search}%")
                    ->orWhere('products.product_name', 'like', "%{$search}%")
                    ->orWhere('products.pos_description', 'like', "%{$search}%")
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
            })
            ->groupBy('products.id', 'products.product_code', 'products.product_name', 'products.brand_id', 'products.product_type_id')
            ->orderByDesc('sold_quantity')
            ->orderBy('products.product_name')
            ->limit((int) ($filters['limit'] ?? 5))
            ->get()
            ->map(function (Product $product): array {
                $displayName = collect([
                    $product->brand?->name,
                    $product->productType?->name,
                ])
                    ->filter()
                    ->join(' ');

                return [
                    'product_id' => $product->id,
                    'product_code' => $product->product_code,
                    'product_name' => $product->product_name,
                    'label' => $displayName === ''
                        ? $product->product_code
                        : "{$product->product_code} - {$displayName}",
                    'sold_quantity' => (int) $product->sold_quantity,
                ];
            })
            ->values()
            ->all();
    }
}
