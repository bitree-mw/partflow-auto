<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $currentSite = Site::query()->active()->orderBy('name')->first();
        $currentBranch = $currentSite?->name ?? 'All sites';
        $products = $this->products($request, $currentSite?->id);
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
            'paymentAccounts' => $this->paymentAccountOptions($request),
            'customers' => $this->customerOptions($request),
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
                'payment_date' => now()->toDateTimeString(),
            ];
        }

        $response = $this->apiCall($request, 'POST', 'pos/sales', $payload);

        return redirect()
            ->route('web.pos')
            ->with('success', $response['message'] ?? 'Sale completed successfully.');
    }

    private function products(Request $request, ?int $currentSiteId): array
    {
        $response = $this->apiCall($request, 'GET', 'pos/products');

        return collect($response['data'] ?? [])
            ->groupBy('product_id')
            ->map(fn (Collection $stocks): array => $this->productFromApiRows($stocks, $currentSiteId))
            ->values()
            ->all();
    }

    private function productFromApiRows(Collection $stocks, ?int $currentSiteId): array
    {
        $primaryStock = $stocks->firstWhere('site_id', $currentSiteId) ?? $stocks->first();
        $branchStock = $stocks->sortBy('site_name')->map(function (array $stock): array {
            $available = (int) ($stock['available_quantity'] ?? 0);

            return [
                'branch' => $stock['site_name'] ?? 'Unassigned',
                'on_hand' => (int) ($stock['quantity_on_hand'] ?? 0),
                'reserved' => (int) ($stock['reserved_quantity'] ?? 0),
                'available' => $available,
                'status' => $available === 0 ? 'out' : ($available <= 2 ? 'low' : 'ok'),
            ];
        })->values()->all();

        $totalAvailable = collect($branchStock)->sum('available');
        $bestBranch = collect($branchStock)->sortByDesc('available')->first();
        $branchStockSummary = collect($branchStock)
            ->map(fn (array $branch): string => "{$branch['branch']} {$branch['available']}")
            ->implode(' - ');
        $compatibleCars = collect($primaryStock['compatible_cars'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $vehicle = $compatibleCars[0] ?? 'Universal fitment';
        $references = collect($primaryStock['references'] ?? []);
        $price = (float) ($primaryStock['selling_price'] ?? 0);
        $cost = (float) ($primaryStock['unit_cost'] ?? 0);
        $margin = (float) ($primaryStock['margin'] ?? ($price - $cost));

        return [
            'id' => $primaryStock['product_id'] ?? null,
            'product_id' => $primaryStock['product_id'] ?? null,
            'product_code' => $primaryStock['product_code'] ?? 'N/A',
            'product_name' => $primaryStock['product_name'] ?? 'Unnamed part',
            'pos_description' => $primaryStock['pos_description'] ?? ($primaryStock['product_name'] ?? 'Unnamed part'),
            'vehicle' => $vehicle,
            'part_type' => data_get($primaryStock, 'part_type.name', 'Unassigned'),
            'brand' => data_get($primaryStock, 'brand.name', 'Unbranded'),
            'part_country_of_origin' => $primaryStock['part_country_of_origin'] ?: 'Not set',
            'barcode' => $this->reference($references, 'barcode'),
            'oem_number' => $this->reference($references, 'oem_number'),
            'selling_price' => $price,
            'selling_price_display' => $this->money($price),
            'unit_cost' => $cost,
            'margin_display' => $this->money($margin),
            'tax_profile' => data_get($primaryStock, 'tax_profile.name', 'No tax profile'),
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

    private function reference(Collection $references, string $type): string
    {
        return $references->firstWhere('reference_type', $type)['reference_value'] ?? 'N/A';
    }

    private function money(float $amount): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($amount);
    }

    private function paymentAccountOptions(Request $request): array
    {
        $response = $this->apiCall($request, 'GET', 'payment-accounts', ['is_active' => true]);

        return collect($response['data'] ?? [])
            ->map(fn ($account) => [
                'id' => $account['id'],
                'label' => "{$account['account_name']} - ".str($account['account_type'])->headline()->toString(),
            ])
            ->all();
    }

    private function customerOptions(Request $request): array
    {
        $response = $this->apiCall($request, 'GET', 'contacts', ['is_active' => true]);

        return collect($response['data'] ?? [])
            ->filter(fn (array $contact): bool => (bool) ($contact['is_customer'] ?? false))
            ->map(fn ($contact) => [
                'id' => $contact['id'],
                'label' => "{$contact['name']} ({$contact['code']})",
            ])
            ->all();
    }

    private function apiCall(Request $request, string $method, string $endpoint, array $payload = []): array
    {
        $token = $this->sessionApiToken($request);
        $parameters = strtoupper($method) === 'GET' ? $payload : [];
        $requestPayload = strtoupper($method) === 'GET' ? [] : $payload;
        $apiRequest = Request::create("/api/{$endpoint}", $method, $parameters);

        $apiRequest->headers->set('Accept', 'application/json');
        $apiRequest->headers->set('Authorization', "Bearer {$token}");
        $apiRequest->setUserResolver(fn () => $request->user());

        if ($requestPayload !== []) {
            $apiRequest->request->replace($requestPayload);
        }

        $response = RouteFacade::dispatch($apiRequest);
        $body = json_decode($response->getContent(), true) ?: [];

        if ($response->getStatusCode() === 422) {
            throw ValidationException::withMessages($body['errors'] ?? ['pos' => $body['message'] ?? 'POS validation failed.']);
        }

        if ($response->getStatusCode() >= 400 || ! ($body['success'] ?? false)) {
            abort($response->getStatusCode(), $body['message'] ?? 'POS API request failed.');
        }

        return $body;
    }

    private function sessionApiToken(Request $request): string
    {
        $token = $request->session()->get('partflow_api_token');

        if ($token) {
            return $token;
        }

        $user = $request->user();

        abort_unless($user, 401);

        $token = $user->createToken('partflow-web-session')->plainTextToken;
        $request->session()->put('partflow_api_token', $token);
        $request->session()->put('partflow_api_token_id', strtok($token, '|') ?: null);

        return $token;
    }
}
