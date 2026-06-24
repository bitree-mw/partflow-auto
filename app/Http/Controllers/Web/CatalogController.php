<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function carModels(): View
    {
        return view('catalog.car-models.index', [
            'title' => 'Car Models',
            'description' => 'Create vehicle fitment records used for product codes and compatibility searches.',
            'carModels' => $this->mockCarModels(),
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
        // Placeholder submit path: the real save will call the catalogue API/service later.
        return redirect()
            ->route('web.catalog.car-models.index')
            ->with('success', 'Car model setup captured in the UI. API connection will be added later.');
    }

    public function partTypes(): View
    {
        return view('catalog.part-types.index', [
            'title' => 'Part Types',
            'description' => 'Maintain standard part categories and short codes used in generated product codes.',
            'partTypes' => [
                ['name' => 'Brake Pads', 'code' => 'BP', 'products' => 42, 'status' => 'Active'],
                ['name' => 'Oil Filter', 'code' => 'OF', 'products' => 36, 'status' => 'Active'],
                ['name' => 'Shock Absorber', 'code' => 'SA', 'products' => 18, 'status' => 'Active'],
                ['name' => 'Fuel Pump', 'code' => 'FP', 'products' => 9, 'status' => 'Active'],
            ],
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
        // Placeholder submit path: the real save will call the catalogue API/service later.
        return redirect()
            ->route('web.catalog.part-types.index')
            ->with('success', 'Part type setup captured in the UI. API connection will be added later.');
    }

    public function products(): View
    {
        return view('catalog.products.index', [
            'title' => 'Parts Catalogue',
            'description' => 'Manage sellable parts, generated product codes, references, pricing, and compatibility.',
            'products' => [
                ['name' => 'Toyota Corolla Brake Pads Front', 'code' => 'TYCO14BP-I', 'model' => 'Toyota Corolla 2014 1.6L', 'type' => 'Brake Pads', 'price' => 'MWK 32,500', 'stock' => 27, 'branch_stock' => [['site' => 'Area 23', 'qty' => 12], ['site' => 'Old Town', 'qty' => 30], ['site' => 'City Centre', 'qty' => 8], ['site' => 'Mzuzu', 'qty' => 0]]],
                ['name' => 'Nissan Tiida Oil Filter', 'code' => 'NSNT12OF-I', 'model' => 'Nissan Tiida 2012 1.5L', 'type' => 'Oil Filter', 'price' => 'MWK 12,000', 'stock' => 16, 'branch_stock' => [['site' => 'Area 23', 'qty' => 6], ['site' => 'Old Town', 'qty' => 16], ['site' => 'City Centre', 'qty' => 9], ['site' => 'Mzuzu', 'qty' => 0]]],
                ['name' => 'Mazda Demio Rear Shock Absorber', 'code' => 'MZDM12SA-I', 'model' => 'Mazda Demio 2012 1.3L', 'type' => 'Shock Absorber', 'price' => 'MWK 83,000', 'stock' => 7, 'branch_stock' => [['site' => 'Area 23', 'qty' => 2], ['site' => 'Old Town', 'qty' => 6], ['site' => 'City Centre', 'qty' => 4], ['site' => 'Mzuzu', 'qty' => 3]]],
            ],
            'partTypes' => [
                ['name' => 'Brake Pads', 'code' => 'BP', 'products' => 42, 'status' => 'Active'],
                ['name' => 'Oil Filter', 'code' => 'OF', 'products' => 36, 'status' => 'Active'],
                ['name' => 'Shock Absorber', 'code' => 'SA', 'products' => 18, 'status' => 'Active'],
                ['name' => 'Fuel Pump', 'code' => 'FP', 'products' => 9, 'status' => 'Active'],
            ],
            'carModels' => $this->mockCarModels(),
            'catalogueSummary' => [
                ['label' => 'Parts available', 'value' => '3', 'detail' => 'Sellable catalogue items'],
                ['label' => 'Part types', 'value' => '4', 'detail' => 'Reusable product categories'],
                ['label' => 'Car models', 'value' => '4', 'detail' => 'Fitment and variant records'],
            ],
        ]);
    }

    public function createProduct(): View
    {
        return view('catalog.products.create', [
            'title' => 'Add Part',
            'description' => 'Build a part using car model, part type, fuel, brand, tax, references, and compatibility.',
            'carModels' => $this->mockCarModels(),
            'countries' => config('countries'),
            'partTypes' => ['Brake Pads', 'Oil Filter', 'Shock Absorber', 'Fuel Pump'],
            'fuelTypes' => ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Universal'],
            'brands' => ['Toyota Genuine', 'Bosch', 'Denso', 'Aftermarket'],
            'taxProfiles' => ['VAT Inclusive 17.5%', 'VAT Exclusive 17.5%', 'Tax Exempt', 'No VAT'],
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        // Placeholder submit path: the real save will call the catalogue API/service later.
        return redirect()
            ->route('web.catalog.products.index')
            ->with('success', 'Part setup captured in the UI. API connection will be added later.');
    }

    private function mockCarModels(): array
    {
        // Shared fixtures keep car-model lists consistent across setup and part-creation pages.
        return [
            ['make' => 'Toyota', 'model' => 'Corolla', 'year' => 2014, 'engine' => '1.6L', 'variant' => 'Sedan', 'origin' => 'Japan', 'products' => 23],
            ['make' => 'Nissan', 'model' => 'Tiida', 'year' => 2012, 'engine' => '1.5L', 'variant' => 'Sedan', 'origin' => 'Japan', 'products' => 19],
            ['make' => 'Mazda', 'model' => 'Demio', 'year' => 2012, 'engine' => '1.3L', 'variant' => 'DE', 'origin' => 'Japan', 'products' => 31],
            ['make' => 'Honda', 'model' => 'Fit', 'year' => 2015, 'engine' => '1.3L', 'variant' => 'Hybrid', 'origin' => 'Japan', 'products' => 14],
        ];
    }
}
