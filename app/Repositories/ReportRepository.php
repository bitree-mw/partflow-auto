<?php

namespace App\Repositories;

use App\Models\Expense;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\SiteStock;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository
{
    public function currentStockBySite(array $filters = []): Collection
    {
        return SiteStock::query()
            ->with(['site', 'product.carModel', 'product.partType', 'product.fuelType', 'product.brand'])
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_id', $filters['site_id']))
            ->when(isset($filters['product_id']), fn ($query) => $query->where('product_id', $filters['product_id']))
            ->orderBy('site_id')
            ->orderBy('product_id')
            ->get()
            ->map(fn (SiteStock $stock) => [
                'site_id' => $stock->site_id,
                'site_name' => $stock->site?->name,
                'product_id' => $stock->product_id,
                'product_code' => $stock->product?->product_code,
                'product_name' => $stock->product?->product_name,
                'quantity_on_hand' => $stock->quantity_on_hand,
                'reserved_quantity' => $stock->reserved_quantity,
                'available_quantity' => $stock->available_quantity,
                'low_stock_level' => $stock->effective_low_stock_level,
            ]);
    }

    public function lowStockBySite(array $filters = []): Collection
    {
        return $this->stockLevelQuery($filters)
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0)')
            ->get();
    }

    public function outOfStockProducts(array $filters = []): Collection
    {
        return $this->stockLevelQuery($filters)
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= 0')
            ->get();
    }

    public function stockValuation(array $filters = []): Collection
    {
        return DB::table('site_stocks')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_stocks.site_id', $filters['site_id']))
            ->selectRaw('sites.id as site_id, sites.name as site_name')
            ->selectRaw('SUM(site_stocks.quantity_on_hand * products.default_purchase_price) as stock_value')
            ->selectRaw('SUM(site_stocks.quantity_on_hand) as quantity_on_hand')
            ->groupBy('sites.id', 'sites.name')
            ->orderBy('sites.name')
            ->get();
    }

    public function mostSellingProducts(array $filters = []): Collection
    {
        return $this->sellingProductsQuery($filters)
            ->orderByDesc('quantity_sold')
            ->limit((int) ($filters['limit'] ?? 10))
            ->get();
    }

    public function leastSellingProducts(array $filters = []): Collection
    {
        return $this->sellingProductsQuery($filters)
            ->orderBy('quantity_sold')
            ->limit((int) ($filters['limit'] ?? 10))
            ->get();
    }

    public function salesByDateRange(array $filters = []): Collection
    {
        return $this->documentsByType('sale', $filters);
    }

    public function purchasesByDateRange(array $filters = []): Collection
    {
        return $this->documentsByType('purchase', $filters);
    }

    public function profitByProduct(array $filters = []): Collection
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
            ->selectRaw('SUM(inventory_document_items.profit_amount) as profit_amount')
            ->groupBy('products.id', 'products.product_code', 'products.product_name')
            ->orderByDesc('profit_amount')
            ->get();
    }

    public function profitBySite(array $filters = []): Collection
    {
        return DB::table('inventory_document_items')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->join('sites', 'sites.id', '=', 'inventory_documents.source_site_id')
            ->where('inventory_documents.document_type', 'sale')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('inventory_documents.document_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('inventory_documents.document_date', '<=', $filters['date_to']))
            ->selectRaw('sites.id as site_id, sites.name as site_name')
            ->selectRaw('SUM(inventory_document_items.profit_amount) as profit_amount')
            ->groupBy('sites.id', 'sites.name')
            ->orderByDesc('profit_amount')
            ->get();
    }

    public function customerBalances(array $filters = []): Collection
    {
        return DB::table('inventory_documents')
            ->join('contacts', 'contacts.id', '=', 'inventory_documents.contact_id')
            ->where('inventory_documents.document_type', 'sale')
            ->where('inventory_documents.balance_amount', '>', 0)
            ->when(isset($filters['contact_id']), fn ($query) => $query->where('contacts.id', $filters['contact_id']))
            ->selectRaw('contacts.id as contact_id, contacts.name as customer_name, contacts.phone')
            ->selectRaw('SUM(inventory_documents.total_amount) as total_sales')
            ->selectRaw('SUM(inventory_documents.paid_amount) as paid_amount')
            ->selectRaw('SUM(inventory_documents.balance_amount) as balance_amount')
            ->groupBy('contacts.id', 'contacts.name', 'contacts.phone')
            ->orderByDesc('balance_amount')
            ->get();
    }

    public function paymentsByAccount(array $filters = []): Collection
    {
        return DB::table('payments')
            ->join('payment_accounts', 'payment_accounts.id', '=', 'payments.payment_account_id')
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('payments.payment_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('payments.payment_date', '<=', $filters['date_to']))
            ->when(isset($filters['payment_account_id']), fn ($query) => $query->where('payment_accounts.id', $filters['payment_account_id']))
            ->selectRaw('payment_accounts.id as payment_account_id, payment_accounts.account_name, payment_accounts.account_type')
            ->selectRaw('COUNT(payments.id) as payment_count')
            ->selectRaw('SUM(payments.amount) as total_amount')
            ->groupBy('payment_accounts.id', 'payment_accounts.account_name', 'payment_accounts.account_type')
            ->orderByDesc('total_amount')
            ->get();
    }

    public function stockMovementHistory(array $filters = []): Collection
    {
        return StockMovement::query()
            ->with(['product', 'site', 'inventoryDocument'])
            ->forProduct(isset($filters['product_id']) ? (int) $filters['product_id'] : null)
            ->forSite(isset($filters['site_id']) ? (int) $filters['site_id'] : null)
            ->type($filters['movement_type'] ?? null)
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest()
            ->get();
    }

    public function stockTransferHistory(array $filters = []): Collection
    {
        return $this->documentsByType('transfer', $filters);
    }

    public function stockTakeVariance(array $filters = []): Collection
    {
        return InventoryDocumentItem::query()
            ->with(['inventoryDocument.sourceSite', 'product'])
            ->whereHas('inventoryDocument', function ($query) use ($filters) {
                $query->where('document_type', 'stock_take')
                    ->when(isset($filters['site_id']), fn ($query) => $query->where('source_site_id', $filters['site_id']))
                    ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('document_date', '>=', $filters['date_from']))
                    ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('document_date', '<=', $filters['date_to']));
            })
            ->where('variance_quantity', '!=', 0)
            ->get()
            ->map(fn (InventoryDocumentItem $item) => [
                'document_id' => $item->inventory_document_id,
                'document_number' => $item->inventoryDocument?->document_number,
                'site_id' => $item->inventoryDocument?->source_site_id,
                'site_name' => $item->inventoryDocument?->sourceSite?->name,
                'product_id' => $item->product_id,
                'product_code' => $item->product?->product_code,
                'product_name' => $item->product?->product_name,
                'system_quantity' => $item->system_quantity,
                'counted_quantity' => $item->counted_quantity,
                'variance_quantity' => $item->variance_quantity,
            ]);
    }

    public function expenses(array $filters = []): Collection
    {
        return Expense::query()
            ->with(['expenseCategory', 'paymentAccount', 'site', 'creator'])
            ->when(isset($filters['expense_category_id']), fn ($query) => $query->where('expense_category_id', $filters['expense_category_id']))
            ->when(isset($filters['payment_account_id']), fn ($query) => $query->where('payment_account_id', $filters['payment_account_id']))
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_id', $filters['site_id']))
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('expense_date')
            ->get();
    }

    private function stockLevelQuery(array $filters = [])
    {
        return DB::table('site_stocks')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_stocks.site_id', $filters['site_id']))
            ->selectRaw('sites.id as site_id, sites.name as site_name')
            ->selectRaw('products.id as product_id, products.product_code, products.product_name')
            ->selectRaw('site_stocks.quantity_on_hand, site_stocks.reserved_quantity')
            ->selectRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) as available_quantity')
            ->selectRaw('COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0) as low_stock_level')
            ->orderBy('sites.name')
            ->orderBy('products.product_name');
    }

    private function sellingProductsQuery(array $filters = [])
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
            ->groupBy('products.id', 'products.product_code', 'products.product_name');
    }

    private function documentsByType(string $documentType, array $filters = []): Collection
    {
        return InventoryDocument::query()
            ->with(['contact', 'sourceSite', 'destinationSite', 'items.product'])
            ->where('document_type', $documentType)
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->forSite(isset($filters['site_id']) ? (int) $filters['site_id'] : null)
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('document_date')
            ->get();
    }
}
