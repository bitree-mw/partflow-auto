<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $products = $this->products();
        $selectedProduct = $products[0];

        return view('pos', [
            'title' => 'Point Of Sale',
            'currentBranch' => 'Area 23',
            'cashier' => 'Cashier Desk 01',
            'saleNumber' => 'POS-1042',
            'quickSearches' => ['oil filter', 'demio', 'shock absorber', 'fuel pump'],
            'vehicleFilters' => [
                'All vehicles',
                'Toyota Corolla 1.6L Sedan (2014)',
                'Nissan Tiida 1.5L Sedan (2012)',
                'Mazda Demio 1.3L DE (2012)',
                'Honda Fit 1.3L Hybrid (2015)',
            ],
            'partTypeFilters' => ['All part types', 'Brake Pads', 'Oil Filter', 'Shock Absorber', 'Fuel Pump'],
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'cartLines' => [
                $this->cartLine($products[0], 1),
                $this->cartLine($products[1], 2),
            ],
            'saleTotals' => [
                'subtotal' => $this->money(56500),
                'discount' => $this->money(2500),
                'tax' => $this->money(8043),
                'total' => $this->money(54000),
                'profit' => $this->money(13400),
            ],
            'paymentMethods' => ['Cash', 'Mobile Money', 'Card', 'Bank Transfer'],
        ]);
    }

    private function products(): array
    {
        // UI fixtures mirror the POS product resource shape without calling the API yet.
        return [
            $this->product(
                id: 1,
                code: 'TYCO14BP-I',
                name: 'Toyota Corolla Brake Pads Front',
                description: 'Front axle ceramic brake pad set, pack of 4.',
                vehicle: 'Toyota Corolla 2014 1.6L Sedan',
                partType: 'Brake Pads',
                brand: 'Denso',
                origin: 'Japan',
                barcode: '60012900421',
                oem: '04465-02340',
                price: 32500,
                cost: 24200,
                stock: [
                    ['branch' => 'Area 23', 'on_hand' => 15, 'reserved' => 3],
                    ['branch' => 'Old Town', 'on_hand' => 31, 'reserved' => 1],
                    ['branch' => 'City Centre', 'on_hand' => 8, 'reserved' => 0],
                    ['branch' => 'Mzuzu', 'on_hand' => 0, 'reserved' => 0],
                ],
                compatibleCars: ['Toyota Corolla 2012-2016', 'Toyota Axio 2013-2016']
            ),
            $this->product(
                id: 2,
                code: 'NSNT12OF-I',
                name: 'Nissan Tiida Oil Filter',
                description: 'Spin-on oil filter for petrol engines.',
                vehicle: 'Nissan Tiida 2012 1.5L Hatchback',
                partType: 'Oil Filter',
                brand: 'Bosch',
                origin: 'South Africa',
                barcode: '60012900438',
                oem: '15208-9F60A',
                price: 12000,
                cost: 8200,
                stock: [
                    ['branch' => 'Area 23', 'on_hand' => 6, 'reserved' => 0],
                    ['branch' => 'Old Town', 'on_hand' => 18, 'reserved' => 2],
                    ['branch' => 'City Centre', 'on_hand' => 10, 'reserved' => 1],
                    ['branch' => 'Mzuzu', 'on_hand' => 0, 'reserved' => 0],
                ],
                compatibleCars: ['Nissan Tiida 2008-2013', 'Nissan Note 2011-2014']
            ),
            $this->product(
                id: 3,
                code: 'MZDM12SA-I',
                name: 'Mazda Demio Rear Shock Absorber',
                description: 'Rear gas shock absorber, sold each.',
                vehicle: 'Mazda Demio 2012 1.3L DE',
                partType: 'Shock Absorber',
                brand: 'Aftermarket',
                origin: 'China',
                barcode: '60012900445',
                oem: 'D651-28-700',
                price: 83000,
                cost: 62400,
                stock: [
                    ['branch' => 'Area 23', 'on_hand' => 2, 'reserved' => 0],
                    ['branch' => 'Old Town', 'on_hand' => 7, 'reserved' => 1],
                    ['branch' => 'City Centre', 'on_hand' => 4, 'reserved' => 0],
                    ['branch' => 'Mzuzu', 'on_hand' => 3, 'reserved' => 0],
                ],
                compatibleCars: ['Mazda Demio 2008-2014', 'Ford Fiesta 2009-2012']
            ),
            $this->product(
                id: 4,
                code: 'HNFT15FP-I',
                name: 'Honda Fit Fuel Pump',
                description: 'Electric in-tank fuel pump assembly.',
                vehicle: 'Honda Fit 2015 1.3L Hybrid',
                partType: 'Fuel Pump',
                brand: 'Denso',
                origin: 'Japan',
                barcode: '60012900452',
                oem: '17045-T5A-J00',
                price: 145000,
                cost: 112000,
                stock: [
                    ['branch' => 'Area 23', 'on_hand' => 1, 'reserved' => 0],
                    ['branch' => 'Old Town', 'on_hand' => 2, 'reserved' => 0],
                    ['branch' => 'City Centre', 'on_hand' => 0, 'reserved' => 0],
                    ['branch' => 'Mzuzu', 'on_hand' => 1, 'reserved' => 0],
                ],
                compatibleCars: ['Honda Fit 2014-2017', 'Honda Grace 2015-2017']
            ),
        ];
    }

    private function product(
        int $id,
        string $code,
        string $name,
        string $description,
        string $vehicle,
        string $partType,
        string $brand,
        string $origin,
        string $barcode,
        string $oem,
        int $price,
        int $cost,
        array $stock,
        array $compatibleCars
    ): array {
        $branchStock = collect($stock)->map(function (array $branch): array {
            $available = max(0, $branch['on_hand'] - $branch['reserved']);

            return [
                'branch' => $branch['branch'],
                'on_hand' => $branch['on_hand'],
                'reserved' => $branch['reserved'],
                'available' => $available,
                'status' => $available === 0 ? 'out' : ($available <= 2 ? 'low' : 'ok'),
            ];
        })->all();

        $totalAvailable = collect($branchStock)->sum('available');
        $bestBranch = collect($branchStock)->sortByDesc('available')->first();
        $margin = $price - $cost;
        $branchStockSummary = collect($branchStock)
            ->map(fn (array $branch): string => "{$branch['branch']} {$branch['available']}")
            ->implode(' - ');

        return [
            'id' => $id,
            'product_id' => $id,
            'product_code' => $code,
            'product_name' => $name,
            'pos_description' => $description,
            'vehicle' => $vehicle,
            'part_type' => $partType,
            'brand' => $brand,
            'part_country_of_origin' => $origin,
            'barcode' => $barcode,
            'oem_number' => $oem,
            'selling_price' => $price,
            'selling_price_display' => $this->money($price),
            'unit_cost' => $cost,
            'margin_display' => $this->money($margin),
            'tax_profile' => 'VAT Inclusive 17.5%',
            'compatible_cars' => $compatibleCars,
            'branch_stock' => $branchStock,
            'branch_stock_summary' => $branchStockSummary,
            'total_available' => $totalAvailable,
            'best_branch' => $bestBranch['branch'],
            'best_branch_available' => $bestBranch['available'],
        ];
    }

    private function money(int $amount): string
    {
        return 'MWK '.number_format($amount);
    }

    private function cartLine(array $product, int $quantity): array
    {
        $lineTotal = $product['selling_price'] * $quantity;

        return [
            'name' => $product['product_name'],
            'code' => $product['product_code'],
            'quantity' => $quantity,
            'unit_price' => $product['selling_price'],
            'unit_price_display' => $product['selling_price_display'],
            'line_total_display' => $this->money($lineTotal),
        ];
    }
}
