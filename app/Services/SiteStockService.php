<?php

namespace App\Services;

use App\Models\SiteStock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SiteStockService
{
    public function __construct(
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function list(array $filters = [], ?User $user = null): Collection
    {
        if ($user) {
            $filters = $this->siteAccessService->scopeFilters($user, $filters);
        }

        return SiteStock::query()
            ->with([
                'product.carModel',
                'product.productType',
                'product.fuelType',
                'product.brand',
                'site',
            ])
            ->forProduct(isset($filters['product_id']) ? (int) $filters['product_id'] : null)
            ->forSite(isset($filters['site_id']) ? (int) $filters['site_id'] : null)
            ->forSites($filters['site_ids'] ?? null)
            ->when(isset($filters['low_stock']), function ($query) use ($filters) {
                if (filter_var($filters['low_stock'], FILTER_VALIDATE_BOOLEAN)) {
                    $query
                        ->whereRaw('COALESCE(low_stock_level, 0) > 0')
                        ->whereRaw('(quantity_on_hand - reserved_quantity) <= COALESCE(low_stock_level, 0)');
                }
            })
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $search = $filters['search'];

                $query->whereHas('product', function ($query) use ($search) {
                    $query->where('product_code', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('site_id')
            ->orderBy('product_id')
            ->get();
    }

    public function create(array $data, User $user): SiteStock
    {
        $this->siteAccessService->authorizeSite($user, (int) $data['site_id'], SiteAccessService::ADJUST_STOCK);

        return DB::transaction(function () use ($data) {
            $quantityOnHand = $data['quantity_on_hand'] ?? 0;
            $reservedQuantity = $data['reserved_quantity'] ?? 0;

            if ($reservedQuantity > $quantityOnHand) {
                throw ValidationException::withMessages([
                    'reserved_quantity' => ['Reserved quantity cannot be greater than quantity on hand.'],
                ]);
            }

            $siteStock = SiteStock::create([
                'product_id' => $data['product_id'],
                'site_id' => $data['site_id'],
                'quantity_on_hand' => $quantityOnHand,
                'reserved_quantity' => $reservedQuantity,
                'low_stock_level' => $data['low_stock_level'] ?? null,
            ]);

            return $siteStock->load([
                'product.carModel',
                'product.productType',
                'product.fuelType',
                'product.brand',
                'site',
            ]);
        });
    }

    public function update(SiteStock $siteStock, array $data, User $user): SiteStock
    {
        $this->siteAccessService->authorizeSite($user, $siteStock->site_id, SiteAccessService::ADJUST_STOCK);

        return DB::transaction(function () use ($siteStock, $data) {
            $quantityOnHand = $data['quantity_on_hand'] ?? $siteStock->quantity_on_hand;
            $reservedQuantity = $data['reserved_quantity'] ?? $siteStock->reserved_quantity;

            if ($reservedQuantity > $quantityOnHand) {
                throw ValidationException::withMessages([
                    'reserved_quantity' => ['Reserved quantity cannot be greater than quantity on hand.'],
                ]);
            }

            $siteStock->update($data);

            return $siteStock->refresh()->load([
                'product.carModel',
                'product.productType',
                'product.fuelType',
                'product.brand',
                'site',
            ]);
        });
    }

    public function delete(SiteStock $siteStock, User $user): void
    {
        $this->siteAccessService->authorizeSite($user, $siteStock->site_id, SiteAccessService::ADJUST_STOCK);

        $siteStock->delete();
    }
}
