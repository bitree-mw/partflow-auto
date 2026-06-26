<?php

namespace App\Repositories;

use App\Models\InventoryDocument;
use App\Models\Site;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public function todaySales(): float
    {
        return (float) InventoryDocument::query()
            ->where('document_type', 'sale')
            ->whereIn('status', ['completed', 'approved'])
            ->whereDate('document_date', today())
            ->sum('total_amount');
    }

    public function todaySaleCount(): int
    {
        return (int) InventoryDocument::query()
            ->where('document_type', 'sale')
            ->whereIn('status', ['completed', 'approved'])
            ->whereDate('document_date', today())
            ->count();
    }

    public function todayProfit(): float
    {
        return (float) DB::table('inventory_document_items')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->where('inventory_documents.document_type', 'sale')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->whereDate('inventory_documents.document_date', today())
            ->sum('inventory_document_items.profit_amount');
    }

    public function totalStockValue(): float
    {
        return (float) DB::table('site_stocks')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->sum(DB::raw('site_stocks.quantity_on_hand * products.default_purchase_price'));
    }

    public function lowStockCount(): int
    {
        return (int) DB::table('site_stocks')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0)')
            ->count();
    }

    public function outOfStockCount(): int
    {
        return (int) DB::table('site_stocks')
            ->whereRaw('(quantity_on_hand - reserved_quantity) <= 0')
            ->count();
    }

    public function outstandingCustomerBalances(): float
    {
        return (float) InventoryDocument::query()
            ->where('document_type', 'sale')
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount');
    }

    public function recentDocuments(string $documentType, int $limit = 5)
    {
        return InventoryDocument::query()
            ->with(['contact', 'sourceSite', 'destinationSite', 'items.product'])
            ->where('document_type', $documentType)
            ->latest('document_date')
            ->limit($limit)
            ->get();
    }

    public function recentStockMovements(int $limit = 10)
    {
        return StockMovement::query()
            ->with(['product', 'site', 'inventoryDocument'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function salesTrend(int $days = 7): Collection
    {
        $startDate = today()->subDays($days - 1);
        $rows = InventoryDocument::query()
            ->where('document_type', 'sale')
            ->whereIn('status', ['completed', 'approved'])
            ->whereDate('document_date', '>=', $startDate)
            ->selectRaw('DATE(document_date) as sale_date')
            ->selectRaw('SUM(total_amount) as sales_amount')
            ->groupBy('sale_date')
            ->pluck('sales_amount', 'sale_date');

        return collect(range(0, $days - 1))->map(function (int $offset) use ($startDate, $rows) {
            $date = $startDate->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'sales_amount' => (float) ($rows[$date->toDateString()] ?? 0),
            ];
        });
    }

    public function topSellingProducts(array $filters = [], int $limit = 5): Collection
    {
        return DB::table('inventory_document_items')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->join('products', 'products.id', '=', 'inventory_document_items.product_id')
            ->where('inventory_documents.document_type', 'sale')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('inventory_documents.document_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('inventory_documents.document_date', '<=', $filters['date_to']))
            ->selectRaw('products.id as product_id, products.product_code, products.product_name')
            ->selectRaw('SUM(inventory_document_items.quantity) as quantity_sold')
            ->selectRaw('SUM(inventory_document_items.line_total) as sales_amount')
            ->selectRaw('SUM(inventory_document_items.profit_amount) as profit_amount')
            ->groupBy('products.id', 'products.product_code', 'products.product_name')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();
    }

    public function branchPerformance(array $filters = []): Collection
    {
        $sales = DB::table('inventory_documents')
            ->where('inventory_documents.document_type', 'sale')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('inventory_documents.document_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('inventory_documents.document_date', '<=', $filters['date_to']))
            ->selectRaw('inventory_documents.source_site_id as site_id')
            ->selectRaw('SUM(inventory_documents.total_amount) as sales_amount')
            ->groupBy('inventory_documents.source_site_id')
            ->get()
            ->keyBy('site_id');

        $profits = DB::table('inventory_document_items')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->where('inventory_documents.document_type', 'sale')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('inventory_documents.document_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('inventory_documents.document_date', '<=', $filters['date_to']))
            ->selectRaw('inventory_documents.source_site_id as site_id')
            ->selectRaw('SUM(inventory_document_items.profit_amount) as profit_amount')
            ->groupBy('inventory_documents.source_site_id')
            ->get()
            ->keyBy('site_id');

        $stockouts = DB::table('site_stocks')
            ->whereRaw('(quantity_on_hand - reserved_quantity) <= 0')
            ->selectRaw('site_id, COUNT(*) as stockout_count')
            ->groupBy('site_id')
            ->pluck('stockout_count', 'site_id');

        return Site::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Site $site) use ($sales, $profits, $stockouts) {
                $salesRow = $sales->get($site->id);
                $profitRow = $profits->get($site->id);

                return [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'sales_amount' => (float) ($salesRow?->sales_amount ?? 0),
                    'profit_amount' => (float) ($profitRow?->profit_amount ?? 0),
                    'stockout_count' => (int) ($stockouts[$site->id] ?? 0),
                ];
            });
    }

    public function lowStockAlerts(int $limit = 5): Collection
    {
        return DB::table('site_stocks')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0)')
            ->selectRaw('sites.name as site_name, products.product_name')
            ->selectRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) as available_quantity')
            ->selectRaw('COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0) as low_stock_level')
            ->orderBy('available_quantity')
            ->limit($limit)
            ->get();
    }

    public function stockTakeVarianceTotal(): float
    {
        return (float) DB::table('inventory_document_items')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->join('products', 'products.id', '=', 'inventory_document_items.product_id')
            ->where('inventory_documents.document_type', 'stock_take')
            ->where('inventory_document_items.variance_quantity', '!=', 0)
            ->sum(DB::raw('ABS(inventory_document_items.variance_quantity) * products.default_purchase_price'));
    }

    public function pendingPurchaseReturnTotal(): float
    {
        return (float) InventoryDocument::query()
            ->where('document_type', 'purchase_return')
            ->whereIn('status', ['draft', 'pending'])
            ->sum('total_amount');
    }
}
