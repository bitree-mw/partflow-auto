<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SiteStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    public function list(array $filters = []): Collection
    {
        return StockMovement::query()
            ->with([
                'product.carModel',
                'product.partType',
                'product.fuelType',
                'product.brand',
                'product.taxProfile',
                'product.references',
                'product.compatibilities.carModel',
                'site',
                'inventoryDocument',
            ])
            ->forProduct(isset($filters['product_id']) ? (int) $filters['product_id'] : null)
            ->forSite(isset($filters['site_id']) ? (int) $filters['site_id'] : null)
            ->type($filters['movement_type'] ?? null)
            ->when(isset($filters['inventory_document_id']), function ($query) use ($filters) {
                $query->where('inventory_document_id', $filters['inventory_document_id']);
            })
            ->when(isset($filters['date_from']), function ($query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(isset($filters['date_to']), function ($query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->latest()
            ->get();
    }

    public function increase(
        int $productId,
        int $siteId,
        int $quantity,
        string $movementType,
        int $createdBy,
        array $context = []
    ): StockMovement {
        return $this->move(
            productId: $productId,
            siteId: $siteId,
            quantityChange: abs($quantity),
            movementType: $movementType,
            createdBy: $createdBy,
            context: $context,
            validateAvailable: false
        );
    }

    public function decrease(
        int $productId,
        int $siteId,
        int $quantity,
        string $movementType,
        int $createdBy,
        array $context = []
    ): StockMovement {
        return $this->move(
            productId: $productId,
            siteId: $siteId,
            quantityChange: -abs($quantity),
            movementType: $movementType,
            createdBy: $createdBy,
            context: $context,
            validateAvailable: true
        );
    }

    public function adjustToCount(
        int $productId,
        int $siteId,
        int $countedQuantity,
        int $createdBy,
        array $context = []
    ): ?StockMovement {
        $stock = $this->stockForUpdate($productId, $siteId);
        $quantityChange = $countedQuantity - $stock->quantity_on_hand;

        if ($quantityChange === 0) {
            return null;
        }

        return $this->move(
            productId: $productId,
            siteId: $siteId,
            quantityChange: $quantityChange,
            movementType: 'stock_take_adjustment',
            createdBy: $createdBy,
            context: $context,
            validateAvailable: false
        );
    }

    public function currentQuantity(int $productId, int $siteId): int
    {
        return SiteStock::query()
            ->where('product_id', $productId)
            ->where('site_id', $siteId)
            ->value('quantity_on_hand') ?? 0;
    }

    private function move(
        int $productId,
        int $siteId,
        int $quantityChange,
        string $movementType,
        int $createdBy,
        array $context = [],
        bool $validateAvailable = false
    ): StockMovement {
        if ($quantityChange === 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Stock movement quantity cannot be zero.'],
            ]);
        }

        $stock = $this->stockForUpdate($productId, $siteId);
        $balanceBefore = $stock->quantity_on_hand;
        $balanceAfter = $balanceBefore + $quantityChange;

        if ($balanceAfter < 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Stock quantity cannot go below zero.'],
            ]);
        }

        if ($validateAvailable && abs($quantityChange) > $stock->available_quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Requested quantity exceeds available stock.'],
            ]);
        }

        if ($balanceAfter < $stock->reserved_quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity on hand cannot be lower than reserved quantity.'],
            ]);
        }

        $stock->forceFill([
            'quantity_on_hand' => $balanceAfter,
        ])->save();

        return StockMovement::create([
            'product_id' => $productId,
            'site_id' => $siteId,
            'movement_type' => $movementType,
            'quantity_change' => $quantityChange,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'inventory_document_id' => $context['inventory_document_id'] ?? null,
            'inventory_document_item_id' => $context['inventory_document_item_id'] ?? null,
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'notes' => $context['notes'] ?? null,
            'created_by' => $createdBy,
        ]);
    }

    private function stockForUpdate(int $productId, int $siteId): SiteStock
    {
        $stock = SiteStock::query()
            ->where('product_id', $productId)
            ->where('site_id', $siteId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        $product = Product::findOrFail($productId);

        SiteStock::create([
            'product_id' => $productId,
            'site_id' => $siteId,
            'quantity_on_hand' => 0,
            'reserved_quantity' => 0,
            'low_stock_level' => $product->default_low_stock_level,
        ]);

        return SiteStock::query()
            ->where('product_id', $productId)
            ->where('site_id', $siteId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
