<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryDocument;
use App\Models\Payment;
use App\Services\ContactService;
use App\Services\InventoryDocumentService;
use App\Services\PaymentAccountService;
use App\Services\PaymentService;
use App\Services\SiteAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly ContactService $contactService,
        private readonly PaymentAccountService $paymentAccountService,
        private readonly PaymentService $paymentService,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function index(Request $request): View
    {
        $siteId = $this->selectedSiteId($request);
        $sales = $this->inventoryDocumentService->listByType(
            'sale',
            $siteId ? ['site_id' => $siteId] : [],
            $request->user()
        );

        return view('sales.index', [
            'title' => 'Sales',
            'description' => 'Review recent invoices, payment status, branch activity, and gross profit.',
            'sales' => $this->saleRows($sales),
            'summary' => $this->summary($sales),
        ]);
    }

    public function edit(InventoryDocument $inventoryDocument): View
    {
        $sale = $this->saleDocument($inventoryDocument);

        return view('sales.edit', [
            'title' => 'Edit Sale',
            'description' => 'Update customer details, notes, and payments recorded against this sale.',
            'sale' => $sale,
            'customers' => $this->contactOptions($this->contactService->list()->filter->isCustomer()->values()),
            'paymentAccounts' => $this->paymentAccountOptions($this->paymentAccountService->list(['is_active' => true])),
            'paymentMethods' => ['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'card' => 'Card', 'bank' => 'Bank Transfer'],
            'currency' => config('services.partflow.base_currency', 'MWK'),
        ]);
    }

    public function update(Request $request, InventoryDocument $inventoryDocument): RedirectResponse
    {
        $sale = $this->saleDocument($inventoryDocument);

        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'document_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank,mobile_money,card'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $documentDate = filled($validated['document_date'] ?? null) ? $validated['document_date'] : now();

        $sale->forceFill([
            'contact_id' => $validated['contact_id'] ?? null,
            'document_date' => $documentDate,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        $amountPaid = (float) ($validated['amount_paid'] ?? 0);

        if ($amountPaid > 0) {
            if (empty($validated['payment_account_id'])) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Choose the account receiving this payment.',
                ]);
            }

            if ($amountPaid > (float) $sale->balance_amount + 0.01) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Payment amount exceeds the remaining sale balance.',
                ]);
            }

            $this->paymentService->createForDocument($sale, [
                'payment_account_id' => $validated['payment_account_id'],
                'amount' => $amountPaid,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_date' => $documentDate,
            ], $request->user());
        } else {
            $this->paymentService->refreshDocumentPaymentStatus($sale);
        }

        return redirect()
            ->route('web.sales.edit', $sale)
            ->with('success', 'Sale updated successfully.');
    }

    public function destroyPayment(InventoryDocument $inventoryDocument, Payment $payment): RedirectResponse
    {
        $sale = $this->saleDocument($inventoryDocument);

        abort_unless((int) $payment->inventory_document_id === (int) $sale->id, 404);

        $this->paymentService->delete($payment, auth()->user());

        return redirect()
            ->route('web.sales.edit', $sale)
            ->with('success', 'Payment removed successfully.');
    }

    private function saleRows(Collection $sales): array
    {
        return $sales
            ->loadMissing('items')
            ->map(fn (InventoryDocument $sale): array => [
                'id' => $sale->id,
                'invoice' => $sale->document_number,
                'date' => $sale->document_date?->format('M j, Y') ?? 'Not dated',
                'time' => $sale->document_date?->format('g:i A') ?? '',
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

    private function selectedSiteId(Request $request): ?int
    {
        $siteId = (int) $request->session()->get('pos_site_id', 0);

        if ($siteId <= 0) {
            return null;
        }

        return in_array($siteId, $this->siteAccessService->allowedSiteIds($request->user()), true)
            ? $siteId
            : null;
    }

    private function saleDocument(InventoryDocument $inventoryDocument): InventoryDocument
    {
        abort_unless($inventoryDocument->document_type === 'sale', 404);

        return $this->inventoryDocumentService->show($inventoryDocument, auth()->user());
    }

    private function contactOptions(Collection $contacts): array
    {
        return $contacts
            ->map(fn ($contact): array => ['id' => $contact->id, 'label' => "{$contact->name} ({$contact->code})"])
            ->all();
    }

    private function paymentAccountOptions(Collection $accounts): array
    {
        return $accounts
            ->map(fn ($account): array => ['id' => $account->id, 'label' => "{$account->account_name} ({$account->account_type})"])
            ->all();
    }
}
