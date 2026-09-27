<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\ScheduledEmailReminder;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClientCatalogSyncService
{
    private const WAREHOUSE_CODE = 'LMBWH';

    public function __construct(
        private readonly ProductService $productService,
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function preview(array $manifest): array
    {
        $products = collect($manifest['products']);

        return [
            'manifest_products' => $products->count(),
            'products_to_delete' => Product::withTrashed()->count(),
            'documents_to_delete' => InventoryDocument::query()->count(),
            'contacts_to_delete' => Contact::withTrashed()->count(),
            'expenses_to_delete' => Expense::query()->count(),
            'warehouse_quantity' => $products->sum('stocks.'.self::WAREHOUSE_CODE),
            'priced_products' => $products->where('default_selling_price', '>', 0)->count(),
            'zero_price_products' => $products->where('default_selling_price', 0)->count(),
        ];
    }

    public function sync(array $manifest): array
    {
        return DB::transaction(function () use ($manifest): array {
            $user = User::query()->active()->orderBy('id')->first();

            if (! $user) {
                throw new RuntimeException('At least one active user is required to record opening warehouse stock.');
            }

            $warehouse = Site::query()->active()->where('code', self::WAREHOUSE_CODE)->first();

            if (! $warehouse) {
                throw new RuntimeException('The active Limbe Warehouse site (LMBWH) is required.');
            }

            $deleted = $this->clearOperationalData();
            $brands = $this->syncBrands($manifest['brands']);
            $productTypes = $this->syncProductTypes($manifest['products']);
            $createdRows = collect();

            foreach ($manifest['products'] as $row) {
                $productType = $productTypes->get($row['product_type']['code']);
                $brand = $brands->get($row['brand_code']);

                if (! $productType || ! $brand) {
                    throw new RuntimeException("Catalog dependencies are missing for {$row['source_key']}.");
                }

                $product = $this->productService->create([
                    'product_name' => $row['product_name'],
                    'car_model_id' => null,
                    'product_type_id' => $productType->id,
                    'fuel_type_id' => null,
                    'brand_id' => $brand->id,
                    'tax_profile_id' => null,
                    'part_country_of_origin' => $row['part_country_of_origin'],
                    'description' => $row['description'],
                    'default_selling_price' => $row['default_selling_price'],
                    'default_low_stock_level' => $row['default_low_stock_level'],
                    'unit_name' => $row['unit_name'],
                    'pack_size' => $row['pack_size'],
                    'is_active' => true,
                    'references' => $row['references'],
                    'compatibilities' => [],
                ]);

                $createdRows->push([
                    'product' => $product,
                    'quantity' => (int) $row['stocks'][self::WAREHOUSE_CODE],
                    'source_rows' => $row['source_rows'] ?? [],
                ]);
            }

            $positiveRows = $createdRows->where('quantity', '>', 0);
            $openingAdjustment = null;

            if ($positiveRows->isNotEmpty()) {
                $openingAdjustment = $this->inventoryDocumentService->createStockAdjustment([
                    'site_id' => $warehouse->id,
                    'document_date' => now(),
                    'status' => 'approved',
                    'notes' => 'Opening warehouse stock imported from Warehouse_September_2026.xlsx.',
                    'items' => $positiveRows->map(fn (array $entry): array => [
                        'product_id' => $entry['product']->id,
                        'quantity_change' => $entry['quantity'],
                        'notes' => 'Opening quantity from '.collect($entry['source_rows'])->join('; ').'.',
                    ])->values()->all(),
                ], $user, enforceSiteAccess: false);
            }

            foreach ($createdRows as $entry) {
                $stock = SiteStock::query()->firstOrCreate(
                    [
                        'product_id' => $entry['product']->id,
                        'site_id' => $warehouse->id,
                    ],
                    [
                        'quantity_on_hand' => 0,
                        'reserved_quantity' => 0,
                    ]
                );

                $stock->forceFill([
                    'low_stock_level' => $entry['product']->default_low_stock_level,
                ])->save();
            }

            return $deleted + [
                'products_created' => $createdRows->count(),
                'opening_adjustment_id' => $openingAdjustment?->id,
                'warehouse_quantity' => $createdRows->sum('quantity'),
            ];
        });
    }

    private function clearOperationalData(): array
    {
        $counts = [
            'products_deleted' => Product::withTrashed()->count(),
            'documents_deleted' => InventoryDocument::query()->count(),
            'stock_movements_deleted' => StockMovement::query()->count(),
            'contacts_deleted' => Contact::withTrashed()->count(),
            'expenses_deleted' => Expense::query()->count(),
        ];

        StockMovement::query()->delete();
        InventoryDocument::query()->delete();
        SiteStock::query()->delete();
        Expense::query()->delete();
        ScheduledEmailReminder::query()->delete();
        Contact::withTrashed()->forceDelete();
        Product::withTrashed()->forceDelete();

        return $counts;
    }

    private function syncBrands(array $rows): Collection
    {
        return collect($rows)->mapWithKeys(function (array $row): array {
            $brand = Brand::withTrashed()->where('code', $row['code'])->first();

            if ($brand && strcasecmp($brand->name, $row['name']) !== 0) {
                throw new RuntimeException("Brand code {$row['code']} is already used by {$brand->name}.");
            }

            if (! $brand) {
                $brand = Brand::query()->create($row + ['is_active' => true]);
            } else {
                if ($brand->trashed()) {
                    $brand->restore();
                }

                $brand->forceFill($row + ['is_active' => true])->save();
            }

            return [$row['code'] => $brand];
        });
    }

    private function syncProductTypes(array $products): Collection
    {
        return collect($products)
            ->pluck('product_type')
            ->unique('code')
            ->mapWithKeys(function (array $row): array {
                $productType = ProductType::withTrashed()->where('code', $row['code'])->first();

                if ($productType && strcasecmp($productType->name, $row['name']) !== 0) {
                    throw new RuntimeException("Product type code {$row['code']} is already used by {$productType->name}.");
                }

                if (! $productType) {
                    $productType = ProductType::query()->create($row + ['is_active' => true]);
                } else {
                    if ($productType->trashed()) {
                        $productType->restore();
                    }

                    $productType->forceFill([
                        'description' => $row['description'] ?? $productType->description,
                        'is_active' => true,
                    ])->save();
                }

                return [$row['code'] => $productType];
            });
    }
}
