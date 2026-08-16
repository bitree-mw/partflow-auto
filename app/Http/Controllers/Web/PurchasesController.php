<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryDocument;
use App\Models\Payment;
use App\Services\ContactService;
use App\Services\InventoryDocumentService;
use App\Services\PaymentService;
use App\Services\PaymentAccountService;
use App\Services\ProductService;
use App\Services\SiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchasesController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly ContactService $contactService,
        private readonly SiteService $siteService,
        private readonly ProductService $productService,
        private readonly PaymentAccountService $paymentAccountService,
        private readonly PaymentService $paymentService
    ) {}

    public function index(Request $request): View
    {
        $purchases = $this->inventoryDocumentService->listByType('purchase', [], $request->user());

        return view('purchases.index', [
            'title' => 'Purchases',
            'description' => 'Receive parts from suppliers, track payable balances, and monitor stock that has entered each branch.',
            'summary' => $this->summary($purchases),
            'purchases' => $this->purchaseRows($purchases),
        ]);
    }

    public function create(Request $request): View
    {
        $prefillItem = [];

        if ($request->filled('product_id')) {
            $prefillItem = [
                'product_id' => (int) $request->query('product_id'),
                'quantity' => max((int) $request->query('quantity', 1), 1),
                'unit_cost' => null,
            ];
        }

        return view('purchases.create', [
            'title' => 'Add Purchase',
            'description' => 'Record parts bought from a supplier and receive stock into the selected site.',
            'suppliers' => $this->contactOptions($this->contactService->list()->filter->isSupplier()->values()),
            'sites' => $this->siteOptions($this->siteService->list(
                ['is_active' => true],
                $request->user(),
                \App\Services\SiteAccessService::RECEIVE_STOCK
            )),
            'parts' => $this->productOptions($this->productService->list(['is_active' => true])),
            'paymentAccounts' => $this->paymentAccountOptions($this->paymentAccountService->list(['is_active' => true])),
            'documentStatuses' => ['draft' => 'Draft', 'completed' => 'Completed and received'],
            'paymentMethods' => ['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'card' => 'Card', 'bank' => 'Bank Transfer'],
            'prefillItem' => $prefillItem,
            'prefillDestinationSiteId' => $request->query('destination_site_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'destination_site_id' => ['required', 'integer', 'exists:sites,id'],
            'document_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,completed'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank,mobile_money,card'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $items = collect($validated['items'] ?? [])
            ->filter(fn (array $item): bool => ! empty($item['product_id']) && ! empty($item['quantity']))
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_cost' => isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
            ])
            ->values()
            ->all();

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one received part line.',
            ]);
        }

        $documentDate = filled($validated['document_date'] ?? null) ? $validated['document_date'] : now();

        $payload = [
            'contact_id' => $validated['contact_id'] ?? null,
            'destination_site_id' => $validated['destination_site_id'],
            'document_date' => $documentDate,
            'status' => $validated['status'] ?? 'completed',
            'notes' => $validated['notes'] ?? null,
            'items' => $items,
        ];

        if ((float) ($validated['amount_paid'] ?? 0) > 0) {
            if (empty($validated['payment_account_id'])) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Choose the account that made the payment.',
                ]);
            }

            $payload['payment'] = [
                'payment_account_id' => $validated['payment_account_id'],
                'amount' => (float) $validated['amount_paid'],
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_date' => $documentDate,
            ];
        }

        $this->inventoryDocumentService->createPurchase($payload, $request->user());

        return redirect()
            ->route('web.purchases.index')
            ->with('success', 'Purchase saved and stock updated successfully.');
    }

    public function edit(InventoryDocument $inventoryDocument): View
    {
        $purchase = $this->purchaseDocument($inventoryDocument);

        return view('purchases.edit', [
            'title' => 'Edit Purchase',
            'description' => 'Update supplier details, operational notes, and purchase payments.',
            'purchase' => $purchase,
            'suppliers' => $this->contactOptions($this->contactService->list()->filter->isSupplier()->values()),
            'paymentAccounts' => $this->paymentAccountOptions($this->paymentAccountService->list(['is_active' => true])),
            'paymentMethods' => ['cash' => 'Cash', 'mobile_money' => 'Mobile Money', 'card' => 'Card', 'bank' => 'Bank Transfer'],
            'currency' => config('services.partflow.base_currency', 'MWK'),
        ]);
    }

    public function update(Request $request, InventoryDocument $inventoryDocument): RedirectResponse
    {
        $purchase = $this->purchaseDocument($inventoryDocument);

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

        $purchase->forceFill([
            'contact_id' => $validated['contact_id'] ?? null,
            'document_date' => $documentDate,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        $amountPaid = (float) ($validated['amount_paid'] ?? 0);

        if ($amountPaid > 0) {
            if (empty($validated['payment_account_id'])) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Choose the account that made the payment.',
                ]);
            }

            if ($amountPaid > (float) $purchase->balance_amount + 0.01) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Payment amount exceeds the remaining purchase balance.',
                ]);
            }

            $this->paymentService->createForDocument($purchase, [
                'payment_account_id' => $validated['payment_account_id'],
                'amount' => $amountPaid,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_date' => $documentDate,
            ], $request->user());
        } else {
            $this->paymentService->refreshDocumentPaymentStatus($purchase);
        }

        return redirect()
            ->route('web.purchases.edit', $purchase)
            ->with('success', 'Purchase updated successfully.');
    }

    public function destroyPayment(InventoryDocument $inventoryDocument, Payment $payment): RedirectResponse
    {
        $purchase = $this->purchaseDocument($inventoryDocument);

        abort_unless((int) $payment->inventory_document_id === (int) $purchase->id, 404);

        $this->paymentService->delete($payment, auth()->user());

        return redirect()
            ->route('web.purchases.edit', $purchase)
            ->with('success', 'Payment removed successfully.');
    }

    private function summary(Collection $purchases): array
    {
        $payable = (float) $purchases->sum('balance_amount');
        $receivedItems = (int) $purchases->sum(fn (InventoryDocument $purchase) => $purchase->items->sum('quantity'));

        return [
            ['label' => 'Purchase value', 'value' => $this->money((float) $purchases->sum('total_amount')), 'detail' => 'All recorded purchases', 'tone' => 'neutral'],
            ['label' => 'Supplier payable', 'value' => $this->money($payable), 'detail' => 'Partial and unpaid purchases', 'tone' => $payable > 0 ? 'warning' : 'success'],
            ['label' => 'Received items', 'value' => (string) $receivedItems, 'detail' => 'Across active sites', 'tone' => 'success'],
            ['label' => 'Open documents', 'value' => (string) $purchases->where('status', 'draft')->count(), 'detail' => 'Draft purchases not yet received', 'tone' => 'danger'],
        ];
    }

    private function purchaseRows(Collection $purchases): array
    {
        return $purchases
            ->loadMissing('items')
            ->map(fn (InventoryDocument $purchase): array => [
                'id' => $purchase->id,
                'number' => $purchase->document_number,
                'date' => $purchase->document_date?->format('M j, Y') ?? 'Not dated',
                'time' => $purchase->document_date?->format('g:i A') ?? '',
                'supplier' => $purchase->contact?->name ?? 'Unassigned supplier',
                'site' => $purchase->destinationSite?->name ?? 'Unassigned site',
                'items' => $purchase->items->count(),
                'total' => $this->money((float) $purchase->total_amount),
                'paid' => $this->money((float) $purchase->paid_amount),
                'status' => str($purchase->payment_status)->headline()->toString(),
                'tone' => match ($purchase->payment_status) {
                    'paid' => 'success',
                    'partial' => 'warning',
                    default => 'danger',
                },
            ])
            ->all();
    }

    private function purchaseDocument(InventoryDocument $inventoryDocument): InventoryDocument
    {
        abort_unless($inventoryDocument->document_type === 'purchase', 404);

        return $this->inventoryDocumentService->show($inventoryDocument, auth()->user());
    }

    private function contactOptions(Collection $contacts): array
    {
        return $contacts
            ->map(fn ($contact): array => ['id' => $contact->id, 'label' => "{$contact->name} ({$contact->code})"])
            ->all();
    }

    private function siteOptions(Collection $sites): array
    {
        return $sites
            ->map(fn ($site): array => ['id' => $site->id, 'label' => "{$site->name} ({$site->code})"])
            ->all();
    }

    private function productOptions(Collection $products): array
    {
        return $products
            ->map(fn ($product): array => ['id' => $product->id, 'label' => "{$product->product_name} ({$product->product_code})"])
            ->all();
    }

    private function paymentAccountOptions(Collection $accounts): array
    {
        return $accounts
            ->map(fn ($account): array => ['id' => $account->id, 'label' => "{$account->account_name} ({$account->account_type})"])
            ->all();
    }

    private function money(float $amount): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($amount, 0);
    }
}
