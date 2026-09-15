<?php

namespace App\Repositories;

use App\Models\Expense;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\SiteStock;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportRepository
{
    public function currentStockBySite(array $filters = []): Collection
    {
        return SiteStock::query()
            ->with(['site', 'product.carModel', 'product.productType', 'product.fuelType', 'product.brand'])
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('site_id', $filters['site_ids']))
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
            ->whereRaw('COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0) > 0')
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0)')
            ->get();
    }

    public function outOfStockProducts(array $filters = []): Collection
    {
        return $this->stockLevelQuery($filters)
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= 0')
            ->get();
    }

    public function stockValuation(array $filters = [], float $maximumDiscountPercentage = 20): Collection
    {
        $minimumAuthorizedPrice = $this->minimumAuthorizedPriceSql($maximumDiscountPercentage);

        return DB::table('site_stocks')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->leftJoinSub($this->latestPurchaseCostSubquery(), 'latest_purchase_costs', function ($join) {
                $join->on('latest_purchase_costs.product_id', '=', 'site_stocks.product_id');
            })
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_stocks.site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('site_stocks.site_id', $filters['site_ids']))
            ->selectRaw('sites.id as site_id, sites.name as site_name')
            ->selectRaw("SUM(site_stocks.quantity_on_hand * ({$minimumAuthorizedPrice})) as stock_value")
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
            ->when(isset($filters['site_id']), fn ($query) => $query->where('inventory_documents.source_site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('inventory_documents.source_site_id', $filters['site_ids']))
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
            ->when(isset($filters['site_id']), fn ($query) => $query->where('inventory_documents.source_site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('inventory_documents.source_site_id', $filters['site_ids']))
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
            ->when(isset($filters['site_id']), fn ($query) => $query->where('inventory_documents.source_site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('inventory_documents.source_site_id', $filters['site_ids']))
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
            ->join('inventory_documents', 'inventory_documents.id', '=', 'payments.inventory_document_id')
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('payments.payment_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('payments.payment_date', '<=', $filters['date_to']))
            ->when(isset($filters['payment_account_id']), fn ($query) => $query->where('payment_accounts.id', $filters['payment_account_id']))
            ->when(isset($filters['site_id']), function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('inventory_documents.source_site_id', $filters['site_id'])
                        ->orWhere('inventory_documents.destination_site_id', $filters['site_id']);
                });
            })
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $this->constrainInventoryDocumentSites($query, $filters['site_ids']))
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
            ->forSites($filters['site_ids'] ?? null)
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
                    ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('source_site_id', $filters['site_ids']))
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
            ->when(array_key_exists('site_ids', $filters), function ($query) use ($filters) {
                if ($filters['include_unassigned_site'] ?? false) {
                    $query->where(fn ($query) => $query->whereNull('site_id')->orWhereIn('site_id', $filters['site_ids']));

                    return;
                }

                $query->whereIn('site_id', $filters['site_ids']);
            })
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('expense_date')
            ->get();
    }

    public function fullFieldReportRows(string $reportType, array $filters = []): Collection
    {
        return match ($this->normalizeReportType($reportType)) {
            'sales',
            'sale',
            'most-selling-products',
            'least-selling-products',
            'profit-by-product',
            'profit-by-site' => $this->inventoryDocumentLineRows('sale', $filters),
            'purchases',
            'purchase' => $this->inventoryDocumentLineRows('purchase', $filters),
            'sale-returns',
            'sale-return' => $this->inventoryDocumentLineRows('sale_return', $filters),
            'purchase-returns',
            'purchase-return' => $this->inventoryDocumentLineRows('purchase_return', $filters),
            'stock-transfers',
            'stock-transfer',
            'transfers',
            'transfer' => $this->inventoryDocumentLineRows('transfer', $filters),
            'stock-take-variance' => $this->inventoryDocumentLineRows('stock_take', $filters),
            'stock-movements',
            'stock-movement' => $this->stockMovementRows($filters),
            'payments',
            'payment',
            'payments-by-account' => $this->paymentRows($filters),
            'expenses' => $this->expenseRows($filters),
            'profit-and-loss' => $this->profitAndLossRows($filters),
            'inventory',
            'inventory-report',
            'inventory-valuation',
            'current-stock',
            'current-stock-by-site',
            'low-stock',
            'low-stock-by-site',
            'out-of-stock',
            'out-of-stock-products',
            'stock-valuation' => $this->stockRows($filters),
            'creditors',
            'creditor-report',
            'creditor-balances' => $this->creditorBalanceRows($filters),
            'debtors',
            'debtor-report',
            'debtor-balances',
            'customer-balances' => $this->customerBalanceRows($filters),
            default => throw new InvalidArgumentException("Unsupported report type [{$reportType}]."),
        };
    }

    public function normalizeReportType(string $reportType): string
    {
        return str($reportType)
            ->lower()
            ->replace(['_', ' '], '-')
            ->replace('-by-date-range', '')
            ->replace('-history', '')
            ->toString();
    }

    private function stockLevelQuery(array $filters = [])
    {
        return DB::table('site_stocks')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_stocks.site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('site_stocks.site_id', $filters['site_ids']))
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
            ->when(isset($filters['site_id']), fn ($query) => $query->where('inventory_documents.source_site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('inventory_documents.source_site_id', $filters['site_ids']))
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
            ->forSites($filters['site_ids'] ?? null)
            ->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->latest('document_date')
            ->get();
    }

    private function inventoryDocumentLineRows(string $documentType, array $filters = []): Collection
    {
        return DB::table('inventory_documents')
            ->leftJoin('inventory_document_items', 'inventory_document_items.inventory_document_id', '=', 'inventory_documents.id')
            ->leftJoin('products', 'products.id', '=', 'inventory_document_items.product_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'inventory_documents.contact_id')
            ->leftJoin('sites as source_sites', 'source_sites.id', '=', 'inventory_documents.source_site_id')
            ->leftJoin('sites as destination_sites', 'destination_sites.id', '=', 'inventory_documents.destination_site_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'inventory_documents.created_by')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'inventory_documents.approved_by')
            ->where('inventory_documents.document_type', $documentType)
            ->when(isset($filters['status']), fn ($query) => $query->where('inventory_documents.status', $filters['status']))
            ->when(isset($filters['site_id']), function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('inventory_documents.source_site_id', $filters['site_id'])
                        ->orWhere('inventory_documents.destination_site_id', $filters['site_id']);
                });
            })
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $this->constrainInventoryDocumentSites($query, $filters['site_ids']))
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('inventory_documents.document_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('inventory_documents.document_date', '<=', $filters['date_to']))
            ->orderByDesc('inventory_documents.document_date')
            ->orderByDesc('inventory_documents.document_number')
            ->orderBy('inventory_document_items.id')
            ->when(isset($filters['preview_limit']), fn ($query) => $query->limit((int) $filters['preview_limit']))
            ->get([
                'inventory_documents.id as document_id',
                'inventory_documents.document_number',
                'inventory_documents.document_type',
                'inventory_documents.document_date',
                'inventory_documents.status',
                'inventory_documents.payment_status',
                'contacts.name as contact_name',
                'contacts.phone as contact_phone',
                'source_sites.name as source_site',
                'destination_sites.name as destination_site',
                'products.product_code',
                'products.product_name',
                'inventory_document_items.quantity',
                'inventory_document_items.unit_cost',
                'inventory_document_items.unit_price',
                'inventory_document_items.discount_amount as line_discount_amount',
                'inventory_document_items.tax_rate',
                'inventory_document_items.tax_amount as line_tax_amount',
                'inventory_document_items.line_total',
                'inventory_document_items.profit_amount',
                'inventory_document_items.system_quantity',
                'inventory_document_items.counted_quantity',
                'inventory_document_items.variance_quantity',
                'inventory_document_items.notes as item_notes',
                'inventory_documents.subtotal_amount',
                'inventory_documents.discount_amount as document_discount_amount',
                'inventory_documents.taxable_amount',
                'inventory_documents.tax_amount as document_tax_amount',
                'inventory_documents.total_amount',
                'inventory_documents.paid_amount',
                'inventory_documents.balance_amount',
                'inventory_documents.notes as document_notes',
                'creators.name as created_by',
                'approvers.name as approved_by',
            ]);
    }

    private function paymentRows(array $filters = []): Collection
    {
        return DB::table('payments')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'payments.inventory_document_id')
            ->join('payment_accounts', 'payment_accounts.id', '=', 'payments.payment_account_id')
            ->join('users', 'users.id', '=', 'payments.received_by')
            ->leftJoin('contacts', 'contacts.id', '=', 'inventory_documents.contact_id')
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('payments.payment_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('payments.payment_date', '<=', $filters['date_to']))
            ->when(isset($filters['payment_account_id']), fn ($query) => $query->where('payments.payment_account_id', $filters['payment_account_id']))
            ->when(isset($filters['site_id']), function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('inventory_documents.source_site_id', $filters['site_id'])
                        ->orWhere('inventory_documents.destination_site_id', $filters['site_id']);
                });
            })
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $this->constrainInventoryDocumentSites($query, $filters['site_ids']))
            ->orderByDesc('payments.payment_date')
            ->get([
                'payments.id as payment_id',
                'payments.payment_date',
                'inventory_documents.document_number',
                'inventory_documents.document_type',
                'contacts.name as contact_name',
                'payment_accounts.account_name',
                'payment_accounts.account_type',
                'payments.payment_method',
                'payments.amount',
                'payments.transaction_reference',
                'users.name as received_by',
                'payments.notes',
            ]);
    }

    private function expenseRows(array $filters = []): Collection
    {
        return DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('payment_accounts', 'payment_accounts.id', '=', 'expenses.payment_account_id')
            ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
            ->join('users', 'users.id', '=', 'expenses.created_by')
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('expenses.expense_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('expenses.expense_date', '<=', $filters['date_to']))
            ->when(isset($filters['expense_category_id']), fn ($query) => $query->where('expenses.expense_category_id', $filters['expense_category_id']))
            ->when(isset($filters['site_id']), fn ($query) => $query->where('expenses.site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), function ($query) use ($filters) {
                if ($filters['include_unassigned_site'] ?? false) {
                    $query->where(fn ($query) => $query->whereNull('expenses.site_id')->orWhereIn('expenses.site_id', $filters['site_ids']));

                    return;
                }

                $query->whereIn('expenses.site_id', $filters['site_ids']);
            })
            ->orderByDesc('expenses.expense_date')
            ->get([
                'expenses.id as expense_id',
                'expenses.expense_date',
                'expense_categories.name as expense_category',
                'sites.name as site_name',
                'payment_accounts.account_name',
                'payment_accounts.account_type',
                'expenses.amount',
                'expenses.reference',
                'expenses.description',
                'users.name as created_by',
            ]);
    }

    private function profitAndLossRows(array $filters = []): Collection
    {
        $incomeRows = $this->inventoryDocumentLineRows('sale', $filters)->map(fn ($row) => [
            'transaction_date' => $row->document_date,
            'account_type' => 'Income',
            'account_name' => 'Sales Revenue',
            'document_number' => $row->document_number,
            'site_name' => $row->source_site,
            'contact_name' => $row->contact_name,
            'product_code' => $row->product_code,
            'product_name' => $row->product_name,
            'quantity' => $row->quantity,
            'income_amount' => $row->line_total,
            'cost_amount' => $row->unit_cost !== null && $row->quantity !== null ? (float) $row->unit_cost * (int) $row->quantity : 0,
            'expense_amount' => 0,
            'profit_or_loss' => $row->profit_amount,
            'notes' => $row->item_notes ?: $row->document_notes,
        ]);

        $expenseRows = $this->expenseRows($filters)->map(fn ($row) => [
            'transaction_date' => $row->expense_date,
            'account_type' => 'Expense',
            'account_name' => $row->expense_category,
            'document_number' => $row->reference,
            'site_name' => $row->site_name,
            'contact_name' => null,
            'product_code' => null,
            'product_name' => null,
            'quantity' => null,
            'income_amount' => 0,
            'cost_amount' => 0,
            'expense_amount' => $row->amount,
            'profit_or_loss' => -1 * (float) $row->amount,
            'notes' => $row->description,
        ]);

        return $incomeRows
            ->merge($expenseRows)
            ->sortByDesc('transaction_date')
            ->values();
    }

    private function stockMovementRows(array $filters = []): Collection
    {
        return DB::table('stock_movements')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('sites', 'sites.id', '=', 'stock_movements.site_id')
            ->leftJoin('inventory_documents', 'inventory_documents.id', '=', 'stock_movements.inventory_document_id')
            ->join('users', 'users.id', '=', 'stock_movements.created_by')
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('stock_movements.created_at', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('stock_movements.created_at', '<=', $filters['date_to']))
            ->when(isset($filters['site_id']), fn ($query) => $query->where('stock_movements.site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('stock_movements.site_id', $filters['site_ids']))
            ->when(isset($filters['product_id']), fn ($query) => $query->where('stock_movements.product_id', $filters['product_id']))
            ->orderByDesc('stock_movements.created_at')
            ->get([
                'stock_movements.id as stock_movement_id',
                'stock_movements.created_at as movement_date',
                'stock_movements.movement_type',
                'inventory_documents.document_number',
                'sites.name as site_name',
                'products.product_code',
                'products.product_name',
                'stock_movements.quantity_change',
                'stock_movements.balance_before',
                'stock_movements.balance_after',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'users.name as created_by',
                'stock_movements.notes',
            ]);
    }

    private function stockRows(array $filters = []): Collection
    {
        $minimumAuthorizedPrice = $this->minimumAuthorizedPriceSql(
            (float) ($filters['maximum_discount_percentage'] ?? 20)
        );

        return DB::table('site_stocks')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->leftJoinSub($this->latestPurchaseCostSubquery(), 'latest_purchase_costs', function ($join) {
                $join->on('latest_purchase_costs.product_id', '=', 'site_stocks.product_id');
            })
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_stocks.site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('site_stocks.site_id', $filters['site_ids']))
            ->when(isset($filters['product_id']), fn ($query) => $query->where('site_stocks.product_id', $filters['product_id']))
            ->orderBy('sites.name')
            ->orderBy('products.product_name')
            ->when(isset($filters['preview_limit']), fn ($query) => $query->limit((int) $filters['preview_limit']))
            ->get([
                'sites.id as site_id',
                'sites.name as site_name',
                'products.id as product_id',
                'products.product_code',
                'products.product_name',
                'site_stocks.quantity_on_hand',
                'site_stocks.reserved_quantity',
                DB::raw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) as available_quantity'),
                DB::raw('COALESCE(latest_purchase_costs.unit_cost, products.default_purchase_price, 0) as unit_purchase_cost'),
                DB::raw('COALESCE(products.default_selling_price, 0) as unit_selling_price'),
                DB::raw('COALESCE(products.minimum_selling_price, 0) as minimum_selling_price'),
                DB::raw("({$minimumAuthorizedPrice}) as minimum_authorized_price"),
                DB::raw('site_stocks.quantity_on_hand * COALESCE(latest_purchase_costs.unit_cost, products.default_purchase_price, 0) as stock_cost_value'),
                DB::raw("site_stocks.quantity_on_hand * ({$minimumAuthorizedPrice}) as potential_sales_value"),
                DB::raw("site_stocks.quantity_on_hand * (({$minimumAuthorizedPrice}) - COALESCE(latest_purchase_costs.unit_cost, products.default_purchase_price, 0)) as potential_gross_margin"),
            ]);
    }

    private function minimumAuthorizedPriceSql(float $maximumDiscountPercentage): string
    {
        $percentage = max(0, min(100, $maximumDiscountPercentage));
        $factor = number_format(1 - ($percentage / 100), 6, '.', '');
        $sellingPrice = 'COALESCE(products.default_selling_price, 0)';
        $productMinimum = "CASE WHEN COALESCE(products.minimum_selling_price, 0) > {$sellingPrice} THEN {$sellingPrice} ELSE COALESCE(products.minimum_selling_price, 0) END";
        $adminMinimum = "{$sellingPrice} * {$factor}";

        return "CASE WHEN ({$productMinimum}) > ({$adminMinimum}) THEN ({$productMinimum}) ELSE ({$adminMinimum}) END";
    }

    private function latestPurchaseCostSubquery()
    {
        $latestPurchaseItems = DB::table('inventory_document_items')
            ->join('inventory_documents', 'inventory_documents.id', '=', 'inventory_document_items.inventory_document_id')
            ->where('inventory_documents.document_type', 'purchase')
            ->whereIn('inventory_documents.status', ['completed', 'approved'])
            ->where('inventory_document_items.unit_cost', '>', 0)
            ->selectRaw('inventory_document_items.product_id, MAX(inventory_document_items.id) as item_id')
            ->groupBy('inventory_document_items.product_id');

        return DB::table('inventory_document_items')
            ->joinSub($latestPurchaseItems, 'latest_purchase_items', function ($join) {
                $join->on('latest_purchase_items.item_id', '=', 'inventory_document_items.id');
            })
            ->selectRaw('inventory_document_items.product_id, inventory_document_items.unit_cost');
    }

    private function customerBalanceRows(array $filters = []): Collection
    {
        return DB::table('inventory_documents')
            ->join('contacts', 'contacts.id', '=', 'inventory_documents.contact_id')
            ->leftJoin('sites', 'sites.id', '=', 'inventory_documents.source_site_id')
            ->where('inventory_documents.document_type', 'sale')
            ->where('inventory_documents.balance_amount', '>', 0)
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('inventory_documents.document_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('inventory_documents.document_date', '<=', $filters['date_to']))
            ->when(isset($filters['contact_id']), fn ($query) => $query->where('contacts.id', $filters['contact_id']))
            ->when(isset($filters['site_id']), fn ($query) => $query->where('inventory_documents.source_site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('inventory_documents.source_site_id', $filters['site_ids']))
            ->orderByDesc('inventory_documents.document_date')
            ->when(isset($filters['preview_limit']), fn ($query) => $query->limit((int) $filters['preview_limit']))
            ->get([
                'inventory_documents.document_number',
                'inventory_documents.document_date',
                'contacts.name as customer_name',
                'contacts.phone',
                'sites.name as site_name',
                'inventory_documents.total_amount',
                'inventory_documents.paid_amount',
                'inventory_documents.balance_amount',
                'inventory_documents.payment_status',
                'inventory_documents.status',
            ]);
    }

    private function creditorBalanceRows(array $filters = []): Collection
    {
        return DB::table('inventory_documents')
            ->leftJoin('contacts', 'contacts.id', '=', 'inventory_documents.contact_id')
            ->leftJoin('sites', 'sites.id', '=', 'inventory_documents.destination_site_id')
            ->where('inventory_documents.document_type', 'purchase')
            ->where('inventory_documents.balance_amount', '>', 0)
            ->when(isset($filters['date_from']), fn ($query) => $query->whereDate('inventory_documents.document_date', '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn ($query) => $query->whereDate('inventory_documents.document_date', '<=', $filters['date_to']))
            ->when(isset($filters['contact_id']), fn ($query) => $query->where('contacts.id', $filters['contact_id']))
            ->when(isset($filters['site_id']), fn ($query) => $query->where('inventory_documents.destination_site_id', $filters['site_id']))
            ->when(array_key_exists('site_ids', $filters), fn ($query) => $query->whereIn('inventory_documents.destination_site_id', $filters['site_ids']))
            ->orderByDesc('inventory_documents.document_date')
            ->orderByDesc('inventory_documents.document_number')
            ->when(isset($filters['preview_limit']), fn ($query) => $query->limit((int) $filters['preview_limit']))
            ->get([
                'inventory_documents.document_number',
                'inventory_documents.document_date',
                'contacts.name as supplier_name',
                'contacts.phone',
                'sites.name as site_name',
                'inventory_documents.total_amount',
                'inventory_documents.paid_amount',
                'inventory_documents.balance_amount',
                'inventory_documents.payment_status',
                'inventory_documents.status',
            ]);
    }

    private function constrainInventoryDocumentSites($query, array $siteIds): void
    {
        $query
            ->where(function ($query) {
                $query->whereNotNull('inventory_documents.source_site_id')
                    ->orWhereNotNull('inventory_documents.destination_site_id');
            })
            ->where(function ($query) use ($siteIds) {
                $query->whereNull('inventory_documents.source_site_id')
                    ->orWhereIn('inventory_documents.source_site_id', $siteIds);
            })
            ->where(function ($query) use ($siteIds) {
                $query->whereNull('inventory_documents.destination_site_id')
                    ->orWhereIn('inventory_documents.destination_site_id', $siteIds);
            });
    }
}
