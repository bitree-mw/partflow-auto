<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\ProductReference;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClientCatalogSyncService
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function preview(array $manifest): array
    {
        $existing = $this->existingProductMatches($manifest['products']);
        $matches = $existing['matches'];
        $retiredTypeIds = ProductType::query()
            ->whereIn('name', $manifest['retired_product_types'])
            ->pluck('id');

        return [
            'manifest_products' => count($manifest['products']),
            'will_create' => collect($manifest['products'])->keys()->diff($matches->keys())->count(),
            'will_update' => $matches->count(),
            'store_quantity' => collect($manifest['products'])->sum('stocks.LMBST'),
            'warehouse_quantity' => collect($manifest['products'])->sum('stocks.LMBWH'),
            'priced_products' => collect($manifest['products'])->where('default_selling_price', '>', 0)->count(),
            'zero_price_products' => collect($manifest['products'])->where('default_selling_price', 0)->count(),
            'generic_products_to_archive' => Product::query()->whereIn('product_type_id', $retiredTypeIds)->where('is_active', true)->count(),
            'duplicate_products_to_archive' => Product::query()->whereIn('id', $existing['duplicates'])->where('is_active', true)->count(),
            'sales_to_delete' => InventoryDocument::query()->where('document_type', 'sale')->count(),
            'purchases_to_delete' => InventoryDocument::query()->where('document_type', 'purchase')->count(),
        ];
    }

    public function sync(array $manifest): array
    {
        return DB::transaction(function () use ($manifest): array {
            $user = User::query()->active()->orderBy('id')->first();

            if (! $user) {
                throw new RuntimeException('At least one active user is required to record the stock reconciliation.');
            }

            $sites = Site::query()
                ->whereIn('code', $manifest['sites'])
                ->get()
                ->keyBy('code');

            $missingSites = collect($manifest['sites'])->diff($sites->keys());

            if ($missingSites->isNotEmpty()) {
                throw new RuntimeException('Required sites are missing: '.$missingSites->join(', '));
            }

            $brands = $this->syncBrands($manifest['brands']);
            $productTypes = $this->syncProductTypes($manifest['products']);
            $existing = $this->existingProductMatches($manifest['products']);
            $matches = $existing['matches'];
            $targetStock = collect($manifest['sites'])->mapWithKeys(fn (string $code): array => [$code => []])->all();
            $created = 0;
            $updated = 0;

            foreach ($manifest['products'] as $index => $row) {
                $product = $matches->get($index);
                $productType = $productTypes->get($row['product_type']['code']);
                $brand = $brands->get($row['brand_code']);

                if (! $productType || ! $brand) {
                    throw new RuntimeException("Catalog dependencies are missing for {$row['source_key']}.");
                }

                if ($product) {
                    if ($product->trashed()) {
                        $product->restore();
                    }

                    if ((int) $product->product_type_id !== (int) $productType->id) {
                        throw new RuntimeException(sprintf(
                            'Existing product %s uses product type %s, not %s.',
                            $product->product_code,
                            $product->productType?->name ?? 'unknown',
                            $productType->name,
                        ));
                    }

                    $product = $this->productService->update($product, [
                        'product_name' => $row['product_name'],
                        'brand_id' => $brand->id,
                        'part_country_of_origin' => $row['part_country_of_origin'],
                        'description' => $row['description'],
                        'default_selling_price' => $row['default_selling_price'],
                        'default_low_stock_level' => $row['default_low_stock_level'],
                        'unit_name' => $row['unit_name'],
                        'pack_size' => $row['pack_size'],
                        'is_active' => true,
                    ]);
                    $updated++;
                } else {
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
                    $created++;
                }

                $this->ensureReferences($product, $row['references']);

                foreach ($manifest['sites'] as $siteCode) {
                    $targetStock[$siteCode][$product->id] = (int) ($row['stocks'][$siteCode] ?? 0);
                }
            }

            $archived = $this->archiveGenericProducts(
                productTypeNames: $manifest['retired_product_types'],
                siteCodes: $manifest['sites'],
                targetStock: $targetStock,
            );
            $duplicatesArchived = $this->archiveProducts(
                productIds: $existing['duplicates'],
                siteCodes: $manifest['sites'],
                targetStock: $targetStock,
            );
            $deleted = $this->deleteSalesAndPurchases();
            $stockTakeIds = $this->reconcileStock($targetStock, $sites, $user);

            return [
                'products_created' => $created,
                'products_updated' => $updated,
                'generic_products_archived' => $archived,
                'duplicate_products_archived' => $duplicatesArchived,
                'sales_deleted' => $deleted['sales'],
                'purchases_deleted' => $deleted['purchases'],
                'payments_deleted' => $deleted['payments'],
                'document_items_deleted' => $deleted['items'],
                'stock_movements_deleted' => $deleted['movements'],
                'stock_take_ids' => $stockTakeIds,
                'store_quantity' => collect($manifest['products'])->sum('stocks.LMBST'),
                'warehouse_quantity' => collect($manifest['products'])->sum('stocks.LMBWH'),
            ];
        });
    }

    private function syncBrands(array $rows): Collection
    {
        return collect($rows)->mapWithKeys(function (array $row): array {
            $brand = Brand::withTrashed()->where('code', $row['code'])->first();

            if ($brand && strcasecmp($brand->name, $row['name']) !== 0) {
                throw new RuntimeException("Brand code {$row['code']} is already used by {$brand->name}.");
            }

            if (! $brand) {
                $brand = Brand::create($row + ['is_active' => true]);
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
                    $productType = ProductType::create($row + ['is_active' => true]);
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

    private function existingProductMatches(array $rows): array
    {
        $referenceValues = collect($rows)
            ->flatMap(fn (array $row): array => array_merge([$row['source_key']], $row['match_reference_values']))
            ->unique()
            ->values();
        $references = ProductReference::withTrashed()
            ->where('reference_type', 'other')
            ->whereIn('reference_value', $referenceValues)
            ->get();
        $products = Product::withTrashed()
            ->with('productType')
            ->whereIn('id', $references->pluck('product_id'))
            ->orWhereIn('product_name', collect($rows)->pluck('product_name'))
            ->get();
        $productsById = $products->keyBy('id');
        $productsByReference = $references
            ->groupBy('reference_value')
            ->map(function (Collection $rows) use ($productsById): Collection {
                return $rows->pluck('product_id')->unique()->map(fn (int $id): ?Product => $productsById->get($id))->filter();
            });
        $productsByNameAndType = $products->keyBy(fn (Product $product): string => $this->nameAndTypeKey(
            $product->product_name,
            $product->productType?->name ?? '',
        ));
        $matches = collect();
        $duplicates = collect();
        $claimedProductIds = [];

        foreach ($rows as $index => $row) {
            $referenceCandidates = collect(array_merge([$row['source_key']], $row['match_reference_values']))
                ->flatMap(fn (string $value): Collection => $productsByReference->get($value, collect()))
                ->unique('id')
                ->values();

            $sourceKeyMatch = $productsByReference
                ->get($row['source_key'], collect())
                ->sortBy('id')
                ->first();
            $nameMatch = $productsByNameAndType->get($this->nameAndTypeKey($row['product_name'], $row['product_type']['name']));
            $product = $sourceKeyMatch
                ?? $referenceCandidates->firstWhere('id', $nameMatch?->id)
                ?? $referenceCandidates->sortBy('id')->first()
                ?? $nameMatch;

            if ($referenceCandidates->count() > 1) {
                $duplicates = $duplicates
                    ->merge($referenceCandidates->reject(fn (Product $candidate): bool => $candidate->id === $product?->id)->pluck('id'))
                    ->unique()
                    ->values();
            }

            if (! $product) {
                continue;
            }

            if (isset($claimedProductIds[$product->id])) {
                throw new RuntimeException(sprintf(
                    'Manifest products %s and %s both match existing product %s.',
                    $claimedProductIds[$product->id],
                    $row['source_key'],
                    $product->product_code,
                ));
            }

            $claimedProductIds[$product->id] = $row['source_key'];
            $matches->put($index, $product);
        }

        return ['matches' => $matches, 'duplicates' => $duplicates];
    }

    private function nameAndTypeKey(string $name, string $type): string
    {
        return mb_strtolower(trim($name).'|'.trim($type));
    }

    private function ensureReferences(Product $product, array $references): void
    {
        foreach ($references as $row) {
            $reference = ProductReference::withTrashed()->firstOrNew([
                'product_id' => $product->id,
                'reference_type' => $row['reference_type'],
                'reference_value' => $row['reference_value'],
            ]);

            if ($reference->exists && $reference->trashed()) {
                $reference->restore();
            }

            $hasAnotherPrimary = $product->references()
                ->where('is_primary', true)
                ->when($reference->exists, fn ($query) => $query->where('id', '!=', $reference->id))
                ->exists();

            $reference->forceFill([
                'is_primary' => (bool) $reference->is_primary || ((bool) $row['is_primary'] && ! $hasAnotherPrimary),
                'notes' => $row['notes'] ?? 'Stable provenance reference for the September 2026 client workbook reconciliation.',
            ])->save();
        }
    }

    private function archiveGenericProducts(array $productTypeNames, array $siteCodes, array &$targetStock): int
    {
        $types = ProductType::query()->whereIn('name', $productTypeNames)->get();
        $products = Product::query()->whereIn('product_type_id', $types->pluck('id'))->get();
        $archivedCount = $products->where('is_active', true)->count();

        foreach ($products as $product) {
            $product->forceFill(['is_active' => false])->save();

            foreach ($siteCodes as $siteCode) {
                $targetStock[$siteCode][$product->id] = 0;
            }
        }

        $types->each(fn (ProductType $type) => $type->forceFill(['is_active' => false])->save());

        return $archivedCount;
    }

    private function archiveProducts(Collection $productIds, array $siteCodes, array &$targetStock): int
    {
        $products = Product::query()->whereIn('id', $productIds)->get();
        $archivedCount = $products->where('is_active', true)->count();

        foreach ($products as $product) {
            $product->forceFill(['is_active' => false])->save();

            foreach ($siteCodes as $siteCode) {
                $targetStock[$siteCode][$product->id] = 0;
            }
        }

        return $archivedCount;
    }

    private function deleteSalesAndPurchases(): array
    {
        $documents = InventoryDocument::query()
            ->whereIn('document_type', ['sale', 'purchase'])
            ->withCount(['items', 'payments'])
            ->get();
        $documentIds = $documents->pluck('id');
        $movements = StockMovement::query()
            ->whereIn('inventory_document_id', $documentIds)
            ->orWhereIn('movement_type', ['sale_out', 'purchase_in'])
            ->count();

        StockMovement::query()
            ->whereIn('inventory_document_id', $documentIds)
            ->orWhereIn('movement_type', ['sale_out', 'purchase_in'])
            ->delete();
        InventoryDocument::query()->whereIn('id', $documentIds)->delete();

        return [
            'sales' => $documents->where('document_type', 'sale')->count(),
            'purchases' => $documents->where('document_type', 'purchase')->count(),
            'payments' => $documents->sum('payments_count'),
            'items' => $documents->sum('items_count'),
            'movements' => $movements,
        ];
    }

    private function reconcileStock(array $targetStock, Collection $sites, User $user): array
    {
        $documentIds = [];

        foreach ($targetStock as $siteCode => $productQuantities) {
            $site = $sites->get($siteCode);
            $items = collect($productQuantities)
                ->sortKeys()
                ->map(fn (int $quantity, int $productId): array => [
                    'product_id' => $productId,
                    'counted_quantity' => $quantity,
                    'notes' => 'September 2026 client workbook stock reconciliation.',
                ])
                ->values()
                ->all();

            if ($items === []) {
                continue;
            }

            $document = $this->inventoryDocumentService->createStockTake([
                'site_id' => $site->id,
                'document_date' => now(),
                'status' => 'approved',
                'notes' => 'Stock quantities reconciled from the supplied September 2026 client workbooks.',
                'items' => $items,
            ], $user, enforceSiteAccess: false);
            $documentIds[$siteCode] = $document->id;
        }

        return $documentIds;
    }
}
