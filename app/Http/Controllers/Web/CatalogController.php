<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\FuelType;
use App\Models\PartType;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\VehicleModel;
use App\Services\BrandService;
use App\Services\CarModelService;
use App\Services\FuelTypeService;
use App\Services\PartTypeService;
use App\Services\ProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CarModelService $carModelService,
        private readonly PartTypeService $partTypeService,
        private readonly ProductService $productService,
        private readonly FuelTypeService $fuelTypeService,
        private readonly BrandService $brandService
    ) {}

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
            'year' => ['required', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'engine_size' => ['nullable', 'string', 'max:50'],
            'variant_name' => ['nullable', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ])->after(function ($validator) use ($request): void {
            $modelBelongsToMake = VehicleModel::query()
                ->whereKey($request->input('vehicle_model_id'))
                ->where('car_make_id', $request->input('car_make_id'))
                ->exists();

            if (! $modelBelongsToMake) {
                $validator->errors()->add(
                    'vehicle_model_id',
                    'Choose a model that belongs to the selected make.'
                );

                return;
            }

            $exists = CarModel::query()
                ->where('car_make_id', $request->input('car_make_id'))
                ->where('vehicle_model_id', $request->input('vehicle_model_id'))
                ->where('year', $request->input('year'))
                ->where('engine_size', $request->input('engine_size'))
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
            'year' => ['required', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'engine_size' => ['nullable', 'string', 'max:50'],
            'variant_name' => ['nullable', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ])->after(function ($validator) use ($request, $carModel): void {
            $modelBelongsToMake = VehicleModel::query()
                ->whereKey($request->input('vehicle_model_id'))
                ->where('car_make_id', $request->input('car_make_id'))
                ->exists();

            if (! $modelBelongsToMake) {
                $validator->errors()->add('vehicle_model_id', 'Choose a model that belongs to the selected make.');

                return;
            }

            $exists = CarModel::query()
                ->whereKeyNot($carModel->id)
                ->where('car_make_id', $request->input('car_make_id'))
                ->where('vehicle_model_id', $request->input('vehicle_model_id'))
                ->where('year', $request->input('year'))
                ->where('engine_size', $request->input('engine_size'))
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

    public function partTypes(Request $request): View
    {
        $filters = $this->catalogueFilters($request);
        $partTypeQuery = PartType::query()
            ->withCount('products')
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']));

        $this->applyCatalogueSort($partTypeQuery, $filters, [
            'name' => 'name',
            'code' => 'code',
            'products' => 'products_count',
            'status' => 'is_active',
        ], [
            ['name', 'asc'],
        ]);

        $partTypes = $partTypeQuery
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (PartType $partType): array => $this->partTypeRow($partType));

        return view('catalog.part-types.index', [
            'title' => 'Part Types',
            'description' => 'Maintain standard part categories and short codes used in generated product codes.',
            'partTypes' => $partTypes,
            'filters' => $filters,
        ]);
    }

    public function createPartType(): View
    {
        return view('catalog.part-types.create', [
            'title' => 'Add Part Type',
            'description' => 'Create a reusable part type before adding parts/products.',
        ]);
    }

    public function storePartType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', 'unique:part_types,code'],
            'description' => ['nullable', 'string'],
        ], [
            'code.regex' => 'The part type code may only contain letters and numbers.',
        ]);

        $this->partTypeService->create($validated);

        return redirect()
            ->route('web.catalog.part-types.index')
            ->with('success', 'Part type saved successfully.');
    }

    public function editPartType(PartType $partType): View
    {
        return view('catalog.part-types.edit', [
            'title' => 'Edit Part Type',
            'description' => 'Update the part type name, code, and active status.',
            'partType' => $partType,
        ]);
    }

    public function updatePartType(Request $request, PartType $partType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('part_types', 'code')->ignore($partType->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'The part type code may only contain letters and numbers.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if (! $validated['is_active'] && $partType->products()->exists()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'This part type is linked to products and cannot be made inactive.');
        }

        $this->partTypeService->update($partType, $validated);

        return redirect()
            ->route('web.catalog.part-types.index')
            ->with('success', 'Part type updated successfully.');
    }

    public function destroyPartType(PartType $partType): RedirectResponse
    {
        if ($partType->products()->exists()) {
            return redirect()
                ->route('web.catalog.part-types.index')
                ->with('error', 'This part type is linked to products and cannot be made inactive.');
        }

        $this->partTypeService->update($partType, ['is_active' => false]);

        return redirect()
            ->route('web.catalog.part-types.index')
            ->with('success', 'Part type marked inactive.');
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
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/', 'unique:brands,code'],
            'country' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ], [
            'code.regex' => 'The brand code may only contain letters, numbers, and hyphens.',
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
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('brands', 'code')->ignore($brand->id)],
            'country' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'The brand code may only contain letters, numbers, and hyphens.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $this->brandService->update($brand, $validated);

        return redirect()
            ->route('web.catalog.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroyBrand(Brand $brand): RedirectResponse
    {
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
            'description' => 'Maintain fuel categories used to generate part codes and narrow compatibility.',
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
        $filters = $this->catalogueFilters($request, ['part_type_id', 'brand_id']);
        $productQuery = Product::query()
            ->select('products.*')
            ->selectSub(function ($query) {
                $query->from('site_stocks')
                    ->selectRaw('COALESCE(SUM(quantity_on_hand - reserved_quantity), 0)')
                    ->whereColumn('site_stocks.product_id', 'products.id');
            }, 'stock_total')
            ->with(['carModel', 'partType', 'fuelType', 'brand', 'taxProfile', 'siteStocks.site'])
            ->search($filters['search'] ?? null)
            ->when(($filters['is_active'] ?? '') !== '', fn ($query) => $query->where('is_active', (bool) (int) $filters['is_active']))
            ->when($filters['part_type_id'] ?? null, fn ($query, $partTypeId) => $query->where('part_type_id', $partTypeId))
            ->when($filters['brand_id'] ?? null, fn ($query, $brandId) => $query->where('brand_id', $brandId));

        $this->applyCatalogueSort($productQuery, $filters, [
            'name' => 'product_name',
            'code' => 'product_code',
            'vehicle' => 'car_model_id',
            'type' => 'part_type_id',
            'brand' => 'brand_id',
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
            'partTypeOptions' => $this->partTypeOptions($this->partTypeService->list(['is_active' => true])),
            'brandOptions' => $this->brandOptions($this->brandService->list(['is_active' => true])),
            'catalogueSummary' => [
                ['label' => 'Parts available', 'value' => (string) Product::query()->count(), 'detail' => 'Sellable catalogue items'],
                ['label' => 'Brands', 'value' => (string) Brand::query()->count(), 'detail' => 'Manufacturers and suppliers'],
                ['label' => 'Part types', 'value' => (string) PartType::query()->count(), 'detail' => 'Reusable product categories'],
                ['label' => 'Fuel types', 'value' => (string) FuelType::query()->count(), 'detail' => 'Petrol, diesel, hybrid, and other setups'],
                ['label' => 'Car models', 'value' => (string) CarModel::query()->count(), 'detail' => 'Fitment and variant records'],
            ],
        ]);
    }

    public function createProduct(): View
    {
        return view('catalog.products.create', [
            'title' => 'Add Part',
            'description' => 'Build a part using car model, part type, fuel, brand, tax, references, and compatibility.',
            'carModels' => $this->carModelOptions($this->carModelService->list(['is_active' => true])),
            'countries' => config('countries'),
            'partTypes' => $this->partTypeOptions($this->partTypeService->list(['is_active' => true])),
            'fuelTypes' => $this->fuelTypeOptions($this->fuelTypeService->list(['is_active' => true])),
            'brands' => $this->brandOptions($this->brandService->list(['is_active' => true])),
            'taxProfiles' => $this->taxProfileOptions(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_code' => ['nullable', 'string', 'max:100', 'unique:products,product_code'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'car_model_id' => ['required', 'integer', 'exists:car_models,id'],
            'part_type_id' => ['required', 'integer', 'exists:part_types,id'],
            'fuel_type_id' => ['nullable', 'integer', 'exists:fuel_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'part_country_of_origin' => ['nullable', 'string', 'max:100'],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0'],
            'default_low_stock_level' => ['nullable', 'integer', 'min:0'],
            'pack_size' => ['nullable', 'numeric', 'min:0.01'],
            'pos_description' => ['nullable', 'string'],
            'compatible_car_model_ids' => ['nullable', 'array'],
            'compatible_car_model_ids.*' => ['nullable', 'integer', 'distinct', 'exists:car_models,id'],
            'compatibility_notes' => ['nullable', 'string'],
        ]);

        $payload = $this->productPayload($validated);
        $this->productService->create($payload);

        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', 'Part saved successfully.');
    }

    public function editProduct(Product $product): View
    {
        $product->load(['compatibilities', 'carModel', 'partType', 'fuelType', 'brand', 'taxProfile']);

        return view('catalog.products.edit', [
            'title' => 'Edit Part',
            'description' => 'Update catalogue part details, pricing, and compatibility.',
            'product' => $product,
            'carModels' => $this->carModelOptions($this->carModelService->list()),
            'countries' => config('countries'),
            'partTypes' => $this->partTypeOptions($this->partTypeService->list()),
            'fuelTypes' => $this->fuelTypeOptions($this->fuelTypeService->list()),
            'brands' => $this->brandOptions($this->brandService->list()),
            'taxProfiles' => $this->taxProfileOptions(),
        ]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'product_code' => ['nullable', 'string', 'max:100', Rule::unique('products', 'product_code')->ignore($product->id)],
            'product_name' => ['nullable', 'string', 'max:255'],
            'car_model_id' => ['required', 'integer', 'exists:car_models,id'],
            'part_type_id' => ['required', 'integer', 'exists:part_types,id'],
            'fuel_type_id' => ['nullable', 'integer', 'exists:fuel_types,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tax_profile_id' => ['nullable', 'integer', 'exists:tax_profiles,id'],
            'part_country_of_origin' => ['nullable', 'string', 'max:100'],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'default_selling_price' => ['nullable', 'numeric', 'min:0'],
            'default_low_stock_level' => ['nullable', 'integer', 'min:0'],
            'pack_size' => ['nullable', 'numeric', 'min:0.01'],
            'pos_description' => ['nullable', 'string'],
            'compatible_car_model_ids' => ['nullable', 'array'],
            'compatible_car_model_ids.*' => ['nullable', 'integer', 'distinct', 'exists:car_models,id'],
            'compatibility_notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = $this->productPayload($validated);
        $payload['is_active'] = $request->boolean('is_active');
        $this->productService->update($product, $payload);

        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', 'Part updated successfully.');
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        $this->productService->update($product, ['is_active' => false]);

        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', 'Part marked inactive.');
    }

    private function productPayload(array $validated): array
    {
        $compatibilities = collect($validated['compatible_car_model_ids'] ?? [])
            ->filter()
            ->unique()
            ->reject(fn (int|string $id): bool => (int) $id === (int) $validated['car_model_id'])
            ->map(fn (int|string $id): array => [
                'car_model_id' => (int) $id,
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
                'label' => $model->name,
                'body_style' => $model->body_style,
            ])
            ->all();
    }

    private function partTypeRows(Collection $partTypes): array
    {
        return $partTypes
            ->map(fn (PartType $partType): array => $this->partTypeRow($partType))
            ->all();
    }

    private function partTypeRow(PartType $partType): array
    {
        return [
            'id' => $partType->id,
            'name' => $partType->name,
            'code' => $partType->code,
            'products' => $partType->products_count ?? 0,
            'is_active' => (bool) $partType->is_active,
            'status' => $partType->is_active ? 'Active' : 'Inactive',
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
        $branchStock = $product->siteStocks
            ->sortBy('site.name')
            ->map(fn ($stock): array => [
                'site' => $stock->site?->name ?? 'Unassigned',
                'qty' => $stock->available_quantity,
            ])
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'name' => $product->product_name,
            'code' => $product->product_code,
            'model' => $this->carModelLabel($product->carModel),
            'type' => $product->partType?->name ?? 'Unassigned',
            'brand' => $product->brand?->name ?? 'Unbranded',
            'price' => $this->money((float) $product->default_selling_price),
            'stock' => collect($branchStock)->sum('qty'),
            'branch_stock' => $branchStock,
            'is_active' => (bool) $product->is_active,
            'status' => $product->is_active ? 'Active' : 'Inactive',
        ];
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

    private function partTypeOptions(Collection $partTypes): array
    {
        return $partTypes
            ->map(fn (PartType $partType): array => [
                'id' => $partType->id,
                'label' => "{$partType->name} ({$partType->code})",
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

        return collect([
            $carModel->make,
            $carModel->model,
            $carModel->year,
            $carModel->engine_size,
            $carModel->variant_name,
        ])
            ->filter()
            ->join(' ');
    }

    private function money(float $amount): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($amount);
    }
}
