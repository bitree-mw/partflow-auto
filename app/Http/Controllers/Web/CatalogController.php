<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\FuelType;
use App\Models\PartType;
use App\Models\Product;
use App\Models\TaxProfile;
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

class CatalogController extends Controller
{
    public function __construct(
        private readonly CarModelService $carModelService,
        private readonly PartTypeService $partTypeService,
        private readonly ProductService $productService,
        private readonly FuelTypeService $fuelTypeService,
        private readonly BrandService $brandService
    ) {}

    public function carModels(): View
    {
        $carModels = $this->carModelService->list()->loadCount('products');

        return view('catalog.car-models.index', [
            'title' => 'Car Models',
            'description' => 'Create vehicle fitment records used for product codes and compatibility searches.',
            'carModels' => $this->carModelRows($carModels),
        ]);
    }

    public function createCarModel(): View
    {
        return view('catalog.car-models.create', [
            'title' => 'Add Car Model',
            'description' => 'Define make, model, year, engine, variant, and country of origin.',
            'countries' => config('countries'),
        ]);
    }

    public function storeCarModel(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'engine_size' => ['nullable', 'string', 'max:50'],
            'variant_name' => ['nullable', 'string', 'max:100'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ])->after(function ($validator) use ($request): void {
            $exists = CarModel::query()
                ->where('make', $request->input('make'))
                ->where('model', $request->input('model'))
                ->where('year', $request->input('year'))
                ->where('engine_size', $request->input('engine_size'))
                ->where('variant_name', $request->input('variant_name'))
                ->where('country_of_origin', $request->input('country_of_origin'))
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'model',
                    'This car make, model, year, and country of origin already exists.'
                );
            }
        })->validate();

        $this->carModelService->create($validated);

        return redirect()
            ->route('web.catalog.car-models.index')
            ->with('success', 'Car model saved successfully.');
    }

    public function partTypes(): View
    {
        $partTypes = $this->partTypeService->list()->loadCount('products');

        return view('catalog.part-types.index', [
            'title' => 'Part Types',
            'description' => 'Maintain standard part categories and short codes used in generated product codes.',
            'partTypes' => $this->partTypeRows($partTypes),
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

    public function fuelTypes(): View
    {
        $fuelTypes = $this->fuelTypeService->list()->loadCount('products');

        return view('catalog.fuel-types.index', [
            'title' => 'Fuel Types',
            'description' => 'Maintain fuel categories used to generate part codes and narrow compatibility.',
            'fuelTypes' => $this->fuelTypeRows($fuelTypes),
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

    public function products(): View
    {
        $products = $this->productService->list()->load('siteStocks.site');
        $partTypes = $this->partTypeService->list()->loadCount('products');
        $fuelTypes = $this->fuelTypeService->list()->loadCount('products');
        $carModels = $this->carModelService->list()->loadCount('products');

        return view('catalog.products.index', [
            'title' => 'Parts Catalogue',
            'description' => 'Manage sellable parts, generated product codes, references, pricing, and compatibility.',
            'products' => $this->productRows($products),
            'partTypes' => $this->partTypeRows($partTypes),
            'fuelTypes' => $this->fuelTypeRows($fuelTypes),
            'carModels' => $this->carModelRows($carModels),
            'catalogueSummary' => [
                ['label' => 'Parts available', 'value' => (string) $products->count(), 'detail' => 'Sellable catalogue items'],
                ['label' => 'Part types', 'value' => (string) $partTypes->count(), 'detail' => 'Reusable product categories'],
                ['label' => 'Fuel types', 'value' => (string) $fuelTypes->count(), 'detail' => 'Petrol, diesel, hybrid, and other setups'],
                ['label' => 'Car models', 'value' => (string) $carModels->count(), 'detail' => 'Fitment and variant records'],
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
            'barcode' => ['nullable', 'string', 'max:255'],
            'oem_number' => ['nullable', 'string', 'max:255'],
            'supplier_code' => ['nullable', 'string', 'max:255'],
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

    private function productPayload(array $validated): array
    {
        $references = collect([
            'barcode' => $validated['barcode'] ?? null,
            'oem_number' => $validated['oem_number'] ?? null,
            'supplier_code' => $validated['supplier_code'] ?? null,
        ])
            ->filter(fn (?string $value): bool => filled($value))
            ->map(fn (string $value, string $type): array => [
                'reference_type' => $type,
                'reference_value' => $value,
                'is_primary' => $type === 'barcode',
            ])
            ->values()
            ->all();

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
                'barcode',
                'oem_number',
                'supplier_code',
                'compatible_car_model_ids',
                'compatibility_notes',
            ])
            ->merge([
                'references' => $references,
                'compatibilities' => $compatibilities,
            ])
            ->all();
    }

    private function carModelRows(Collection $carModels): array
    {
        return $carModels
            ->map(fn (CarModel $carModel): array => [
                'make' => $carModel->make,
                'model' => $carModel->model,
                'year' => $carModel->year,
                'engine' => $carModel->engine_size ?: 'Any',
                'variant' => $carModel->variant_name ?: 'Standard',
                'origin' => $carModel->country_of_origin ?: 'Not set',
                'products' => $carModel->products_count ?? 0,
            ])
            ->all();
    }

    private function partTypeRows(Collection $partTypes): array
    {
        return $partTypes
            ->map(fn (PartType $partType): array => [
                'name' => $partType->name,
                'code' => $partType->code,
                'products' => $partType->products_count ?? 0,
                'status' => $partType->is_active ? 'Active' : 'Inactive',
            ])
            ->all();
    }

    private function fuelTypeRows(Collection $fuelTypes): array
    {
        return $fuelTypes
            ->map(fn (FuelType $fuelType): array => [
                'name' => $fuelType->name,
                'code' => $fuelType->code ?: 'N/A',
                'products' => $fuelType->products_count ?? 0,
                'status' => $fuelType->is_active ? 'Active' : 'Inactive',
            ])
            ->all();
    }

    private function productRows(Collection $products): array
    {
        return $products
            ->map(function (Product $product): array {
                $branchStock = $product->siteStocks
                    ->sortBy('site.name')
                    ->map(fn ($stock): array => [
                        'site' => $stock->site?->name ?? 'Unassigned',
                        'qty' => $stock->available_quantity,
                    ])
                    ->values()
                    ->all();

                return [
                    'name' => $product->product_name,
                    'code' => $product->product_code,
                    'model' => $this->carModelLabel($product->carModel),
                    'type' => $product->partType?->name ?? 'Unassigned',
                    'price' => $this->money((float) $product->default_selling_price),
                    'stock' => collect($branchStock)->sum('qty'),
                    'branch_stock' => $branchStock,
                ];
            })
            ->all();
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
        return 'MWK '.number_format($amount);
    }
}
