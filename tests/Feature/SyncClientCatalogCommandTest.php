<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\InventoryDocument;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncClientCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reconciles_catalog_and_stock_while_deleting_only_sales_and_purchases(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $store = Site::query()->create(['name' => 'Limbe Store', 'code' => 'LMBST', 'type' => 'shop', 'is_active' => true]);
        $warehouse = Site::query()->create(['name' => 'Limbe Warehouse', 'code' => 'LMBWH', 'type' => 'warehouse', 'is_active' => true]);
        $genericHeadLampType = ProductType::query()->create(['name' => 'Head Lamp', 'code' => 'HL', 'is_active' => true]);
        $radiatorType = ProductType::query()->create(['name' => 'Radiator', 'code' => 'RAD', 'is_active' => true]);
        $confirmedBrand = Brand::query()->create(['name' => 'Confirmed Brand', 'code' => 'CONF', 'country' => 'Japan', 'is_active' => true]);
        $productService = app(ProductService::class);
        $genericHeadLamp = $productService->create([
            'product_name' => 'Head Lamp - Toyota Raum',
            'product_type_id' => $genericHeadLampType->id,
            'default_selling_price' => 300000,
            'references' => [],
            'compatibilities' => [],
        ]);
        $radiator = $productService->create([
            'product_name' => 'Radiator - Nissan Note',
            'product_type_id' => $radiatorType->id,
            'brand_id' => $confirmedBrand->id,
            'default_selling_price' => 200000,
            'references' => [[
                'reference_type' => 'other',
                'reference_value' => 'CPT-LEGACY-RAD',
                'is_primary' => true,
            ]],
            'compatibilities' => [],
        ]);
        $originalRadiatorCode = $radiator->product_code;
        $radiator->references()->create([
            'reference_type' => 'other',
            'reference_value' => 'SEP26-CCCCCCCCCCCC',
            'is_primary' => false,
            'notes' => 'Existing September source key.',
        ]);
        $duplicateRadiator = $productService->create([
            'product_name' => 'Radiator - Nissan Note Duplicate',
            'product_type_id' => $radiatorType->id,
            'default_selling_price' => 200000,
            'references' => [[
                'reference_type' => 'other',
                'reference_value' => 'CPT-LEGACY-RAD-2',
                'is_primary' => true,
            ]],
            'compatibilities' => [],
        ]);

        foreach ([$store, $warehouse] as $site) {
            SiteStock::query()->create([
                'product_id' => $genericHeadLamp->id,
                'site_id' => $site->id,
                'quantity_on_hand' => 7,
                'reserved_quantity' => 0,
                'low_stock_level' => 0,
            ]);
            SiteStock::query()->create([
                'product_id' => $duplicateRadiator->id,
                'site_id' => $site->id,
                'quantity_on_hand' => 5,
                'reserved_quantity' => 0,
                'low_stock_level' => 0,
            ]);
            SiteStock::query()->create([
                'product_id' => $radiator->id,
                'site_id' => $site->id,
                'quantity_on_hand' => 9,
                'reserved_quantity' => 0,
                'low_stock_level' => 0,
            ]);
        }

        $sale = $this->document('SAL-OLD', 'sale', $store, $user);
        $purchase = $this->document('PUR-OLD', 'purchase', $warehouse, $user);
        $stockTake = $this->document('STK-KEEP', 'stock_take', $store, $user);
        $saleItemId = $this->documentItem($sale, $radiator);
        $purchaseItemId = $this->documentItem($purchase, $radiator);
        $stockTakeItemId = $this->documentItem($stockTake, $radiator);
        $this->movement($sale, $saleItemId, $radiator, $store, $user, 'sale_out', -1);
        $this->movement($purchase, $purchaseItemId, $radiator, $warehouse, $user, 'purchase_in', 1);
        $preservedMovement = $this->movement($stockTake, $stockTakeItemId, $radiator, $store, $user, 'stock_take_adjustment', 2);
        $account = PaymentAccount::query()->create(['account_name' => 'Test Cash', 'account_type' => 'cash', 'is_active' => true]);
        Payment::query()->create([
            'inventory_document_id' => $sale->id,
            'payment_account_id' => $account->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'received_by' => $user->id,
        ]);

        $manifestPath = $this->writeManifest([
            $this->productRow('SEP26-AAAAAAAAAAAA', 'Head Lamp Left - Toyota Raum', 'Head Lamp Left', 'HLL', [], 2, 3, 400000),
            $this->productRow('SEP26-BBBBBBBBBBBB', 'Head Lamp Right - Toyota Raum', 'Head Lamp Right', 'HLR', [], 1, 4, 400000),
            $this->productRow('SEP26-CCCCCCCCCCCC', 'Radiator - Nissan Note', 'Radiator', 'RAD', ['CPT-LEGACY-RAD', 'CPT-LEGACY-RAD-2'], 4, 1, 380000, ['CPT-LEGACY-RAD-2']),
        ]);

        try {
            $this->artisan('catalog:sync-client-manifest', ['manifest' => $manifestPath, '--force' => true])
                ->assertSuccessful();
        } finally {
            @unlink($manifestPath);
        }

        $this->assertDatabaseMissing('inventory_documents', ['id' => $sale->id]);
        $this->assertDatabaseMissing('inventory_documents', ['id' => $purchase->id]);
        $this->assertDatabaseHas('inventory_documents', ['id' => $stockTake->id, 'document_type' => 'stock_take']);
        $this->assertDatabaseHas('stock_movements', ['id' => $preservedMovement->id]);
        $this->assertDatabaseMissing('stock_movements', ['movement_type' => 'sale_out']);
        $this->assertDatabaseMissing('stock_movements', ['movement_type' => 'purchase_in']);
        $this->assertDatabaseCount('payments', 0);

        $this->assertFalse($genericHeadLamp->refresh()->is_active);
        $this->assertFalse($genericHeadLampType->refresh()->is_active);
        $this->assertSame(0, SiteStock::query()->where('product_id', $genericHeadLamp->id)->sum('quantity_on_hand'));
        $this->assertTrue((bool) ProductType::query()->where('code', 'HLL')->value('is_active'));
        $this->assertTrue((bool) ProductType::query()->where('code', 'HLR')->value('is_active'));

        $left = Product::query()->where('product_name', 'Head Lamp Left - Toyota Raum')->firstOrFail();
        $right = Product::query()->where('product_name', 'Head Lamp Right - Toyota Raum')->firstOrFail();
        $this->assertNull($left->part_country_of_origin);
        $this->assertSame('Unknown', $left->brand->name);
        $this->assertSame(2, $this->stock($left, $store));
        $this->assertSame(3, $this->stock($left, $warehouse));
        $this->assertSame(1, $this->stock($right, $store));
        $this->assertSame(4, $this->stock($right, $warehouse));
        $this->assertSame('380000.00', $radiator->refresh()->default_selling_price);
        $this->assertSame($radiator->id, $radiator->fresh()->id);
        $this->assertNotSame($originalRadiatorCode, $radiator->product_code);
        $this->assertStringStartsWith('RAD-UNKN-UNK-', $radiator->product_code);
        $this->assertNull($radiator->part_country_of_origin);
        $this->assertSame('Unknown', $radiator->brand->name);
        $this->assertDatabaseHas('brands', ['code' => 'DUBU', 'country' => 'Dubai', 'is_active' => true]);
        $this->assertDatabaseHas('brands', ['code' => 'DUBA', 'country' => 'Dubai', 'is_active' => true]);
        $this->assertSame(0, Product::query()->whereIn('brand_id', Brand::query()->whereIn('code', ['DUBA', 'DUBU'])->pluck('id'))->count());
        $this->assertFalse($duplicateRadiator->refresh()->is_active);
        $this->assertSame(0, SiteStock::query()->where('product_id', $duplicateRadiator->id)->sum('quantity_on_hand'));
        $this->assertSame(4, $this->stock($radiator, $store));
        $this->assertSame(1, $this->stock($radiator, $warehouse));
        $this->assertDatabaseHas('inventory_document_items', ['id' => $stockTakeItemId, 'product_id' => $radiator->id]);
        $this->assertDatabaseHas('stock_movements', ['id' => $preservedMovement->id, 'product_id' => $radiator->id]);
        $this->assertDatabaseHas('product_references', [
            'product_id' => $radiator->id,
            'reference_type' => 'other',
            'reference_value' => 'SEP26-CCCCCCCCCCCC',
        ]);
        $this->assertDatabaseHas('product_references', [
            'product_id' => $radiator->id,
            'reference_type' => 'other',
            'reference_value' => 'CPT-LEGACY-RAD-2',
        ]);
    }

    public function test_dry_run_does_not_change_database(): void
    {
        User::factory()->create(['is_active' => true]);
        Site::query()->create(['name' => 'Limbe Store', 'code' => 'LMBST', 'type' => 'shop', 'is_active' => true]);
        Site::query()->create(['name' => 'Limbe Warehouse', 'code' => 'LMBWH', 'type' => 'warehouse', 'is_active' => true]);
        $manifestPath = $this->writeManifest([
            $this->productRow('SEP26-DDDDDDDDDDDD', 'Head Lamp Left - Toyota Vitz', 'Head Lamp Left', 'HLL', [], 1, 1, 300000),
        ]);

        try {
            $this->artisan('catalog:sync-client-manifest', ['manifest' => $manifestPath, '--dry-run' => true])
                ->expectsOutputToContain('Dry run complete')
                ->assertSuccessful();
        } finally {
            @unlink($manifestPath);
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('brands', 0);
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    private function document(string $number, string $type, Site $site, User $user): InventoryDocument
    {
        return InventoryDocument::query()->create([
            'document_number' => $number,
            'document_type' => $type,
            'source_site_id' => $site->id,
            'destination_site_id' => $site->id,
            'document_date' => now(),
            'status' => 'completed',
            'created_by' => $user->id,
        ]);
    }

    private function documentItem(InventoryDocument $document, Product $product): int
    {
        return $document->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'unit_price' => 200,
        ])->id;
    }

    private function movement(
        InventoryDocument $document,
        int $itemId,
        Product $product,
        Site $site,
        User $user,
        string $type,
        int $quantity
    ): StockMovement {
        return StockMovement::query()->create([
            'product_id' => $product->id,
            'site_id' => $site->id,
            'movement_type' => $type,
            'quantity_change' => $quantity,
            'balance_before' => 5,
            'balance_after' => 5 + $quantity,
            'inventory_document_id' => $document->id,
            'inventory_document_item_id' => $itemId,
            'created_by' => $user->id,
        ]);
    }

    private function stock(Product $product, Site $site): int
    {
        return (int) SiteStock::query()
            ->where('product_id', $product->id)
            ->where('site_id', $site->id)
            ->value('quantity_on_hand');
    }

    private function writeManifest(array $products): string
    {
        $path = tempnam(sys_get_temp_dir(), 'partflow-client-catalog-');
        file_put_contents($path, json_encode([
            'schema_version' => 1,
            'import_key' => 'september-2026-client-catalog',
            'brands' => [
                [
                    'name' => 'Unknown',
                    'code' => 'UNKN',
                    'country' => null,
                    'description' => 'Fallback brand for unconfirmed products.',
                ],
                [
                    'name' => 'Dubai Used Parts',
                    'code' => 'DUBU',
                    'country' => 'Dubai',
                    'description' => 'Optional Dubai sourcing classification.',
                ],
                [
                    'name' => 'Dubai Aftermarket',
                    'code' => 'DUBA',
                    'country' => 'Dubai',
                    'description' => 'Optional Dubai sourcing classification.',
                ],
            ],
            'retired_product_types' => ['Head Lamp'],
            'sites' => ['LMBST', 'LMBWH'],
            'products' => $products,
        ], JSON_THROW_ON_ERROR));

        return $path;
    }

    private function productRow(
        string $sourceKey,
        string $name,
        string $type,
        string $typeCode,
        array $matchReferences,
        int $storeQuantity,
        int $warehouseQuantity,
        int $price,
        array $mergedReferences = [],
    ): array {
        return [
            'source_key' => $sourceKey,
            'product_name' => $name,
            'product_type' => [
                'name' => $type,
                'code' => $typeCode,
                'description' => "{$type} test type.",
            ],
            'brand_code' => 'UNKN',
            'part_country_of_origin' => null,
            'default_selling_price' => $price,
            'default_low_stock_level' => 0,
            'unit_name' => 'piece',
            'pack_size' => 1,
            'is_active' => true,
            'description' => 'Test source product.',
            'match_reference_values' => $matchReferences,
            'stocks' => ['LMBST' => $storeQuantity, 'LMBWH' => $warehouseQuantity],
            'references' => array_merge([[
                'reference_type' => 'other',
                'reference_value' => $sourceKey,
                'is_primary' => true,
                'notes' => 'Test provenance.',
            ]], array_map(fn (string $reference): array => [
                'reference_type' => 'other',
                'reference_value' => $reference,
                'is_primary' => false,
                'notes' => 'Merged provenance.',
            ], $mergedReferences)),
        ];
    }
}
