<?php

namespace App\Repositories;

use App\Models\InventoryDocument;
use App\Models\StockMovement;
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
            ->with(['contact', 'sourceSite', 'destinationSite'])
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
}
