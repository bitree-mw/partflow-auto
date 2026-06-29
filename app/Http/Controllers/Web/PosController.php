<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteStock;
use App\Services\ContactService;
use App\Services\InventoryDocumentService;
use App\Services\PaymentAccountService;
use App\Services\PosProductSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function __construct(
        private readonly PosProductSearchService $posProductSearchService,
        private readonly PaymentAccountService $paymentAccountService,
        private readonly ContactService $contactService,
        private readonly InventoryDocumentService $inventoryDocumentService
    ) {}

    public function index(): View
    {
        $currentSite = Site::query()->active()->orderBy('name')->first();
        $currentBranch = $currentSite?->name ?? 'All sites';
        $products = $this->products($currentSite?->id);
        $selectedProduct = $products[0] ?? $this->emptyProduct($currentBranch);

        return view('pos', [
            'title' => 'Point Of Sale',
            'currentBranch' => $currentBranch,
            'currentSiteId' => $currentSite?->id,
            'cashier' => auth()->user()?->name ?? 'Cashier',
            'saleNumber' => 'Draft sale',
            'quickSearches' => collect($products)->pluck('part_type')->filter()->unique()->take(4)->values()->all(),
            'vehicleFilters' => collect(['All vehicles'])->merge(collect($products)->pluck('vehicle')->filter()->unique())->values()->all(),
            'partTypeFilters' => collect(['All part types'])->merge(collect($products)->pluck('part_type')->filter()->unique())->values()->all(),
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'cartLines' => [],
            'saleTotals' => [
                'subtotal' => $this->money(0),
                'discount' => $this->money(0),
                'tax' => $this->money(0),
                'total' => $this->money(0),
                'profit' => $this->money(0),
            ],
            'paymentAccounts' => $this->paymentAccountOptions(),
            'customers' => $this->customerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_site_id' => ['required', 'integer', 'exists:sites,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'cart_payload' => ['required', 'string'],
            'payment_account_id' => ['nullable', 'integer', 'exists:payment_accounts,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cart = json_decode($validated['cart_payload'], true);

        if (! is_array($cart)) {
            throw ValidationException::withMessages([
                'cart_payload' => 'The cart could not be read. Please rebuild the sale.',
            ]);
        }

        $items = collect($cart)
            ->filter(fn ($item): bool => ! empty($item['product_id']) && ! empty($item['quantity']))
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'discount_amount' => 0,
            ])
            ->values()
            ->all();

        if (empty($items)) {
            throw ValidationException::withMessages([
                'cart_payload' => 'Add at least one part before completing the sale.',
            ]);
        }

        $payload = [
            'source_site_id' => $validated['source_site_id'],
            'contact_id' => $validated['contact_id'] ?? null,
            'status' => 'completed',
            'items' => $items,
        ];

        if ((float) ($validated['amount_paid'] ?? 0) > 0) {
            if (empty($validated['payment_account_id'])) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Choose the account receiving this payment.',
                ]);
            }

            $payload['payment'] = [
                'payment_account_id' => $validated['payment_account_id'],
                'amount' => (float) $validated['amount_paid'],
                'payment_date' => now(),
            ];
        }

        $this->inventoryDocumentService->createSale($payload, $request->user());

        return redirect()
            ->route('web.pos')
            ->with('success', 'Sale completed successfully.');
    }

    private function products(?int $currentSiteId): array
    {
        return $this->posProductSearchService
            ->search()
            ->groupBy('product_id')
            ->map(fn (Collection $stocks): array => $this->product($stocks, $currentSiteId))
            ->values()
            ->all();
    }

    private function product(Collection $stocks, ?int $currentSiteId): array
    {
        /** @var SiteStock $primaryStock */
        $primaryStock = $stocks->firstWhere('site_id', $currentSiteId) ?? $stocks->first();
        $product = $primaryStock->product;
        $branchStock = $stocks->sortBy('site.name')->map(function (SiteStock $stock): array {
            $available = $stock->available_quantity;

            return [
                'branch' => $stock->site?->name ?? 'Unassigned',
                'on_hand' => $stock->quantity_on_hand,
                'reserved' => $stock->reserved_quantity,
                'available' => $available,
                'status' => $available === 0 ? 'out' : ($available <= 2 ? 'low' : 'ok'),
            ];
        })->values()->all();

        $totalAvailable = collect($branchStock)->sum('available');
        $bestBranch = collect($branchStock)->sortByDesc('available')->first();
        $price = (float) $product->default_selling_price;
        $cost = (float) $product->default_purchase_price;
        $margin = $price - $cost;
        $branchStockSummary = collect($branchStock)
            ->map(fn (array $branch): string => "{$branch['branch']} {$branch['available']}")
            ->implode(' - ');
        $references = $product->references;
        $vehicle = $this->vehicleLabel($product->carModel);
        $compatibleCars = collect([$vehicle])
            ->merge($product->compatibilities->map(fn ($compatibility) => $this->vehicleLabel($compatibility->carModel)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'product_id' => $product->id,
            'product_code' => $product->product_code,
            'product_name' => $product->product_name,
            'pos_description' => $product->pos_description ?: $product->description ?: $product->product_name,
            'vehicle' => $vehicle,
            'part_type' => $product->partType?->name ?? 'Unassigned',
            'brand' => $product->brand?->name ?? 'Unbranded',
            'part_country_of_origin' => $product->part_country_of_origin ?: 'Not set',
            'barcode' => $this->reference($references, 'barcode'),
            'oem_number' => $this->reference($references, 'oem_number'),
            'selling_price' => $price,
            'selling_price_display' => $this->money($price),
            'unit_cost' => $cost,
            'margin_display' => $this->money($margin),
            'tax_profile' => $product->taxProfile?->name ?? 'No tax profile',
            'compatible_cars' => $compatibleCars,
            'branch_stock' => $branchStock,
            'branch_stock_summary' => $branchStockSummary,
            'total_available' => $totalAvailable,
            'best_branch' => $bestBranch['branch'] ?? 'No stock',
            'best_branch_available' => $bestBranch['available'] ?? 0,
        ];
    }

    private function emptyProduct(string $currentBranch): array
    {
        return [
            'id' => null,
            'product_id' => null,
            'product_code' => 'N/A',
            'product_name' => 'No stocked parts yet',
            'pos_description' => 'Receive or add parts before selling from POS.',
            'vehicle' => 'No vehicle',
            'part_type' => 'No part type',
            'brand' => 'Unbranded',
            'part_country_of_origin' => 'Not set',
            'barcode' => 'N/A',
            'oem_number' => 'N/A',
            'selling_price' => 0,
            'selling_price_display' => $this->money(0),
            'unit_cost' => 0,
            'margin_display' => $this->money(0),
            'tax_profile' => 'No tax profile',
            'compatible_cars' => [],
            'branch_stock' => [[
                'branch' => $currentBranch,
                'on_hand' => 0,
                'reserved' => 0,
                'available' => 0,
                'status' => 'out',
            ]],
            'branch_stock_summary' => "{$currentBranch} 0",
            'total_available' => 0,
            'best_branch' => $currentBranch,
            'best_branch_available' => 0,
        ];
    }

    private function vehicleLabel($carModel): string
    {
        if (! $carModel) {
            return 'Universal fitment';
        }

        return collect([
            $carModel->make,
            $carModel->model,
            $carModel->year,
            $carModel->engine_size,
            $carModel->variant_name,
        ])->filter()->join(' ');
    }

    private function reference(Collection $references, string $type): string
    {
        return $references->firstWhere('reference_type', $type)?->reference_value ?? 'N/A';
    }

    private function money(float $amount): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($amount);
    }

    private function paymentAccountOptions(): array
    {
        return $this->paymentAccountService
            ->list(['is_active' => true])
            ->map(fn ($account) => [
                'id' => $account->id,
                'label' => "{$account->account_name} - ".str($account->account_type)->headline()->toString(),
            ])
            ->all();
    }

    private function customerOptions(): array
    {
        return $this->contactService
            ->list()
            ->filter->isCustomer()
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'label' => "{$contact->name} ({$contact->code})",
            ])
            ->all();
    }
}
