<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductReference;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClientDocsInventoryService
{
    private const STORE_CODE = 'LMBST';

    public function __construct(
        private readonly ProductService $productService,
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function preview(array $products, array $matchedWarehouseSourceKeys): array
    {
        $sourceKeys = collect($products)->pluck('source_key');
        $existingSourceKeys = ProductReference::query()
            ->where('reference_type', 'other')
            ->whereIn('reference_value', $sourceKeys)
            ->pluck('reference_value')
            ->flip();
        $warehouseMatches = collect($products)
            ->filter(fn (array $row): bool => isset($matchedWarehouseSourceKeys[$row['source_key']]))
            ->count();

        return [
            'source_products' => count($products),
            'source_quantity' => collect($products)->sum('quantity_on_hand'),
            'warehouse_matches' => $warehouseMatches,
            'already_imported' => $existingSourceKeys->count(),
            'new_products' => collect($products)
                ->reject(fn (array $row): bool => $existingSourceKeys->has($row['source_key']))
                ->reject(fn (array $row): bool => isset($matchedWarehouseSourceKeys[$row['source_key']]))
                ->count(),
            'zero_quantity_products' => collect($products)->where('quantity_on_hand', 0)->count(),
        ];
    }

    public function apply(array $products, array $matchedWarehouseSourceKeys): array
    {
        return DB::transaction(function () use ($products, $matchedWarehouseSourceKeys): array {
            $user = User::query()->active()->orderBy('id')->first();

            if (! $user) {
                throw new RuntimeException('At least one active user is required to record store stock.');
            }

            $store = Site::query()->active()->where('code', self::STORE_CODE)->first();

            if (! $store) {
                throw new RuntimeException('The active Limbe Store site (LMBST) is required.');
            }

            $defaultTaxProfileId = TaxProfile::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->value('id');
            $typeIds = [];
            $created = 0;
            $reused = 0;
            $referencesAdded = 0;
            $targets = collect();

            foreach ($products as $row) {
                $product = $this->productForSourceKey($row['source_key']);

                if (! $product && isset($matchedWarehouseSourceKeys[$row['source_key']])) {
                    $warehouseSourceKey = $matchedWarehouseSourceKeys[$row['source_key']];
                    $product = $this->productForSourceKey($warehouseSourceKey);

                    if (! $product) {
                        throw new RuntimeException(
                            "Matched warehouse product is missing for {$row['source_key']}: {$warehouseSourceKey}."
                        );
                    }
                }

                if (! $product) {
                    $typeId = $this->productTypeId($row['product_type'], $typeIds);
                    $product = $this->productService->create([
                        'product_name' => $row['product_name'],
                        'car_model_id' => null,
                        'product_type_id' => $typeId,
                        'fuel_type_id' => null,
                        'brand_id' => null,
                        'tax_profile_id' => $defaultTaxProfileId,
                        'part_country_of_origin' => null,
                        'description' => $row['description'],
                        'default_selling_price' => $row['default_selling_price'],
                        'default_low_stock_level' => $row['default_low_stock_level'],
                        'unit_name' => $row['unit_name'],
                        'pack_size' => $row['pack_size'],
                        'is_active' => $row['is_active'],
                        'references' => $row['references'],
                        'compatibilities' => [],
                    ]);
                    $created++;
                } else {
                    $reused++;
                    $referencesAdded += $this->addReferences($product, $row['references']);
                }

                $existingTarget = $targets->get($product->id, [
                    'product' => $product,
                    'quantity' => 0,
                    'source_rows' => [],
                ]);
                $existingTarget['quantity'] += (int) $row['quantity_on_hand'];
                $existingTarget['source_rows'][] = $row['source_rows'];
                $targets->put($product->id, $existingTarget);
            }

            $changedTargets = $targets->filter(function (array $target) use ($store): bool {
                $currentQuantity = SiteStock::query()
                    ->where('product_id', $target['product']->id)
                    ->where('site_id', $store->id)
                    ->value('quantity_on_hand') ?? 0;

                return (int) $currentQuantity !== $target['quantity'];
            });

            $stockTake = null;

            if ($changedTargets->isNotEmpty()) {
                $stockTake = $this->inventoryDocumentService->createStockTake([
                    'site_id' => $store->id,
                    'status' => 'approved',
                    'document_date' => now(),
                    'notes' => 'Store stock reconciled from the verified client workbooks and CPT source list.',
                    'items' => $changedTargets->map(fn (array $target): array => [
                        'product_id' => $target['product']->id,
                        'counted_quantity' => $target['quantity'],
                        'notes' => 'Counted quantity from '.collect($target['source_rows'])->join('; ').'.',
                    ])->values()->all(),
                ], $user, enforceSiteAccess: false);
            }

            foreach ($targets as $target) {
                $stock = SiteStock::query()->firstOrCreate(
                    [
                        'product_id' => $target['product']->id,
                        'site_id' => $store->id,
                    ],
                    [
                        'quantity_on_hand' => 0,
                        'reserved_quantity' => 0,
                    ]
                );
                $stock->forceFill([
                    'low_stock_level' => $target['product']->default_low_stock_level,
                ])->save();
            }

            return [
                'products_created' => $created,
                'products_reused' => $reused,
                'references_added' => $referencesAdded,
                'store_products' => $targets->count(),
                'store_quantity' => $targets->sum('quantity'),
                'stock_take_id' => $stockTake?->id,
                'stock_rows_changed' => $changedTargets->count(),
            ];
        });
    }

    private function productForSourceKey(string $sourceKey): ?Product
    {
        return Product::query()
            ->whereHas('references', function ($query) use ($sourceKey): void {
                $query
                    ->where('reference_type', 'other')
                    ->where('reference_value', strtoupper($sourceKey));
            })
            ->first();
    }

    private function productTypeId(array $type, array &$typeIds): int
    {
        $code = $type['code'];

        if (isset($typeIds[$code])) {
            return $typeIds[$code];
        }

        $productType = ProductType::withTrashed()->where('code', $code)->first();

        if ($productType && strcasecmp($productType->name, $type['name']) !== 0) {
            throw new RuntimeException(sprintf(
                'Product type code %s is already used by "%s", not "%s".',
                $code,
                $productType->name,
                $type['name'],
            ));
        }

        if (! $productType) {
            $productType = ProductType::query()->create([
                'name' => $type['name'],
                'code' => $code,
                'description' => $type['description'] ?? null,
                'is_active' => true,
            ]);
        } else {
            if ($productType->trashed()) {
                $productType->restore();
            }

            $productType->forceFill(['is_active' => true])->save();
        }

        return $typeIds[$code] = $productType->id;
    }

    private function addReferences(Product $product, array $references): int
    {
        $added = 0;

        foreach ($references as $reference) {
            $value = strtoupper(trim($reference['reference_value']));
            $existing = ProductReference::withTrashed()
                ->where('product_id', $product->id)
                ->where('reference_type', $reference['reference_type'])
                ->where('reference_value', $value)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                    $added++;
                }

                continue;
            }

            $product->references()->create([
                'reference_type' => $reference['reference_type'],
                'reference_value' => $value,
                'is_primary' => false,
                'notes' => $reference['notes'] ?? null,
            ]);
            $added++;
        }

        return $added;
    }
}
