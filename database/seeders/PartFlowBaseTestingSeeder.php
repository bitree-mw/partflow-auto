<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Contact;
use App\Models\ExpenseCategory;
use App\Models\FuelType;
use App\Models\ProductType;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\ProductCompatibility;
use App\Models\ProductReference;
use App\Models\Site;
use App\Models\SiteStock;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PartFlowBaseTestingSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FuelTypeSeeder::class,
            TaxProfileSeeder::class,
        ]);

        $roles = $this->seedRoles();
        $sites = $this->seedSites();
        $users = $this->seedUsers($roles);
        $this->seedUserSiteAccess($users, $sites);
        $contacts = $this->seedContacts();
        $carModels = $this->seedCarModels();
        $productTypes = $this->seedProductTypes();
        $brands = $this->seedBrands();
        $paymentAccounts = $this->seedPaymentAccounts();
        $this->seedExpenseCategories();
        $this->seedProducts($carModels, $productTypes, $brands, $sites);
    }

    private function seedRoles(): array
    {
        return DefaultRolePermissionSeeder::upsert();
    }

    private function seedSites(): array
    {
        $records = [
            ['name' => 'Area 23', 'code' => 'A23', 'type' => 'shop', 'location' => 'Lilongwe', 'phone' => '+265 991 100 001', 'address' => 'Area 23, Lilongwe'],
            ['name' => 'Old Town', 'code' => 'OTN', 'type' => 'branch', 'location' => 'Lilongwe', 'phone' => '+265 991 100 002', 'address' => 'Old Town, Lilongwe'],
            ['name' => 'City Centre', 'code' => 'CTC', 'type' => 'branch', 'location' => 'Lilongwe', 'phone' => '+265 991 100 003', 'address' => 'City Centre, Lilongwe'],
            ['name' => 'Mzuzu', 'code' => 'MZU', 'type' => 'branch', 'location' => 'Mzuzu', 'phone' => '+265 991 100 004', 'address' => 'Mzuzu Main Market'],
            ['name' => 'Kanengo Warehouse', 'code' => 'KNW', 'type' => 'warehouse', 'location' => 'Lilongwe', 'phone' => '+265 991 100 005', 'address' => 'Kanengo Industrial Area'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record) => [
                $record['code'] => Site::updateOrCreate(
                    ['code' => $record['code']],
                    $record + ['is_active' => true]
                ),
            ])
            ->all();
    }

    private function seedUsers(array $roles): array
    {
        $records = [
            ['name' => 'System Admin', 'email' => 'admin@partflow.test', 'phone' => '+265 991 000 001', 'role' => 'System Administrator'],
            ['name' => 'Area 23 Cashier', 'email' => 'cashier.area23@partflow.test', 'phone' => '+265 991 000 002', 'role' => 'Cashier'],
            ['name' => 'Mzuzu Stock Lead', 'email' => 'stock.mzuzu@partflow.test', 'phone' => '+265 991 000 003', 'role' => 'Stock Controller'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record) => [
                $record['email'] => User::updateOrCreate(
                    ['email' => $record['email']],
                    [
                        'role_id' => $roles[$record['role']]->id,
                        'name' => $record['name'],
                        'phone' => $record['phone'],
                        'password' => Hash::make('password'),
                        'is_active' => true,
                    ]
                ),
            ])
            ->all();
    }

    private function seedUserSiteAccess(array $users, array $sites): void
    {
        foreach ($users as $user) {
            foreach ($sites as $site) {
                UserSiteAccess::updateOrCreate(
                    ['user_id' => $user->id, 'site_id' => $site->id],
                    [
                        'access_level' => match ($user->role->name) {
                            'System Administrator' => 'admin',
                            'Stock Controller' => 'stock',
                            'Cashier' => 'sales',
                            default => 'view_only',
                        },
                        'can_view_stock' => true,
                        'can_make_sales' => $user->email !== 'stock.mzuzu@partflow.test',
                        'can_receive_stock' => true,
                        'can_transfer_stock' => true,
                        'can_adjust_stock' => $user->email !== 'cashier.area23@partflow.test',
                        'is_default' => $site->code === 'A23',
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function seedContacts(): array
    {
        $records = [
            ['contact_type' => 'customer', 'code' => 'CUS-001', 'name' => 'AutoFix Garage', 'phone' => '+265 991 200 111', 'email' => 'orders@autofix.test', 'tax_number' => 'TPIN-CUS-001', 'credit_limit' => 750000, 'address' => 'Mchesi, Lilongwe', 'notes' => 'Regular workshop customer.'],
            ['contact_type' => 'customer', 'code' => 'CUS-002', 'name' => 'Northern Motors', 'phone' => '+265 888 455 221', 'email' => 'parts@northern.test', 'tax_number' => 'TPIN-CUS-002', 'credit_limit' => 500000, 'address' => 'Mzuzu', 'notes' => 'Credit terms reviewed monthly.'],
            ['contact_type' => 'supplier', 'code' => 'SUP-001', 'name' => 'Japan Auto Imports', 'phone' => '+265 999 800 441', 'email' => 'supply@japanimports.test', 'tax_number' => 'TPIN-SUP-001', 'credit_limit' => 2500000, 'address' => 'Blantyre', 'notes' => 'Main imported parts supplier.'],
            ['contact_type' => 'supplier', 'code' => 'SUP-002', 'name' => 'SA Parts Depot', 'phone' => '+27 11 555 9000', 'email' => 'orders@saparts.test', 'tax_number' => 'TPIN-SUP-002', 'credit_limit' => 1800000, 'address' => 'Johannesburg', 'notes' => 'Fast moving service items.'],
            ['contact_type' => 'both', 'code' => 'BTH-001', 'name' => 'Local Consumables', 'phone' => '+265 882 401 771', 'email' => 'sales@localconsumables.test', 'tax_number' => 'TPIN-BTH-001', 'credit_limit' => 400000, 'address' => 'Lilongwe', 'notes' => 'Local supplier and occasional customer.'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record) => [
                $record['code'] => Contact::updateOrCreate(
                    ['code' => $record['code']],
                    $record + ['is_active' => true]
                ),
            ])
            ->all();
    }

    private function seedCarModels(): array
    {
        $records = [
            ['make' => 'Toyota', 'make_code' => 'TY', 'model' => 'Corolla', 'model_code' => 'CO', 'year' => 2014, 'engine_size' => '1600cc', 'variant_name' => 'Sedan', 'country_of_origin' => 'Japan'],
            ['make' => 'Toyota', 'make_code' => 'TY', 'model' => 'Axio', 'model_code' => 'AX', 'year' => 2013, 'engine_size' => '1500cc', 'variant_name' => 'Sedan', 'country_of_origin' => 'Japan'],
            ['make' => 'Nissan', 'make_code' => 'NS', 'model' => 'Tiida', 'model_code' => 'NT', 'year' => 2012, 'engine_size' => '1500cc', 'variant_name' => 'Hatchback', 'country_of_origin' => 'Japan'],
            ['make' => 'Mazda', 'make_code' => 'MZ', 'model' => 'Demio', 'model_code' => 'DM', 'year' => 2012, 'engine_size' => '1300cc', 'variant_name' => 'DE', 'country_of_origin' => 'Japan'],
            ['make' => 'Honda', 'make_code' => 'HN', 'model' => 'Fit', 'model_code' => 'FT', 'year' => 2015, 'engine_size' => '1300cc', 'variant_name' => 'Hybrid', 'country_of_origin' => 'Japan'],
            ['make' => 'Ford', 'make_code' => 'FD', 'model' => 'Ranger', 'model_code' => 'RG', 'year' => 2016, 'engine_size' => '2200cc', 'variant_name' => 'TDCi', 'country_of_origin' => 'South Africa'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record) => [
                "{$record['make']} {$record['model']} {$record['year']}" => CarModel::updateOrCreate(
                    ['make' => $record['make'], 'model' => $record['model'], 'year' => $record['year'], 'engine_size' => $record['engine_size']],
                    $record + ['notes' => 'Seeded testing vehicle fitment.', 'is_active' => true]
                ),
            ])
            ->all();
    }

    private function seedProductTypes(): array
    {
        $records = [
            ['name' => 'Brake Pads', 'code' => 'BP', 'description' => 'Brake pad sets and friction material.'],
            ['name' => 'Oil Filter', 'code' => 'OF', 'description' => 'Engine oil filters.'],
            ['name' => 'Shock Absorber', 'code' => 'SA', 'description' => 'Suspension shock absorbers.'],
            ['name' => 'Fuel Pump', 'code' => 'FP', 'description' => 'Fuel pump assemblies.'],
            ['name' => 'Spark Plug Set', 'code' => 'SP', 'description' => 'Ignition spark plug sets.'],
            ['name' => 'Air Filter', 'code' => 'AF', 'description' => 'Engine air filters.'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record) => [
                $record['code'] => ProductType::updateOrCreate(
                    ['code' => $record['code']],
                    $record + ['is_active' => true]
                ),
            ])
            ->all();
    }

    private function seedBrands(): array
    {
        $records = [
            ['name' => 'Common', 'code' => 'COMM', 'country' => null, 'description' => 'Generic common replacement part brand.'],
            ['name' => 'Unknown', 'code' => 'UNKN', 'country' => null, 'description' => 'Fallback brand for parts whose manufacturer is not known.'],
            ['name' => 'Toyota Genuine', 'code' => 'TYGN', 'country' => 'Japan', 'description' => 'Toyota genuine parts.'],
            ['name' => 'Denso', 'code' => 'DENS', 'country' => 'Japan', 'description' => 'OEM electrical and service components.'],
            ['name' => 'Bosch', 'code' => 'BOSC', 'country' => 'Germany', 'description' => 'Service and electrical parts.'],
            ['name' => 'KYB', 'code' => 'KYBX', 'country' => 'Japan', 'description' => 'Suspension parts.'],
            ['name' => 'Aftermarket', 'code' => 'AFTM', 'country' => 'China', 'description' => 'General aftermarket parts.'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record) => [
                $record['code'] => Brand::updateOrCreate(
                    ['code' => $record['code']],
                    $record + ['is_active' => true]
                ),
            ])
            ->all();
    }

    private function seedPaymentAccounts(): array
    {
        $records = [
            ['account_name' => 'Area 23 Cash Till', 'account_type' => 'cash', 'bank_name' => 'Area 23 Main Counter', 'account_number' => 'TILL-A23', 'mobile_number' => null, 'account_holder_name' => 'Cashier Desk 01'],
            ['account_name' => 'Airtel Money Sales', 'account_type' => 'mobile_money', 'bank_name' => 'Airtel Money', 'account_number' => 'PF-AIRTEL-01', 'mobile_number' => '+265 991 000 200', 'account_holder_name' => 'PartFlow Auto Limited'],
            ['account_name' => 'National Bank Current', 'account_type' => 'bank', 'bank_name' => 'National Bank', 'account_number' => '1002044001', 'mobile_number' => null, 'account_holder_name' => 'PartFlow Auto Limited'],
            ['account_name' => 'Card Terminal', 'account_type' => 'card', 'bank_name' => 'Card Processor', 'account_number' => 'MERCH-PF-01', 'mobile_number' => null, 'account_holder_name' => 'PartFlow Auto Limited'],
        ];

        return collect($records)
            ->mapWithKeys(fn (array $record) => [
                $record['account_name'] => PaymentAccount::updateOrCreate(
                    ['account_name' => $record['account_name']],
                    $record + ['is_active' => true]
                ),
            ])
            ->all();
    }

    private function seedExpenseCategories(): void
    {
        $records = [
            ['name' => 'Rent', 'code' => 'RENT', 'description' => 'Site rent and leases.'],
            ['name' => 'Utilities', 'code' => 'UTIL', 'description' => 'Water, power, and internet.'],
            ['name' => 'Transport', 'code' => 'TRSP', 'description' => 'Deliveries and transport costs.'],
            ['name' => 'Stock Loss', 'code' => 'LOSS', 'description' => 'Shrinkage and loss adjustments.'],
        ];

        foreach ($records as $record) {
            ExpenseCategory::updateOrCreate(['code' => $record['code']], $record + ['is_active' => true]);
        }
    }

    private function seedProducts(array $carModels, array $productTypes, array $brands, array $sites): void
    {
        $fuel = FuelType::where('name', 'Petrol')->first();
        $hybrid = FuelType::where('name', 'Hybrid')->first();
        $diesel = FuelType::where('name', 'Diesel')->first();
        $tax = TaxProfile::where('is_default', true)->first();
        $products = [
            ['code' => 'BPTYCO141600', 'name' => 'Toyota Genuine Brake Pads (JPN) - Petrol', 'car' => 'Toyota Corolla 2014', 'type' => 'BP', 'fuel' => $fuel, 'brand' => 'TYGN', 'origin' => 'Japan', 'purchase' => 24200, 'sale' => 32500, 'low' => 6, 'refs' => ['barcode' => '60012900421', 'oem_number' => '04465-02340'], 'compatible' => ['Toyota Axio 2013']],
            ['code' => 'OFNSNT121500', 'name' => 'Bosch Oil Filter (ZAF) - Petrol', 'car' => 'Nissan Tiida 2012', 'type' => 'OF', 'fuel' => $fuel, 'brand' => 'BOSC', 'origin' => 'South Africa', 'purchase' => 8200, 'sale' => 12000, 'low' => 10, 'refs' => ['barcode' => '60012900438', 'oem_number' => '15208-9F60A'], 'compatible' => []],
            ['code' => 'SAMZDM121300', 'name' => 'KYB Shock Absorber (JPN) - Petrol', 'car' => 'Mazda Demio 2012', 'type' => 'SA', 'fuel' => $fuel, 'brand' => 'KYBX', 'origin' => 'Japan', 'purchase' => 62400, 'sale' => 83000, 'low' => 4, 'refs' => ['barcode' => '60012900445', 'oem_number' => 'D651-28-700'], 'compatible' => []],
            ['code' => 'FPHNFT151300', 'name' => 'Denso Fuel Pump (JPN) - Hybrid', 'car' => 'Honda Fit 2015', 'type' => 'FP', 'fuel' => $hybrid, 'brand' => 'DENS', 'origin' => 'Japan', 'purchase' => 112000, 'sale' => 145000, 'low' => 2, 'refs' => ['barcode' => '60012900452', 'oem_number' => '17045-T5A-J00'], 'compatible' => []],
            ['code' => 'AFFDRG162200', 'name' => 'Aftermarket Air Filter (ZAF) - Diesel', 'car' => 'Ford Ranger 2016', 'type' => 'AF', 'fuel' => $diesel, 'brand' => 'AFTM', 'origin' => 'South Africa', 'purchase' => 18000, 'sale' => 26500, 'low' => 8, 'refs' => ['barcode' => '60012900469', 'oem_number' => 'AB39-9601-AC'], 'compatible' => []],
        ];

        foreach ($products as $record) {
            $product = Product::updateOrCreate(
                ['product_code' => $record['code']],
                [
                    'product_name' => $record['name'],
                    'car_model_id' => $carModels[$record['car']]->id,
                    'product_type_id' => $productTypes[$record['type']]->id,
                    'fuel_type_id' => $record['fuel']->id,
                    'brand_id' => $brands[$record['brand']]->id,
                    'tax_profile_id' => $tax->id,
                    'part_country_of_origin' => $record['origin'],
                    'description' => "{$record['name']} seeded for testing catalogue, POS, and reporting.",
                    'pos_description' => null,
                    'default_purchase_price' => 0,
                    'default_selling_price' => $record['sale'],
                    'minimum_selling_price' => round($record['sale'] * 0.8, 2),
                    'default_low_stock_level' => $record['low'],
                    'unit_name' => 'Each',
                    'pack_size' => 1,
                    'is_active' => true,
                ]
            );

            foreach ($record['refs'] as $type => $value) {
                ProductReference::updateOrCreate(
                    ['product_id' => $product->id, 'reference_type' => $type, 'reference_value' => $value],
                    ['is_primary' => $type === 'barcode', 'notes' => 'Seeded testing reference.']
                );
            }

            foreach ($record['compatible'] as $compatibleKey) {
                ProductCompatibility::updateOrCreate(
                    ['product_id' => $product->id, 'car_model_id' => $carModels[$compatibleKey]->id],
                    ['notes' => 'Seeded compatible fitment.']
                );
            }

            foreach ($sites as $site) {
                $quantity = match ($site->code) {
                    'A23' => 12,
                    'OTN' => 20,
                    'CTC' => 8,
                    'MZU' => 3,
                    default => 30,
                };

                SiteStock::updateOrCreate(
                    ['product_id' => $product->id, 'site_id' => $site->id],
                    [
                        'quantity_on_hand' => $quantity,
                        'reserved_quantity' => $site->code === 'A23' ? 1 : 0,
                        'low_stock_level' => $record['low'],
                    ]
                );
            }
        }
    }
}
