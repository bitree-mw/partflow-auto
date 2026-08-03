<?php

namespace App\Services;

use App\Models\InventoryDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AlertService
{
    public function all(array $filters = []): Collection
    {
        return $this->stockAlerts($filters)
            ->merge($this->pendingTransferAlerts($filters))
            ->merge($this->customerBalanceAlerts($filters))
            ->sortBy(fn (array $alert) => sprintf(
                '%d-%s-%s',
                $alert['priority_rank'],
                $alert['type'],
                $alert['item']
            ))
            ->values()
            ->map(fn (array $alert) => collect($alert)->except('priority_rank')->all());
    }

    public function summary(array $filters = []): array
    {
        $alerts = $this->all($filters);

        return [
            'count' => $alerts->count(),
            'high_count' => $alerts->where('priority', 'High')->count(),
            'latest' => $alerts->take(3)->values(),
        ];
    }

    private function stockAlerts(array $filters = []): Collection
    {
        return DB::table('site_stocks')
            ->join('sites', 'sites.id', '=', 'site_stocks.site_id')
            ->join('products', 'products.id', '=', 'site_stocks.product_id')
            ->when(isset($filters['site_id']), fn ($query) => $query->where('site_stocks.site_id', $filters['site_id']))
            ->whereRaw('COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0) > 0')
            ->whereRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) <= COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0)')
            ->selectRaw('products.id as product_id, products.product_name, products.product_code, sites.id as site_id, sites.name as site_name')
            ->selectRaw('(site_stocks.quantity_on_hand - site_stocks.reserved_quantity) as available_quantity')
            ->selectRaw('COALESCE(site_stocks.low_stock_level, products.default_low_stock_level, 0) as low_stock_level')
            ->orderBy('available_quantity')
            ->limit((int) ($filters['limit'] ?? 25))
            ->get()
            ->map(function ($row) {
                $available = (int) $row->available_quantity;
                $recommended = max((int) $row->low_stock_level, 0);
                $isOut = $available <= 0;

                return [
                    'type' => $isOut ? 'Out of stock' : 'Low stock',
                    'item' => $row->product_name,
                    'item_code' => $row->product_code,
                    'branch' => $row->site_name,
                    'detail' => "{$available} available, recommended {$recommended}",
                    'priority' => $isOut || $available <= floor($recommended / 2) ? 'High' : 'Medium',
                    'priority_tone' => $isOut || $available <= floor($recommended / 2) ? 'danger' : 'warning',
                    'priority_rank' => $isOut || $available <= floor($recommended / 2) ? 0 : 1,
                    'available' => $available,
                    'recommended' => $recommended,
                    'review_url' => route('web.catalog.products.edit', $row->product_id),
                    'purchase_url' => route('web.purchases.create', [
                        'product_id' => $row->product_id,
                        'destination_site_id' => $row->site_id,
                        'quantity' => max($recommended - $available, 1),
                    ]),
                ];
            });
    }

    private function pendingTransferAlerts(array $filters = []): Collection
    {
        return InventoryDocument::query()
            ->with(['sourceSite', 'destinationSite'])
            ->where('document_type', 'transfer')
            ->whereIn('status', ['draft', 'pending'])
            ->when(isset($filters['site_id']), fn ($query) => $query->forSite((int) $filters['site_id']))
            ->latest('document_date')
            ->limit(8)
            ->get()
            ->map(fn (InventoryDocument $document) => [
                'type' => 'Transfer pending',
                'item' => $document->document_number,
                'item_code' => $document->document_number,
                'branch' => $document->sourceSite?->name ?? $document->destinationSite?->name ?? 'Unassigned',
                'detail' => 'Transfer awaiting approval or completion',
                'priority' => 'Medium',
                'priority_tone' => 'warning',
                'priority_rank' => 1,
                'available' => null,
                'recommended' => null,
                'review_url' => route('web.purchases.index'),
                'purchase_url' => null,
            ]);
    }

    private function customerBalanceAlerts(array $filters = []): Collection
    {
        return DB::table('inventory_documents')
            ->leftJoin('contacts', 'contacts.id', '=', 'inventory_documents.contact_id')
            ->leftJoin('sites', 'sites.id', '=', 'inventory_documents.source_site_id')
            ->where('inventory_documents.document_type', 'sale')
            ->where('inventory_documents.balance_amount', '>', 0)
            ->when(isset($filters['site_id']), fn ($query) => $query->where('inventory_documents.source_site_id', $filters['site_id']))
            ->selectRaw('contacts.id as contact_id, contacts.name as customer_name, sites.name as site_name')
            ->selectRaw('SUM(inventory_documents.balance_amount) as balance_amount')
            ->groupBy('contacts.id', 'contacts.name', 'sites.name')
            ->orderByDesc('balance_amount')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'type' => 'Customer balance',
                'item' => $row->customer_name ?? 'Walk-in customer',
                'item_code' => null,
                'branch' => $row->site_name ?? 'Unassigned',
                'detail' => $this->formatCurrency((float) $row->balance_amount).' outstanding',
                'priority' => ((float) $row->balance_amount) >= 500000 ? 'High' : 'Medium',
                'priority_tone' => ((float) $row->balance_amount) >= 500000 ? 'danger' : 'warning',
                'priority_rank' => ((float) $row->balance_amount) >= 500000 ? 0 : 1,
                'available' => null,
                'recommended' => null,
                'review_url' => route('web.customers.index', ['search' => $row->customer_name]),
                'purchase_url' => null,
            ]);
    }

    private function formatCurrency(float $value): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($value, 0);
    }
}
