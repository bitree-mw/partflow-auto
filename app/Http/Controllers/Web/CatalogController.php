<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\FuelType;
use App\Models\InventoryDocument;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\TaxProfile;
use App\Models\VehicleModel;
use App\Services\BrandService;
use App\Services\CarModelService;
use App\Services\FuelTypeService;
use App\Services\InventoryDocumentService;
use App\Services\ProductService;
use App\Services\ProductTypeService;
use App\Services\SiteAccessService;
use App\Services\SiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CarModelService $carModelService,
        private readonly ProductTypeService $productTypeService,
        private readonly ProductService $productService,
        private readonly FuelTypeService $fuelTypeService,
        private readonly BrandService $brandService,
        private readonly SiteService $siteService,
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function siteManagement(Request $request): View
    {
        $filters = $request->only(['search', 'type', 'is_active']);
        $siteIds = $this->siteAccessService->allowedSiteIds($request->user());
        $sites = Site::query()
            ->withCount(['siteStocks', 'sourceInventoryDocuments', 'destinationInventoryDocuments'])
            ->withSum('siteStocks as stock_on_hand', 'quantity_on_hand')
            ->search($filters['search'] ?? null)
            ->type($filters['type'] ?? null)
            ->whereIn('id', $siteIds)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'code' => $site->code,
                'type' => str($site->type)->headline()->toString(),
                'location' => $site->location ?: 'Not set',
                'stock_items' => $site->site_stocks_count ?? 0,
                'stock_on_hand' => (int) ($site->stock_on_hand ?? 0),
                'documents' => ($site->source_inventory_documents_count ?? 0) + ($site->destination_inventory_documents_count ?? 0),
                'is_active' => (bool) $site->is_active,
                'status' => $site->is_active ? 'Active' : 'Inactive',
            ]);

        $siteOptions = $this->siteOptions($this->siteService->list(['is_active' => true], $request->user()));
        $productOptions = $this->productOptions($this->productService->list(['is_active' => true]));

        return view('catalog.sites.index', [
            'title' => 'Site Management',
            'description' => 'Manage warehouses, branches, stock transfers, and stock takes.',
            'sites' => $sites,
            'filters' => $filters,
            'summary' => [
                ['label' => 'Active sites', 'value' => (string) Site::query()->active()->whereIn('id', $siteIds)->count(), 'detail' => 'Branches, shops, and warehouses'],
                ['label' => 'Warehouses', 'value' => (string) Site::query()->active()->whereIn('id', $siteIds)->where('type', 'warehouse')->count(), 'detail' => 'Stock storage locations'],
                ['label' => 'Transfer docs', 'value' => (string) $this->inventoryDocumentService->listByType('transfer', [], $request->user())->count(), 'detail' => 'Stock movement records'],
                ['label' => 'Stock takes', 'value' => (string) $this->inventoryDocumentService->listByType('stock_take', [], $request->user())->count(), 'detail' => 'Count and variance records'],
            ],
        ]);
    }

    public function createSite(): View
    {
        return view('catalog.sites.create', [
            'title' => 'Add Site',
            'description' => 'Create a branch, shop, or warehouse used for stock, transfers, and stock counts.',
            'siteTypes' => ['shop' => 'Shop', 'branch' => 'Branch', 'warehouse' => 'Warehouse'],
        ]);
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:sites,name'],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', 'unique:sites,code'],
            'type' => ['required', 'string', 'in:shop,branch,warehouse'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'code.regex' => 'The site code may only contain letters, numbers, and hyphens.',
        ]);

        $validated['code'] = filled($validated['code'] ?? null)
            ? strtoupper($validated['code'])
            : $this->uniqueSiteCode($validated['name']);

        $this->siteService->create($validated);

        return redirect()
            ->route('web.catalog.sites.index')
            ->with('success', 'Site added successfully.');
    }

    public function siteTransfers(Request $request): View
    {
        $transfers = $this->siteDocumentQuery('transfer', $request)
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (InventoryDocument $document): array => $this->siteDocumentRow($document));

        return view('catalog.sites.transfers.index', [
            'title' => 'Stock Transfers',
            'description' => 'View completed stock movement between sites.',
            'documents' => $transfers,
            'filters' => $request->only(['search', 'site_id']),
            'siteOptions' => $this->siteOptions($this->siteService->list(
                ['is_active' => true],
                $request->user(),
                SiteAccessService::TRANSFER_STOCK
            )),
        ]);
    }

    public function createSiteTransfer(Request $request): View
    {
        $siteIds = $this->siteAccessService->allowedSiteIds($request->user(), SiteAccessService::TRANSFER_STOCK);

        return view('catalog.sites.transfers.create', [
            'title' => 'New Stock Transfer',
            'description' => 'Move multiple parts from one site to another in one transaction.',
            'siteOptions' => $this->siteOptions($this->siteService->list(
                ['is_active' => true],
                $request->user(),
                SiteAccessService::TRANSFER_STOCK
            )),
            'productOptions' => $this->productOptions($this->productService->list(['is_active' => true])),
            'stockAvailability' => $this->stockAvailabilityMap($siteIds),
        ]);
    }

    public function storeSiteTransfer(Request $request): RedirectResponse
    {
        if ($request->integer('source_site_id') > 0 && $request->integer('destination_site_id') > 0) {
            $this->siteAccessService->authorizeSites($request->user(), [
                $request->integer('source_site_id'),
                $request->integer('destination_site_id'),
            ], SiteAccessService::TRANSFER_STOCK);
        }

        $validated = Validator::make($request->all(), [
            'source_site_id' => ['required', 'integer', 'exists:sites,id', 'different:destination_site_id'],
            'destination_site_id' => ['required', 'integer', 'exists:sites,id'],
            'document_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ])->after(function ($validator) use ($request): void {
            $sourceSiteId = (int) $request->input('source_site_id');
            $items = collect($request->input('items', []))
                ->filter(fn ($item): bool => filled($item['product_id'] ?? null) && filled($item['quantity'] ?? null));

            if ($sourceSiteId <= 0 || $items->isEmpty()) {
                return;
            }

            $requestedByProduct = $items
                ->groupBy(fn ($item): int => (int) $item['product_id'])
                ->map(fn ($rows): int => $rows->sum(fn ($row): int => (int) $row['quantity']));

            $stocks = SiteStock::query()
                ->with('product')
                ->where('site_id', $sourceSiteId)
                ->whereIn('product_id', $requestedByProduct->keys())
                ->get()
                ->keyBy('product_id');

            foreach ($items as $index => $item) {
                $productId = (int) $item['product_id'];
                $available = (int) ($stocks->get($productId)?->available_quantity ?? 0);
                $requested = (int) $requestedByProduct->get($productId, 0);

                if ($requested > $available) {
                    $productName = $stocks->get($productId)?->product?->product_name ?? 'This product';
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "{$productName} has {$available} available at the source site."
                    );
                }
            }
        })->validate();

        $document = $this->inventoryDocumentService->createTransfer([
            'source_site_id' => $validated['source_site_id'],
            'destination_site_id' => $validated['destination_site_id'],
            'document_date' => $validated['document_date'] ?? now(),
            'status' => 'completed',
            'notes' => $validated['notes'] ?? null,
            'items' => collect($validated['items'])->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])->values()->all(),
        ], $request->user());

        return redirect()
            ->route('web.catalog.sites.transfers.show', $document)
            ->with('success', 'Stock transferred successfully.');
    }

    public function showSiteTransfer(InventoryDocument $inventoryDocument): View
    {
        $document = $this->siteDocumentOrFail($inventoryDocument, 'transfer');

        return view('catalog.sites.transfers.show', [
            'title' => $document->document_number,
            'description' => 'Stock transfer details and moved line items.',
            'document' => $document,
        ]);
    }

    public function siteStockTakes(Request $request): View
    {
        $stockTakes = $this->siteDocumentQuery('stock_take', $request)
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (InventoryDocument $document): array => $this->siteDocumentRow($document));

        return view('catalog.sites.stock-takes.index', [
            'title' => 'Stock Takes',
            'description' => 'Review stock counts, system quantities, and variances.',
            'documents' => $stockTakes,
            'filters' => $request->only(['search', 'site_id']),
            'siteOptions' => $this->siteOptions($this->siteService->list(
                ['is_active' => true],
                $request->user(),
                SiteAccessService::ADJUST_STOCK
            )),
        ]);
    }

    public function createSiteStockTake(Request $request): View
    {
        $siteIds = $this->siteAccessService->allowedSiteIds($request->user(), SiteAccessService::ADJUST_STOCK);

        return view('catalog.sites.stock-takes.create', [
            'title' => 'New Stock Take',
            'description' => 'Count multiple parts at a site and let the system report any variances.',
            'siteOptions' => $this->siteOptions($this->siteService->list(
                ['is_active' => true],
                $request->user(),
                SiteAccessService::ADJUST_STOCK
            )),
            'productOptions' => $this->productOptions($this->productService->list(['is_active' => true])),
            'stockAvailability' => $this->stockAvailabilityMap($siteIds),
        ]);
    }

    public function storeSiteStockTake(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'document_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.counted_quantity' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $document = $this->inventoryDocumentService->createStockTake([
            'site_id' => $validated['site_id'],
            'document_date' => $validated['document_date'] ?? now(),
            'status' => 'approved',
            'notes' => $validated['notes'] ?? null,
            'items' => collect($validated['items'])->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'counted_quantity' => (int) $item['counted_quantity'],
                'notes' => $item['notes'] ?? null,
            ])->values()->all(),
        ], $request->user());

        return redirect()
            ->route('web.catalog.sites.stock-takes.show', $document)
            ->with('success', 'Stock take saved successfully.');
    }

    public function showSiteStockTake(InventoryDocument $inventoryDocument): View
    {
        $document = $this->siteDocumentOrFail($inventoryDocument, 'stock_take');

        return view('catalog.sites.stock-takes.show', [
            'title' => $document->document_number,
            'description' => 'Stock take variance report.',
            'document' => $document,
            'varianceCount' => $document->items->where('variance_quantity', '!=', 0)->count(),
        ]);
    }

    public function carModels(Request $request): View
    {
        $filters = $this->catalogueFilters($request);
        $carModelQuery = CarModel::query()
            ->with(['carMake', 'vehicleModel'])
            ->withCount(['products', 'compatibleProducts'])
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']));

        $this->applyCatalogueSort($carModelQuery, $filters, [
            'vehicle' => ['make', 'model'],
            'engine' => 'engine_size',
            'variant' => 'variant_name',
            'origin' => 'country_of_origin',
            'products' => 'products_count',
            'status' => 'is_active',
        ], [
            ['make', 'asc'],
            ['model', 'asc'],
            ['year', 'desc'],
        ]);

        $carModels = $carModelQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (CarModel $carModel): array => $this->carModelRow($carModel));

        return view('catalog.car-models.index', [
            'title' => 'Car Models',
            'description' => 'Create vehicle fitment records used for product codes and compatibility searches.',
            'carModels' => $carModels,
            'filters' => $filters,
        ]);
    }

    public function createCarModel(): View
    {
        return view('catalog.car-models.create', [
            'title' => 'Add Car Model',
            'description' => 'Define make, model, year, engine, variant, and country of origin.',
            'countries' => config('countries'),
            'carMakes' => $this->carMakeOptions(),
            'vehicleModels' => $this->vehicleModelOptions(),
        ]);
    }

    public function storeCarModel(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'car_make_id' => ['required', 'integer', 'exists:car_makes,id'],
            'vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'engine_size' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'variant_name' => ['required', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ])->after(function ($validator) use ($request): void {
            $vehicleModel = VehicleModel::query()
                ->whereKey($request->input('vehicle_model_id'))
                ->where('car_make_id', $request->input('car_make_id'))
                ->first();

            if (! $vehicleModel) {
                $validator->errors()->add(
                    'vehicle_model_id',
                    'Choose a model that belongs to the selected make.'
                );

                return;
            }

            $year = $vehicleModel->year ?: $request->input('year');
            $engineSize = $this->normalizeEngineSize($request->input('engine_size'));

            if (! $year) {
                $validator->errors()->add('year', 'Choose a model with a year or provide a year.');

                return;
            }

            $exists = CarModel::query()
                ->where('car_make_id', $request->input('car_make_id'))
                ->where('vehicle_model_id', $request->input('vehicle_model_id'))
                ->where('year', $year)
                ->where('engine_size', $engineSize)
                ->where('variant_name', $request->input('variant_name'))
                ->where('country_of_origin', $request->input('country_of_origin'))
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'vehicle_model_id',
                    'This car make, model, year, engine, variant, and country of origin already exists.'
                );
            }
        })->validate();

        $vehicleModel = VehicleModel::query()->find($validated['vehicle_model_id']);
        $validated['year'] = $vehicleModel?->year ?: ($validated['year'] ?? null);
        $validated['engine_size'] = $this->normalizeEngineSize($validated['engine_size'] ?? null);

        $this->carModelService->create($validated);

        return redirect()
            ->route('web.catalog.car-models.index')
            ->with('success', 'Car model saved successfully.');
    }

    public function editCarModel(CarModel $carModel): View
    {
        return view('catalog.car-models.edit', [
            'title' => 'Edit Car Model',
            'description' => 'Update the vehicle fitment record used for product matching.',
            'carModel' => $carModel,
            'countries' => config('countries'),
            'carMakes' => $this->carMakeOptions(),
            'vehicleModels' => $this->vehicleModelOptions(),
        ]);
    }

    public function updateCarModel(Request $request, CarModel $carModel): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'car_make_id' => ['required', 'integer', 'exists:car_makes,id'],
            'vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'engine_size' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'variant_name' => ['required', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ])->after(function ($validator) use ($request, $carModel): void {
            $vehicleModel = VehicleModel::query()
                ->whereKey($request->input('vehicle_model_id'))
                ->where('car_make_id', $request->input('car_make_id'))
                ->first();

            if (! $vehicleModel) {
                $validator->errors()->add('vehicle_model_id', 'Choose a model that belongs to the selected make.');

                return;
            }

            $year = $vehicleModel->year ?: $request->input('year');
            $engineSize = $this->normalizeEngineSize($request->input('engine_size'));

            if (! $year) {
                $validator->errors()->add('year', 'Choose a model with a year or provide a year.');

                return;
            }

            $exists = CarModel::query()
                ->whereKeyNot($carModel->id)
                ->where('car_make_id', $request->input('car_make_id'))
                ->where('vehicle_model_id', $request->input('vehicle_model_id'))
                ->where('year', $year)
                ->where('engine_size', $engineSize)
                ->where('variant_name', $request->input('variant_name'))
                ->where('country_of_origin', $request->input('country_of_origin'))
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'vehicle_model_id',
                    'This car make, model, year, engine, variant, and country of origin already exists.'
                );
            }
        })->validate();

        $vehicleModel = VehicleModel::query()->find($validated['vehicle_model_id']);
        $validated['year'] = $vehicleModel?->year ?: ($validated['year'] ?? null);
        $validated['engine_size'] = $this->normalizeEngineSize($validated['engine_size'] ?? null);
        $validated['is_active'] = $request->boolean('is_active');

        if (! $validated['is_active'] && ($carModel->products()->exists() || $carModel->compatibleProducts()->exists())) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This car model is linked to products and cannot be made inactive.');
        }

        $this->carModelService->update($carModel, $validated);

        return redirect()
            ->route('web.catalog.car-models.index')
            ->with('success', 'Car model updated successfully.');
    }

    public function destroyCarModel(CarModel $carModel): RedirectResponse
    {
        if ($carModel->products()->exists() || $carModel->compatibleProducts()->exists()) {
            return redirect()
                ->route('web.catalog.car-models.index')
                ->with('error', 'This car model is linked to products and cannot be made inactive.');
        }

        $this->carModelService->update($carModel, ['is_active' => false]);

        return redirect()
            ->route('web.catalog.car-models.index')
            ->with('success', 'Car model marked inactive.');
    }

    public function productTypes(Request $request): View
    {
        $filters = $this->catalogueFilters($request);
        $productTypeQuery = ProductType::query()
            ->withCount('products')
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']));

        $this->applyCatalogueSort($productTypeQuery, $filters, [
            'name' => 'name',
            'code' => 'code',
            'products' => 'products_count',
            'status' => 'is_active',
        ], [
            ['name', 'asc'],
        ]);

        $productTypes = $productTypeQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (ProductType $productType): array => $this->productTypeRow($productType));

        return view('catalog.product-types.index', [
            'title' => 'Product types',
            'description' => 'Maintain reusable product categories for parts, fluids, and service consumables.',
            'productTypes' => $productTypes,
            'filters' => $filters,
        ]);
    }

    public function createProductType(): View
    {
        return view('catalog.product-types.create', [
            'title' => 'Add product type',
            'description' => 'Create a reusable product type before adding catalogue products.',
        ]);
    }

    public function storeProductType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', 'unique:product_types,code'],
            'description' => ['nullable', 'string'],
        ], [
            'code.regex' => 'The product type code may only contain letters and numbers.',
        ]);

        $this->productTypeService->create($validated);

        return redirect()
            ->route('web.catalog.product-types.index')
            ->with('success', 'Product type saved successfully.');
    }

    public function editProductType(ProductType $product_type): View
    {
        return view('catalog.product-types.edit', [
            'title' => 'Edit product type',
            'description' => 'Update the product type name, code, and active status.',
            'productType' => $product_type,
        ]);
    }

    public function updateProductType(Request $request, ProductType $product_type): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('product_types', 'code')->ignore($product_type->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'The product type code may only contain letters and numbers.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if (! $validated['is_active'] && $product_type->products()->exists()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This product type is linked to products and cannot be made inactive.');
        }

        $this->productTypeService->update($product_type, $validated);

        return redirect()
            ->route('web.catalog.product-types.index')
            ->with('success', 'Product type updated successfully.');
    }

    public function destroyProductType(ProductType $product_type): RedirectResponse
    {
        if ($product_type->products()->exists()) {
            return redirect()
                ->route('web.catalog.product-types.index')
                ->with('error', 'This product type is linked to products and cannot be made inactive.');
        }

        $this->productTypeService->update($product_type, ['is_active' => false]);

        return redirect()
            ->route('web.catalog.product-types.index')
            ->with('success', 'Product type marked inactive.');
    }

    public function brands(Request $request): View
    {
        $filters = $this->catalogueFilters($request);
        $brandQuery = Brand::query()
            ->withCount('products')
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']));

        $this->applyCatalogueSort($brandQuery, $filters, [
            'name' => 'name',
            'code' => 'code',
            'country' => 'country',
            'products' => 'products_count',
            'status' => 'is_active',
        ], [
            ['name', 'asc'],
        ]);

        $brands = $brandQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Brand $brand): array => $this->brandRow($brand));

        return view('catalog.brands.index', [
            'title' => 'Brands',
            'description' => 'Maintain manufacturers and suppliers used when adding catalogue parts.',
            'brands' => $brands,
            'filters' => $filters,
        ]);
    }

    public function createBrand(): View
    {
        return view('catalog.brands.create', [
            'title' => 'Add Brand',
            'description' => 'Create a reusable brand before assigning it to parts.',
            'countries' => config('countries'),
        ]);
    }

    public function storeBrand(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
            'code' => ['nullable', 'string', 'max:4', 'regex:/^[A-Za-z0-9]+$/', 'unique:brands,code'],
            'country' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ], [
            'code.regex' => 'The brand code may only contain letters and numbers.',
        ]);

        $this->brandService->create($validated);

        return redirect()
            ->route('web.catalog.brands.index')
            ->with('success', 'Brand saved successfully.');
    }

    public function editBrand(Brand $brand): View
    {
        return view('catalog.brands.edit', [
            'title' => 'Edit Brand',
            'description' => 'Update brand details and active status.',
            'brand' => $brand,
            'countries' => config('countries'),
        ]);
    }

    public function updateBrand(Request $request, Brand $brand): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brand->id)],
            'code' => ['nullable', 'string', 'max:4', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('brands', 'code')->ignore($brand->id)],
            'country' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'The brand code may only contain letters and numbers.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if (! $validated['is_active'] && $brand->products()->exists()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This brand is linked to products and cannot be made inactive.');
        }

        $this->brandService->update($brand, $validated);

        return redirect()
            ->route('web.catalog.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroyBrand(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return redirect()
                ->route('web.catalog.brands.index')
                ->with('error', 'This brand is linked to products and cannot be made inactive.');
        }

        $this->brandService->update($brand, ['is_active' => false]);

        return redirect()
            ->route('web.catalog.brands.index')
            ->with('success', 'Brand marked inactive.');
    }

    public function fuelTypes(Request $request): View
    {
        $filters = $this->catalogueFilters($request);
        $fuelTypeQuery = FuelType::query()
            ->withCount('products')
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']));

        $this->applyCatalogueSort($fuelTypeQuery, $filters, [
            'name' => 'name',
            'code' => 'code',
            'products' => 'products_count',
            'status' => 'is_active',
        ], [
            ['name', 'asc'],
        ]);

        $fuelTypes = $fuelTypeQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (FuelType $fuelType): array => $this->fuelTypeRow($fuelType));

        return view('catalog.fuel-types.index', [
            'title' => 'Fuel Types',
            'description' => 'Maintain fuel categories used to generate product codes and narrow compatibility.',
            'fuelTypes' => $fuelTypes,
            'filters' => $filters,
        ]);
    }

    public function createFuelType(): View
    {
        return view('catalog.fuel-types.create', [
            'title' => 'Add Fuel Type',
            'description' => 'Create a reusable fuel category before adding parts.',
        ]);
    }

    public function storeFuelType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:fuel_types,name'],
            'code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', 'unique:fuel_types,code'],
            'description' => ['nullable', 'string'],
        ], [
            'code.regex' => 'The fuel type code may only contain letters and numbers.',
        ]);

        $this->fuelTypeService->create($validated);

        return redirect()
            ->route('web.catalog.fuel-types.index')
            ->with('success', 'Fuel type saved successfully.');
    }

    public function editFuelType(FuelType $fuelType): View
    {
        return view('catalog.fuel-types.edit', [
            'title' => 'Edit Fuel Type',
            'description' => 'Update fuel type details and active status.',
            'fuelType' => $fuelType,
        ]);
    }

    public function updateFuelType(Request $request, FuelType $fuelType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('fuel_types', 'name')->ignore($fuelType->id)],
            'code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('fuel_types', 'code')->ignore($fuelType->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'The fuel type code may only contain letters and numbers.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $this->fuelTypeService->update($fuelType, $validated);

        return redirect()
            ->route('web.catalog.fuel-types.index')
            ->with('success', 'Fuel type updated successfully.');
    }

    public function destroyFuelType(FuelType $fuelType): RedirectResponse
    {
        $this->fuelTypeService->update($fuelType, ['is_active' => false]);

        return redirect()
            ->route('web.catalog.fuel-types.index')
            ->with('success', 'Fuel type marked inactive.');
    }

    public function products(Request $request): View
    {
        $filters = $this->catalogueFilters($request, ['product_type_id', 'brand_id']);
        $productQuery = Product::query()
            ->select('products.*')
            ->selectSub(function ($query) {
                $query->from('site_stocks')
                    ->selectRaw('COALESCE(SUM(quantity_on_hand - reserved_quantity), 0)')
                    ->whereColumn('site_stocks.product_id', 'products.id');
            }, 'stock_total')
            ->withCount('compatibilities')
            ->with(['carModel', 'productType', 'fuelType', 'brand', 'taxProfile', 'siteStocks.site'])
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']))
            ->when($filters['product_type_id'] ?? null, fn ($query, $productTypeId) => $query->where('product_type_id', $productTypeId))
            ->when($filters['brand_id'] ?? null, fn ($query, $brandId) => $query->where('brand_id', $brandId));

        $this->applyCatalogueSort($productQuery, $filters, [
            'name' => 'product_name',
            'type' => 'product_type_id',
            'brand' => 'brand_id',
            'compatibility' => 'compatibilities_count',
            'price' => 'default_selling_price',
            'stock' => 'stock_total',
            'status' => 'is_active',
        ], [
            ['product_name', 'asc'],
        ]);

        $products = $productQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Product $product): array => $this->productRow($product));

        return view('catalog.products.index', [
            'title' => 'Parts Catalogue',
            'description' => 'Manage sellable parts, generated product codes, references, pricing, and compatibility.',
            'products' => $products,
            'filters' => $filters,
            'selectedProductTypes' => $this->productTypeOptionsByIds(
                $this->normalizeProductTypeIds($filters['product_type_id'] ?? null)
            ),
            'brandOptions' => $this->brandOptions($this->brandService->list(['is_active' => true])),
            'catalogueSummary' => [
                ['label' => 'Products available', 'value' => number_format(Product::query()->count()), 'detail' => 'Sellable catalogue items'],
                ['label' => 'Brands', 'value' => number_format(Brand::query()->count()), 'detail' => 'Product manufacturers'],
                ['label' => 'Product types', 'value' => number_format(ProductType::query()->count()), 'detail' => 'Reusable product categories'],
                ['label' => 'Fuel types', 'value' => number_format(FuelType::query()->count()), 'detail' => 'Vehicle power trim'],
                ['label' => 'Car models', 'value' => number_format(CarModel::query()->count()), 'detail' => 'Fitment and variant records'],
            ],
        ]);
    }

    public function createProduct(Request $request): View
    {
        $selectedCarModelIds = $this->normalizeCarModelIds($request->old('compatible_car_model_ids', []));
        $selectedProductTypeIds = $this->normalizeProductTypeIds($request->old('product_type_id'));

        return view('catalog.products.create', [
            'title' => 'Add Product',
            'description' => 'Build a product using vehicle fitment, product type, fuel, brand, tax, references, and compatibility.',
            'selectedCarModels' => $this->carModelOptionsByIds($selectedCarModelIds),
            'selectedProductTypes' => $this->productTypeOptionsByIds($selectedProductTypeIds),
            'countries' => config('countries'),
            'fuelTypes' => $this->fuelTypeOptions($this->fuelTypeService->list(['is_active' => true])),
            'brands' => $this->brandOptions($this->brandService->list(['is_active' => true])),
            'taxProfiles' => $this->taxProfileOptions(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        if (! $request->has('compatible_car_model_ids') && $request->filled('car_model_id')) {
            $request->merge([
                'compatible_car_model_ids' => [(int) $request->input('car_model_id')],
            ]);
        }

        $this->normalizeProductCompatibilityInput($request);

        $validated = $request->validate([
            'product_code' => ['nullable', 'string', 'max:100', 'unique:products,product_code'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'car_model_id' => ['nullable', 'integer', 'exists:car_models,id'],
            'product_type_id' => ['required', 'integer', 'exists:product_types,id'],
            'fuel_type_id' => ['nullable', 'integer', 'exists:fuel_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'part_country_of_origin' => ['nullable', 'string', 'max:100'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0'],
            'default_low_stock_level' => ['nullable', 'integer', 'min:0'],
            'pack_size' => ['nullable', 'numeric', 'min:0.01'],
            'compatible_car_model_ids' => ['nullable', 'array'],
            'compatible_car_model_ids.*' => ['required', 'integer', 'distinct', 'exists:car_models,id'],
            'compatibility_notes' => ['nullable', 'string'],
        ]);

        $payload = $this->productPayload($validated);
        $this->productService->create($payload);

        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', 'Product saved successfully.');
    }

    public function carModelOptionsSearch(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $selectedIds = $this->normalizeCarModelIds($request->query('ids', []));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(25, (int) $request->query('per_page', 50)));
        $query = CarModel::query();
        $hasMore = false;

        if ($selectedIds->isNotEmpty()) {
            $query->whereIn('id', $selectedIds);
        } else {
            $query->active();

            if ($search !== '') {
                $tokens = str($search)->squish()->explode(' ')->filter()->values();

                $query->where(function ($query) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $query->where(function ($query) use ($token): void {
                            $query->where('make', 'like', "%{$token}%")
                                ->orWhere('model', 'like', "%{$token}%")
                                ->orWhere('make_code', 'like', "%{$token}%")
                                ->orWhere('model_code', 'like', "%{$token}%")
                                ->orWhere('country_of_origin', 'like', "%{$token}%")
                                ->orWhere('engine_size', 'like', "%{$token}%")
                                ->orWhere('variant_name', 'like', "%{$token}%")
                                ->orWhere('year', 'like', "%{$token}%");
                        });
                    }
                });
            }
        }

        $results = $query
            ->orderBy('make')
            ->orderBy('model')
            ->orderByDesc('year')
            ->orderBy('variant_name')
            ->orderBy('country_of_origin')
            ->orderBy('id')
            ->when(
                $selectedIds->isEmpty(),
                fn ($query) => $query->offset(($page - 1) * $perPage)->limit($perPage + 1)
            )
            ->get();

        if ($selectedIds->isEmpty()) {
            $hasMore = $results->count() > $perPage;
            $results = $results->take($perPage);
        }

        $options = $results
            ->map(fn (CarModel $carModel): array => [
                'id' => $carModel->id,
                'label' => $this->carModelLabel($carModel),
            ])
            ->values();

        return response()->json([
            'data' => $options,
            'meta' => [
                'has_more' => $hasMore,
                'page' => $page,
                'next_page' => $hasMore ? $page + 1 : null,
            ],
        ]);
    }

    public function productTypeOptionsSearch(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $selectedIds = $this->normalizeProductTypeIds($request->query('ids', []));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(25, (int) $request->query('per_page', 50)));
        $query = ProductType::query();
        $hasMore = false;

        if ($selectedIds->isNotEmpty()) {
            $query->whereIn('id', $selectedIds);
        } else {
            $query->active();

            if ($search !== '') {
                $query->search($search);
            }
        }

        $results = $query
            ->orderBy('name')
            ->orderBy('code')
            ->orderBy('id')
            ->when(
                $selectedIds->isEmpty(),
                fn ($query) => $query->offset(($page - 1) * $perPage)->limit($perPage + 1)
            )
            ->get();

        if ($selectedIds->isEmpty()) {
            $hasMore = $results->count() > $perPage;
            $results = $results->take($perPage);
        }

        return response()->json([
            'data' => $this->productTypeOptions($results),
            'meta' => [
                'has_more' => $hasMore,
                'page' => $page,
                'next_page' => $hasMore ? $page + 1 : null,
            ],
        ]);
    }

    public function editProduct(Request $request, Product $product): View
    {
        $product->load(['compatibilities', 'carModel', 'productType', 'fuelType', 'brand', 'taxProfile']);
        $existingCompatibilityIds = collect([$product->car_model_id])
            ->merge($product->compatibilities->pluck('car_model_id'))
            ->filter()
            ->values()
            ->all();
        $selectedCarModelIds = $this->normalizeCarModelIds(
            $request->old('compatible_car_model_ids', $existingCompatibilityIds)
        );
        $selectedProductTypeIds = $this->normalizeProductTypeIds(
            $request->old('product_type_id', $product->product_type_id)
        );

        return view('catalog.products.edit', [
            'title' => 'Edit Product',
            'description' => 'Update catalogue product details, pricing, and compatibility.',
            'product' => $product,
            'selectedCarModels' => $this->carModelOptionsByIds($selectedCarModelIds),
            'selectedProductTypes' => $this->productTypeOptionsByIds($selectedProductTypeIds),
            'countries' => config('countries'),
            'fuelTypes' => $this->fuelTypeOptions($this->fuelTypeService->list()),
            'brands' => $this->brandOptions($this->brandService->list()),
            'taxProfiles' => $this->taxProfileOptions(),
        ]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $this->normalizeProductCompatibilityInput($request);

        $validated = $request->validate([
            'product_code' => ['nullable', 'string', 'max:100', Rule::unique('products', 'product_code')->ignore($product->id)],
            'product_name' => ['nullable', 'string', 'max:255'],
            'car_model_id' => ['nullable', 'integer', 'exists:car_models,id'],
            'product_type_id' => ['required', 'integer', 'exists:product_types,id'],
            'fuel_type_id' => ['nullable', 'integer', 'exists:fuel_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'part_country_of_origin' => ['nullable', 'string', 'max:100'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0'],
            'default_low_stock_level' => ['nullable', 'integer', 'min:0'],
            'pack_size' => ['nullable', 'numeric', 'min:0.01'],
            'compatible_car_model_ids' => ['nullable', 'array'],
            'compatible_car_model_ids.*' => ['required', 'integer', 'distinct', 'exists:car_models,id'],
            'compatibility_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = $this->productPayload($validated);
        $payload['is_active'] = $request->boolean('is_active');

        if (! $payload['is_active'] && $this->productHasStock($product)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This product has stock and cannot be made inactive.');
        }

        $this->productService->update($product, $payload);

        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        if ($this->productHasStock($product)) {
            return redirect()
                ->route('web.catalog.products.index')
                ->with('error', 'This product has stock and cannot be made inactive.');
        }

        $this->productService->update($product, ['is_active' => false]);

        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', 'Product marked inactive.');
    }

    private function productPayload(array $validated): array
    {
        $selectedCarModelIds = collect($validated['compatible_car_model_ids'] ?? [])
            ->filter()
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();
        $primaryCarModelId = filled($validated['car_model_id'] ?? null)
            ? (int) $validated['car_model_id']
            : $selectedCarModelIds->first();

        $validated['car_model_id'] = $primaryCarModelId;
        unset($validated['pos_description']);

        $compatibilities = collect($validated['compatible_car_model_ids'] ?? [])
            ->filter()
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => $id === $primaryCarModelId)
            ->map(fn (int $id): array => [
                'car_model_id' => $id,
                'notes' => $validated['compatibility_notes'] ?? null,
            ])
            ->values()
            ->all();

        return collect($validated)
            ->except([
                'compatible_car_model_ids',
                'compatibility_notes',
            ])
            ->merge([
                'references' => [],
                'compatibilities' => $compatibilities,
            ])
            ->all();
    }

    private function normalizeProductCompatibilityInput(Request $request): void
    {
        $compatibleCarModelIds = collect($request->input('compatible_car_model_ids', []))
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $request->merge([
            'compatible_car_model_ids' => $compatibleCarModelIds,
        ]);
    }

    private function catalogueFilters(Request $request, array $extraKeys = []): array
    {
        return $request->only(array_merge(['search', 'is_active', 'sort', 'direction'], $extraKeys));
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 10);

        return in_array($perPage, [10, 25, 50], true) ? $perPage : 10;
    }

    private function applyCatalogueSort($query, array $filters, array $sortableColumns, array $defaultOrder): void
    {
        $sort = $filters['sort'] ?? 'status';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! array_key_exists($sort, $sortableColumns)) {
            $sort = 'status';
            $direction = 'desc';
        }

        $columns = (array) $sortableColumns[$sort];

        if ($sort !== 'status') {
            $query->orderByDesc('is_active');
        }

        foreach ($columns as $column) {
            $query->orderBy($column, $direction);
        }

        foreach ($defaultOrder as [$column, $order]) {
            if (! in_array($column, $columns, true)) {
                $query->orderBy($column, $order);
            }
        }
    }

    private function siteDocumentQuery(string $documentType, Request $request)
    {
        $filters = $request->only(['search', 'site_id']);
        $filters = $this->siteAccessService->scopeFilters($request->user(), $filters);

        return InventoryDocument::query()
            ->with(['sourceSite', 'destinationSite', 'items.product'])
            ->withCount('items')
            ->type($documentType)
            ->forSite(filled($filters['site_id'] ?? null) ? (int) $filters['site_id'] : null)
            ->forSites($filters['site_ids'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('document_number', 'like', "%{$search}%")
                        ->orWhereHas('items.product', fn ($query) => $query->where('product_name', 'like', "%{$search}%"));
                });
            })
            ->latest('document_date')
            ->latest('id');
    }

    private function siteDocumentRow(InventoryDocument $document): array
    {
        return [
            'id' => $document->id,
            'number' => $document->document_number,
            'date' => $document->document_date?->format('M j, Y') ?? 'Not dated',
            'source' => $document->sourceSite?->name ?? 'Not set',
            'destination' => $document->destinationSite?->name ?? 'Not set',
            'items' => $document->items_count ?? $document->items->count(),
            'units' => (int) $document->items->sum('quantity'),
            'variance_count' => $document->items->where('variance_quantity', '!=', 0)->count(),
            'status' => str($document->status)->headline()->toString(),
        ];
    }

    private function siteDocumentOrFail(InventoryDocument $document, string $type): InventoryDocument
    {
        abort_unless($document->document_type === $type, 404);

        return $this->inventoryDocumentService->show($document, auth()->user());
    }

    private function stockAvailabilityMap(array $siteIds): array
    {
        return SiteStock::query()
            ->whereIn('site_id', $siteIds)
            ->get()
            ->groupBy('site_id')
            ->map(fn (Collection $stocks): array => $stocks
                ->mapWithKeys(fn (SiteStock $stock): array => [
                    $stock->product_id => [
                        'on_hand' => $stock->quantity_on_hand,
                        'available' => $stock->available_quantity,
                    ],
                ])
                ->all())
            ->all();
    }

    private function uniqueSiteCode(string $name): string
    {
        $base = str($name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->substr(0, 6)
            ->toString() ?: 'SITE';
        $code = $base;
        $counter = 1;

        while (Site::query()->where('code', $code)->exists()) {
            $code = $base.str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }

    private function carModelRows(Collection $carModels): array
    {
        return $carModels
            ->map(fn (CarModel $carModel): array => $this->carModelRow($carModel))
            ->all();
    }

    private function carModelRow(CarModel $carModel): array
    {
        return [
            'id' => $carModel->id,
            'make' => $carModel->make,
            'model' => $carModel->model,
            'year' => $carModel->year,
            'engine' => $carModel->engine_size ?: 'Any',
            'variant' => $carModel->variant_name ?: 'Standard',
            'origin' => $carModel->country_of_origin ?: 'Not set',
            'products' => $carModel->products_count ?? 0,
            'linked_products' => ($carModel->products_count ?? 0) + ($carModel->compatible_products_count ?? 0),
            'is_active' => (bool) $carModel->is_active,
            'status' => $carModel->is_active ? 'Active' : 'Inactive',
        ];
    }

    private function carMakeOptions(): array
    {
        return CarMake::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (CarMake $make): array => [
                'id' => $make->id,
                'label' => $make->name,
            ])
            ->all();
    }

    private function vehicleModelOptions(): array
    {
        return VehicleModel::query()
            ->active()
            ->with('carMake')
            ->orderBy('name')
            ->get()
            ->map(fn (VehicleModel $model): array => [
                'id' => $model->id,
                'car_make_id' => $model->car_make_id,
                'label' => $model->year ? "{$model->name} ({$model->year})" : $model->name,
                'year' => $model->year,
                'body_style' => $model->body_style,
            ])
            ->all();
    }

    private function productTypeRows(Collection $productTypes): array
    {
        return $productTypes
            ->map(fn (ProductType $productType): array => $this->productTypeRow($productType))
            ->all();
    }

    private function productTypeRow(ProductType $productType): array
    {
        return [
            'id' => $productType->id,
            'name' => $productType->name,
            'code' => $productType->code,
            'products' => $productType->products_count ?? 0,
            'is_active' => (bool) $productType->is_active,
            'status' => $productType->is_active ? 'Active' : 'Inactive',
        ];
    }

    private function fuelTypeRows(Collection $fuelTypes): array
    {
        return $fuelTypes
            ->map(fn (FuelType $fuelType): array => $this->fuelTypeRow($fuelType))
            ->all();
    }

    private function fuelTypeRow(FuelType $fuelType): array
    {
        return [
            'id' => $fuelType->id,
            'name' => $fuelType->name,
            'code' => $fuelType->code ?: 'N/A',
            'products' => $fuelType->products_count ?? 0,
            'is_active' => (bool) $fuelType->is_active,
            'status' => $fuelType->is_active ? 'Active' : 'Inactive',
        ];
    }

    private function brandRows(Collection $brands): array
    {
        return $brands
            ->map(fn (Brand $brand): array => $this->brandRow($brand))
            ->all();
    }

    private function brandRow(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'code' => $brand->code ?: 'N/A',
            'country' => $brand->country ?: 'Not set',
            'products' => $brand->products_count ?? 0,
            'is_active' => (bool) $brand->is_active,
            'status' => $brand->is_active ? 'Active' : 'Inactive',
        ];
    }

    private function productRows(Collection $products): array
    {
        return $products
            ->map(fn (Product $product): array => $this->productRow($product))
            ->all();
    }

    private function productRow(Product $product): array
    {
        $stockRows = $product->siteStocks;
        $stockStatus = $this->productStockStatus($product, $stockRows);
        $branchStock = $product->siteStocks
            ->sortBy('site.name')
            ->map(fn ($stock): array => [
                'site' => $stock->site?->name ?? 'Unassigned',
                'qty' => $stock->available_quantity,
                'minimum' => (int) ($stock->low_stock_level ?? $product->default_low_stock_level ?? 0),
            ])
            ->values()
            ->all();
        $compatibilityCount = (int) ($product->compatibilities_count ?? $product->compatibilities()->count());
        $minimumStock = $stockRows->isEmpty()
            ? (int) ($product->default_low_stock_level ?? 0)
            : collect($branchStock)->sum('minimum');

        return [
            'id' => $product->id,
            'name' => $product->product_name,
            'code' => $product->product_code,
            'type' => $product->productType?->name ?? 'Unassigned',
            'brand' => $product->brand?->name ?? 'Unbranded',
            'compatible_count' => $compatibilityCount,
            'compatible_label' => $compatibilityCount.' other '.($compatibilityCount === 1 ? 'car' : 'cars'),
            'price' => $this->money((float) $product->default_selling_price),
            'stock' => collect($branchStock)->sum('qty'),
            'minimum_stock' => $minimumStock,
            'branch_stock' => $branchStock,
            'has_stock' => $stockRows->contains(fn ($stock): bool => $stock->quantity_on_hand > 0 || $stock->reserved_quantity > 0),
            'is_active' => (bool) $product->is_active,
            'status' => $stockStatus['label'],
            'status_tone' => $stockStatus['tone'],
        ];
    }

    private function productStockStatus(Product $product, Collection $stockRows): array
    {
        if (! $product->is_active) {
            return ['label' => 'Inactive', 'tone' => 'inactive'];
        }

        if ($stockRows->isEmpty()) {
            return ['label' => 'Critical', 'tone' => 'danger'];
        }

        $branchStates = $stockRows->map(function (SiteStock $stock) use ($product): string {
            $available = (int) $stock->available_quantity;
            $lowStockLevel = (int) ($stock->low_stock_level ?? $product->default_low_stock_level ?? 0);

            if ($available <= 0) {
                return 'critical';
            }

            if ($lowStockLevel <= 0 || $available > $lowStockLevel) {
                return 'in_stock';
            }

            return $available <= max(1, (int) floor($lowStockLevel / 2))
                ? 'critical'
                : 'low_stock';
        });

        if ($branchStates->contains('critical')) {
            return ['label' => 'Critical', 'tone' => 'danger'];
        }

        if ($branchStates->contains('low_stock')) {
            return ['label' => 'Low stock', 'tone' => 'warning'];
        }

        return ['label' => 'In stock', 'tone' => 'success'];
    }

    private function productHasStock(Product $product): bool
    {
        return $product->siteStocks()
            ->where(function ($query): void {
                $query->where('quantity_on_hand', '>', 0)
                    ->orWhere('reserved_quantity', '>', 0);
            })
            ->exists();
    }

    private function carModelOptions(Collection $carModels): array
    {
        return $carModels
            ->map(fn (CarModel $carModel): array => [
                'id' => $carModel->id,
                'label' => $this->carModelLabel($carModel),
                'make' => $carModel->make,
                'model' => $carModel->model,
                'year' => $carModel->year,
                'engine' => $carModel->engine_size ?: 'Any',
                'variant' => $carModel->variant_name ?: 'Standard',
                'origin' => $carModel->country_of_origin ?: 'Not set',
            ])
            ->all();
    }

    private function carModelOptionsByIds(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $options = $this->carModelOptions(
            CarModel::query()
                ->whereIn('id', $ids)
                ->get()
        );
        $optionsById = collect($options)->keyBy('id');

        return $ids
            ->map(fn (int $id): ?array => $optionsById->get($id))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeCarModelIds(mixed $ids): Collection
    {
        return collect(is_array($ids) ? $ids : [$ids])
            ->flatMap(fn (mixed $value): array => is_array($value) ? $value : explode(',', (string) $value))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();
    }

    private function normalizeProductTypeIds(mixed $ids): Collection
    {
        return collect(is_array($ids) ? $ids : [$ids])
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();
    }

    private function productTypeOptionsByIds(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $options = $this->productTypeOptions(
            ProductType::query()
                ->whereIn('id', $ids)
                ->get()
        );
        $optionsById = collect($options)->keyBy('id');

        return $ids
            ->map(fn (int $id): ?array => $optionsById->get($id))
            ->filter()
            ->values()
            ->all();
    }

    private function productTypeOptions(Collection $productTypes): array
    {
        return $productTypes
            ->map(fn (ProductType $productType): array => [
                'id' => $productType->id,
                'label' => "{$productType->name} ({$productType->code})",
            ])
            ->all();
    }

    private function fuelTypeOptions(Collection $fuelTypes): array
    {
        return $fuelTypes
            ->map(fn (FuelType $fuelType): array => [
                'id' => $fuelType->id,
                'label' => $fuelType->code ? "{$fuelType->name} ({$fuelType->code})" : $fuelType->name,
            ])
            ->all();
    }

    private function brandOptions(Collection $brands): array
    {
        return $brands
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'label' => $brand->country ? "{$brand->name} - {$brand->country}" : $brand->name,
                'country' => $brand->country,
                'is_unknown' => strtoupper((string) $brand->code) === 'UNKN',
            ])
            ->all();
    }

    private function siteOptions(Collection $sites): array
    {
        return $sites
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'label' => "{$site->name} ({$site->code})",
            ])
            ->all();
    }

    private function productOptions(Collection $products): array
    {
        return $products
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'label' => "{$product->product_name} ({$product->product_code})",
            ])
            ->all();
    }

    private function taxProfileOptions(): array
    {
        return TaxProfile::query()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (TaxProfile $taxProfile): array => [
                'id' => $taxProfile->id,
                'label' => "{$taxProfile->name} ({$taxProfile->tax_rate}%)",
            ])
            ->all();
    }

    private function carModelLabel(?CarModel $carModel): string
    {
        if (! $carModel) {
            return 'Unassigned vehicle';
        }

        $label = collect([
            $carModel->make,
            $carModel->model,
            $carModel->year,
            $carModel->engine_size,
            $carModel->variant_name,
        ])
            ->filter()
            ->join(' ');

        return $carModel->country_of_origin
            ? "{$label} ({$carModel->country_of_origin})"
            : $label;
    }

    private function normalizeEngineSize(mixed $engineSize): ?string
    {
        $value = trim((string) $engineSize);

        if ($value === '') {
            return null;
        }

        $numeric = (float) $value;

        if ($numeric > 0 && $numeric < 100) {
            $numeric *= 1000;
        }

        $number = rtrim(rtrim(number_format($numeric, 1, '.', ''), '0'), '.');

        return $number === '' ? null : "{$number}cc";
    }

    private function money(float $amount): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($amount);
    }
}
