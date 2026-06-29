<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryDocument;
use App\Services\InventoryDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class SalesController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(): View
    {
        $sales = $this->inventoryDocumentService->listByType('sale');

        return view('sales.index', [
            'title' => 'Sales',
            'description' => 'Review recent invoices, payment status, branch activity, and gross profit.',
            'sales' => $this->saleRows($sales),
            'summary' => $this->summary($sales),
        ]);
    }

    private function saleRows(Collection $sales): array
    {
        return $sales
            ->loadMissing('items')
            ->map(fn (InventoryDocument $sale): array => [
                'invoice' => $sale->document_number,
                'branch' => $sale->sourceSite?->name ?? 'Unassigned',
                'customer' => $sale->contact?->name ?? 'Walk-in',
                'items' => $sale->items->count(),
                'total' => $this->money((float) $sale->total_amount),
                'profit' => $this->money((float) $sale->items->sum('profit_amount')),
                'status' => str($sale->payment_status)->headline()->toString(),
                'payment_tone' => match ($sale->payment_status) {
                    'paid' => 'success',
                    'partial' => 'warning',
                    default => 'danger',
                },
            ])
            ->all();
    }

    private function summary(Collection $sales): array
    {
        $grossSales = (float) $sales->sum('total_amount');
        $profit = (float) $sales->loadMissing('items')->sum(fn (InventoryDocument $sale) => $sale->items->sum('profit_amount'));
        $creditSales = (float) $sales->where('balance_amount', '>', 0)->sum('balance_amount');

        return [
            ['label' => 'Gross sales', 'value' => $this->money($grossSales)],
            ['label' => 'Gross profit', 'value' => $this->money($profit)],
            ['label' => 'Credit sales', 'value' => $this->money($creditSales)],
            ['label' => 'Transactions', 'value' => (string) $sales->count()],
        ];
    }

    private function money(float $amount): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($amount, 0);
    }
}
