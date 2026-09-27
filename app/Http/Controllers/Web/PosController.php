<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\PosLookupRequest;
use App\Http\Requests\Web\StorePosSaleRequest;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Services\DiscountPolicyService;
use App\Services\InventoryDocumentService;
use App\Services\SiteAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\InputBag;
use Throwable;

class PosController extends Controller
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly SiteAccessService $siteAccessService,
        private readonly DiscountPolicyService $discountPolicy
    ) {}

    public function index(Request $request): View
    {
        $currentSite = $this->currentSite($request);
        $currentBranch = $currentSite?->name ?? 'All sites';
        $products = $this->products($request, $currentSite);
        $selectedProduct = $products[0] ?? $this->emptyProduct($currentBranch);

        return view('pos', [
            'title' => 'Point Of Sale',
            'currentBranch' => $currentBranch,
            'currentSiteId' => $currentSite?->id,
            'siteOptions' => $this->siteOptions($request->user()),
            'canChangeSiteDirectly' => $this->isAdmin($request->user()),
            'cashier' => auth()->user()?->name ?? 'Cashier',
            'saleNumber' => 'Draft sale',
            'quickSearches' => [],
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
            'posEndpoints' => [
                'products' => route('web.pos.products'),
                'suggestions' => route('web.pos.suggestions'),
                'vehicleModels' => route('web.pos.vehicle-models'),
                'productTypes' => route('web.pos.product-types'),
                'site' => route('web.pos.site'),
                'canChangeSiteDirectly' => $this->isAdmin($request->user()),
                'maximumDiscountPercentage' => $this->discountPolicy->maximumDiscountPercentage(),
            ],
        ]);
    }

    public function productsJson(PosLookupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $currentSite = $this->currentSite(
            $request,
            filled($validated['site_id'] ?? null) ? (int) $validated['site_id'] : null
        );

        return response()->json([
            'products' => $this->products($request, $currentSite, collect($validated)->except('site_id')->all()),
            'site_id' => $currentSite?->id,
            'site_name' => $currentSite?->name,
        ]);
    }

    public function suggestionsJson(PosLookupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $currentSite = $this->currentSite(
            $request,
            filled($validated['site_id'] ?? null) ? (int) $validated['site_id'] : null
        );

        return response()->json([
            'suggestions' => $this->apiCall($request, 'GET', 'pos/suggestions', [
                'search' => $validated['search'] ?? null,
                'site_id' => $currentSite?->id,
                'limit' => 5,
            ])['data'] ?? [],
        ]);
    }

    public function vehicleModelsJson(Request $request): JsonResponse
    {
        return response()->json([
            'vehicles' => $this->apiCall($request, 'GET', 'car-models', [
                'search' => $request->query('search'),
            ])['data'] ?? [],
        ]);
    }

    public function productTypesJson(Request $request): JsonResponse
    {
        return response()->json([
            'productTypes' => collect($this->apiCall($request, 'GET', 'product-types', [
                'search' => $request->query('search'),
                'is_active' => true,
            ])['data'] ?? [])
                ->map(fn (array $productType): array => [
                    'id' => $productType['id'] ?? null,
                    'name' => $productType['name'] ?? '',
                    'code' => $productType['code'] ?? '',
                    'label' => trim(($productType['name'] ?? '').' '.(($productType['code'] ?? '') ? "({$productType['code']})" : '')),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function updateSite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $site = Site::query()->active()->findOrFail($validated['site_id']);
        $user = $request->user();

        $this->siteAccessService->authorizeSite($user, $site->id, SiteAccessService::MAKE_SALES);

        if (! $this->isAdmin($user) && ! $this->validAdminPassword($validated['admin_password'] ?? '')) {
            throw ValidationException::withMessages([
                'admin_password' => 'An admin password is required to change the POS selling site.',
            ]);
        }

        $request->session()->put('pos_site_id', $site->id);

        return response()->json([
            'message' => "POS selling site changed to {$site->name}.",
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
            ],
        ]);
    }

    public function store(StorePosSaleRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $cart = json_decode($validated['cart_payload'], true);

        if (! is_array($cart)) {
            throw ValidationException::withMessages([
                'cart_payload' => 'The cart could not be read. Please rebuild the sale.',
            ]);
        }

        $cartData = Validator::make(
            ['items' => $cart],
            [
                'items' => ['required', 'array', 'min:1', 'max:100'],
                'items.*' => ['required', 'array'],
                'items.*.product_id' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('products', 'id')->where(fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at')),
                ],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            ],
            [
                'items.required' => 'Add at least one product before completing the sale.',
                'items.min' => 'Add at least one product before completing the sale.',
                'items.max' => 'A sale cannot contain more than 100 different products.',
                'items.*.product_id.required' => 'Every sale line must have a product.',
                'items.*.product_id.distinct' => 'The same product cannot appear twice in the cart.',
                'items.*.product_id.exists' => 'A product in the cart is no longer available. Please rebuild the sale.',
                'items.*.quantity.required' => 'Every sale line must have a quantity.',
                'items.*.quantity.integer' => 'Sale quantities must be whole numbers.',
                'items.*.quantity.min' => 'Sale quantities must be at least 1.',
                'items.*.quantity.max' => 'A sale quantity cannot exceed 10,000 units.',
            ]
        )->validate();

        $products = Product::query()
            ->whereIn('id', collect($cartData['items'])->pluck('product_id'))
            ->get(['id', 'default_selling_price', 'minimum_selling_price'])
            ->keyBy('id');

        $items = collect($cartData['items'])
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $products->get((int) $item['product_id'])->default_selling_price,
                'discount_amount' => 0,
            ])
            ->all();

        $documentDate = filled($validated['document_date'] ?? null) ? $validated['document_date'] : now();

        $payload = [
            'source_site_id' => $validated['source_site_id'],
            'contact_id' => $validated['contact_id'] ?? null,
            'document_date' => $documentDate,
            'status' => 'completed',
            'discount_amount' => (float) ($validated['discount_amount'] ?? 0),
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
                'payment_date' => $documentDate,
            ];
        }

        try {
            $sale = $this->inventoryDocumentService->createSale($payload, $request->user());
        } catch (ValidationException|AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The sale could not be completed. Please try again or contact an administrator.',
                ], 500);
            }

            return redirect()
                ->route('web.pos')
                ->with('error', 'The sale could not be completed. Please try again or contact an administrator.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sale created successfully',
                'sale' => [
                    'id' => $sale->id,
                    'document_number' => $sale->document_number,
                ],
            ], 201);
        }

        return redirect()
            ->route('web.pos')
            ->with('success', 'Sale created successfully');
    }

    private function products(Request $request, ?Site $currentSite, array $filters = []): array
    {
        $response = $this->apiCall($request, 'GET', 'pos/products', array_filter($filters, fn ($value): bool => $value !== null && $value !== ''));

        return collect($response['data'] ?? [])
            ->groupBy('product_id')
            ->map(fn (Collection $stocks): array => $this->productFromApiRows($stocks, $currentSite))
            ->filter(fn (array $product): bool => (int) data_get($product, 'current_branch_stock.available', 0) > 0)
            ->values()
            ->all();
    }

    private function productFromApiRows(Collection $stocks, ?Site $currentSite): array
    {
        $currentSiteId = $currentSite?->id;
        $primaryStock = $stocks->firstWhere('site_id', $currentSiteId) ?? $stocks->first();
        $branchStock = $stocks->map(function (array $stock) use ($currentSiteId): array {
            $available = (int) ($stock['available_quantity'] ?? 0);

            return [
                'site_id' => $stock['site_id'] ?? null,
                'branch' => $stock['site_name'] ?? 'Unassigned',
                'on_hand' => (int) ($stock['quantity_on_hand'] ?? 0),
                'reserved' => (int) ($stock['reserved_quantity'] ?? 0),
                'available' => $available,
                'status' => $available === 0 ? 'out' : ($available <= 2 ? 'low' : 'ok'),
                'current' => (int) ($stock['site_id'] ?? 0) === (int) $currentSiteId,
            ];
        })
            ->sortBy([
                ['current', 'desc'],
                ['branch', 'asc'],
            ])
            ->values()
            ->all();

        if ($currentSite && ! collect($branchStock)->contains(
            fn (array $branch): bool => (int) ($branch['site_id'] ?? 0) === $currentSite->id
        )) {
            array_unshift($branchStock, [
                'site_id' => $currentSite->id,
                'branch' => $currentSite->name,
                'on_hand' => 0,
                'reserved' => 0,
                'available' => 0,
                'status' => 'out',
                'current' => true,
            ]);
        }

        $totalAvailable = collect($branchStock)->sum('available');
        $bestBranch = collect($branchStock)->sortByDesc('available')->first();
        $currentBranchStock = collect($branchStock)->firstWhere('current', true) ?? $branchStock[0] ?? null;
        $otherAvailable = collect($branchStock)
            ->reject(fn (array $branch): bool => (bool) ($branch['current'] ?? false))
            ->sum('available');
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
        $minimumSellingPrice = (float) ($primaryStock['minimum_selling_price'] ?? $price);
        $minimumAuthorizedPrice = $this->discountPolicy->minimumAuthorizedPrice($price, $minimumSellingPrice);

        return [
            'id' => $primaryStock['product_id'] ?? null,
            'product_id' => $primaryStock['product_id'] ?? null,
            'product_code' => $primaryStock['product_code'] ?? 'N/A',
            'product_name' => $primaryStock['product_name'] ?? 'Unnamed product',
            'pos_description' => $primaryStock['pos_description'] ?? ($primaryStock['product_name'] ?? 'Unnamed product'),
            'vehicle' => $vehicle,
            'product_type' => data_get($primaryStock, 'product_type.name', 'Unassigned'),
            'brand' => data_get($primaryStock, 'brand.name', 'Unbranded'),
            'part_country_of_origin' => $primaryStock['part_country_of_origin'] ?: 'Not set',
            'barcode' => $this->reference($references, 'barcode'),
            'oem_number' => $this->reference($references, 'oem_number'),
            'selling_price' => $price,
            'selling_price_display' => $this->money($price),
            'minimum_selling_price' => $minimumSellingPrice,
            'minimum_authorized_price' => $minimumAuthorizedPrice,
            'minimum_authorized_price_display' => $this->money($minimumAuthorizedPrice),
            'maximum_discount_percentage' => $this->discountPolicy->maximumDiscountPercentageForPrices($price, $minimumSellingPrice),
            'unit_cost' => $cost,
            'margin_display' => $this->money($margin),
            'tax_profile' => data_get($primaryStock, 'tax_profile.name', 'No tax profile'),
            'compatible_cars' => $compatibleCars,
            'compatible_cars_count' => count($compatibleCars),
            'compatible_cars_tooltip' => $compatibleCars === [] ? 'No vehicle fitment linked' : implode("\n", $compatibleCars),
            'branch_stock' => $branchStock,
            'current_branch_stock' => $currentBranchStock,
            'current_branch_name' => $currentBranchStock['branch'] ?? 'Current branch',
            'other_available' => $otherAvailable,
            'branch_stock_summary' => $branchStockSummary,
            'branch_stock_tooltip' => collect($branchStock)
                ->map(fn (array $branch): string => "{$branch['branch']}: {$branch['available']}")
                ->implode("\n"),
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
            'product_type' => 'No product type',
            'brand' => 'Unbranded',
            'part_country_of_origin' => 'Not set',
            'barcode' => 'N/A',
            'oem_number' => 'N/A',
            'selling_price' => 0,
            'selling_price_display' => $this->money(0),
            'minimum_selling_price' => 0,
            'minimum_authorized_price' => 0,
            'minimum_authorized_price_display' => $this->money(0),
            'maximum_discount_percentage' => 0,
            'unit_cost' => 0,
            'margin_display' => $this->money(0),
            'tax_profile' => 'No tax profile',
            'compatible_cars' => [],
            'compatible_cars_count' => 0,
            'compatible_cars_tooltip' => 'No vehicle fitment linked',
            'branch_stock' => [[
                'site_id' => null,
                'branch' => $currentBranch,
                'on_hand' => 0,
                'reserved' => 0,
                'available' => 0,
                'status' => 'out',
                'current' => true,
            ]],
            'current_branch_stock' => [
                'site_id' => null,
                'branch' => $currentBranch,
                'on_hand' => 0,
                'reserved' => 0,
                'available' => 0,
                'status' => 'out',
                'current' => true,
            ],
            'current_branch_name' => $currentBranch,
            'other_available' => 0,
            'branch_stock_summary' => "{$currentBranch} 0",
            'branch_stock_tooltip' => "{$currentBranch}: 0",
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

    private function currentSite(Request $request, ?int $requestedSiteId = null): ?Site
    {
        $sessionSiteId = $request->session()->get('pos_site_id');
        $allowedSiteIds = $request->user()
            ? $this->siteAccessService->allowedSiteIds($request->user(), SiteAccessService::MAKE_SALES)
            : [];
        $site = null;
        $shouldPersistFallback = false;

        if ($requestedSiteId && $request->user()) {
            $this->siteAccessService->authorizeSite($request->user(), $requestedSiteId, SiteAccessService::MAKE_SALES);
            $site = Site::query()->active()->find($requestedSiteId);
        } elseif ($sessionSiteId) {
            $site = Site::query()->active()->whereIn('id', $allowedSiteIds)->find($sessionSiteId);
        }

        if (! $site && $request->user()) {
            $shouldPersistFallback = true;
            $site = $request->user()
                ->accessibleSites()
                ->wherePivot('is_active', true)
                ->wherePivot('can_make_sales', true)
                ->wherePivot('is_default', true)
                ->where('sites.is_active', true)
                ->orderBy('sites.name')
                ->first();
        }

        if (! $site && $this->isAdmin($request->user())) {
            $shouldPersistFallback = true;
            $site = Site::query()->active()->orderBy('name')->first();
        }

        if ($site && $shouldPersistFallback) {
            $request->session()->put('pos_site_id', $site->id);
        }

        return $site;
    }

    private function siteOptions(User $user): array
    {
        $siteIds = $this->siteAccessService->allowedSiteIds($user, SiteAccessService::MAKE_SALES);

        return Site::query()
            ->active()
            ->whereIn('id', $siteIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'label' => trim("{$site->name} {$site->code}"),
            ])
            ->all();
    }

    private function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing('role');
        $permissions = $user->role?->permissions ?? [];

        return in_array('*', $permissions, true)
            || str($user->role?->name ?? '')->lower()->contains('admin');
    }

    private function validAdminPassword(string $password): bool
    {
        if ($password === '') {
            return false;
        }

        return User::query()
            ->active()
            ->whereHas('role', function ($query): void {
                $query->whereJsonContains('permissions', '*')
                    ->orWhere('name', 'like', '%Admin%');
            })
            ->get()
            ->contains(fn (User $user): bool => Hash::check($password, $user->password));
    }

    private function apiCall(Request $request, string $method, string $endpoint, array $payload = []): array
    {
        $token = $this->sessionApiToken($request);
        $isGet = strtoupper($method) === 'GET';
        $apiRequest = Request::create(
            "/api/{$endpoint}",
            $method,
            $isGet ? $payload : [],
            [],
            [],
            [],
            $isGet ? null : json_encode($payload)
        );

        $apiRequest->headers->set('Accept', 'application/json');
        $apiRequest->headers->set('Authorization', "Bearer {$token}");
        $apiRequest->headers->set('Content-Type', 'application/json');
        $apiRequest->setUserResolver(fn () => $request->user());

        if (! $isGet) {
            $apiRequest->request->replace($payload);
            $apiRequest->setJson(new InputBag($payload));
            $apiRequest->merge($payload);
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
