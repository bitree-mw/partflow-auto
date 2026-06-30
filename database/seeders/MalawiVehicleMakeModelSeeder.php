<?php

namespace Database\Seeders;

use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

class MalawiVehicleMakeModelSeeder extends Seeder
{
    public function run(): void
    {
        $makes = [
            'Toyota' => ['code' => 'TY', 'country' => 'Japan', 'models' => ['Aqua', 'Allion', 'Alphard', 'Axio', 'Belta', 'Camry', 'Corolla', 'Corolla Fielder', 'Crown', 'Dyna', 'Fortuner', 'Harrier', 'Hiace', 'Hilux', 'ISIS', 'Land Cruiser', 'Land Cruiser Prado', 'Noah', 'Passo', 'Premio', 'Probox', 'Ractis', 'Raum', 'RAV4', 'Rush', 'Sienta', 'Spacio', 'TownAce', 'Vanguard', 'Vellfire', 'Vitz', 'Voxy', 'Wish']],
            'Nissan' => ['code' => 'NS', 'country' => 'Japan', 'models' => ['AD Van', 'Bluebird Sylphy', 'Caravan', 'Cube', 'Dualis', 'Elgrand', 'Juke', 'Lafesta', 'March', 'Murano', 'Navara', 'Note', 'NV200', 'Patrol', 'Serena', 'Skyline', 'Sunny', 'Teana', 'Tiida', 'Wingroad', 'X-Trail']],
            'Honda' => ['code' => 'HN', 'country' => 'Japan', 'models' => ['Accord', 'Airwave', 'Civic', 'CR-V', 'Fit', 'Fit Shuttle', 'Freed', 'HR-V', 'Insight', 'Odyssey', 'Stepwgn', 'Stream', 'Vezel']],
            'Mazda' => ['code' => 'MZ', 'country' => 'Japan', 'models' => ['Atenza', 'Axela', 'Biante', 'BT-50', 'CX-3', 'CX-5', 'CX-7', 'Demio', 'Familia Van', 'MPV', 'Premacy', 'Tribute', 'Verisa']],
            'Mitsubishi' => ['code' => 'MT', 'country' => 'Japan', 'models' => ['Canter', 'Colt', 'Delica', 'Fuso Fighter', 'L200', 'Lancer', 'Mirage', 'Outlander', 'Pajero', 'Pajero IO', 'Pajero Mini', 'RVR', 'Triton']],
            'Subaru' => ['code' => 'SB', 'country' => 'Japan', 'models' => ['Exiga', 'Forester', 'Impreza', 'Legacy', 'Outback', 'XV']],
            'Suzuki' => ['code' => 'SZ', 'country' => 'Japan', 'models' => ['Alto', 'Carry', 'Escudo', 'Every', 'Grand Vitara', 'Jimny', 'Solio', 'Spacia', 'Swift', 'SX4', 'Vitara', 'Wagon R']],
            'Isuzu' => ['code' => 'IZ', 'country' => 'Japan', 'models' => ['D-Max', 'Elf', 'Forward', 'MU-X', 'N-Series', 'Wizard']],
            'Daihatsu' => ['code' => 'DH', 'country' => 'Japan', 'models' => ['Boon', 'Hijet', 'Mira', 'Move', 'Rocky', 'Terios']],
            'Lexus' => ['code' => 'LX', 'country' => 'Japan', 'models' => ['CT', 'ES', 'GS', 'IS', 'LX', 'NX', 'RX']],
            'Ford' => ['code' => 'FD', 'country' => 'United States', 'models' => ['EcoSport', 'Everest', 'Fiesta', 'Focus', 'Ranger', 'Transit']],
            'Volkswagen' => ['code' => 'VW', 'country' => 'Germany', 'models' => ['Amarok', 'Caddy', 'Golf', 'Jetta', 'Passat', 'Polo', 'Tiguan', 'Touareg']],
            'Mercedes-Benz' => ['code' => 'MB', 'country' => 'Germany', 'models' => ['A-Class', 'C-Class', 'E-Class', 'M-Class', 'S-Class', 'Sprinter', 'Vito']],
            'BMW' => ['code' => 'BM', 'country' => 'Germany', 'models' => ['1 Series', '3 Series', '5 Series', '7 Series', 'X1', 'X3', 'X5', 'X6']],
            'Hyundai' => ['code' => 'HY', 'country' => 'South Korea', 'models' => ['Accent', 'Elantra', 'H-1', 'i10', 'i20', 'i30', 'Santa Fe', 'Sonata', 'Tucson']],
            'Kia' => ['code' => 'KA', 'country' => 'South Korea', 'models' => ['Cerato', 'K2700', 'Morning', 'Picanto', 'Rio', 'Sorento', 'Sportage']],
            'Chevrolet' => ['code' => 'CV', 'country' => 'United States', 'models' => ['Aveo', 'Captiva', 'Colorado', 'Cruze', 'Spark', 'Trailblazer']],
            'Jeep' => ['code' => 'JP', 'country' => 'United States', 'models' => ['Cherokee', 'Compass', 'Grand Cherokee', 'Renegade', 'Wrangler']],
            'Land Rover' => ['code' => 'LR', 'country' => 'United Kingdom', 'models' => ['Defender', 'Discovery', 'Freelander', 'Range Rover', 'Range Rover Sport']],
            'Peugeot' => ['code' => 'PG', 'country' => 'France', 'models' => ['206', '207', '208', '307', '308', '3008', 'Partner']],
            'Renault' => ['code' => 'RN', 'country' => 'France', 'models' => ['Clio', 'Duster', 'Kangoo', 'Koleos', 'Megane', 'Sandero']],
            'Mahindra' => ['code' => 'MH', 'country' => 'India', 'models' => ['Bolero', 'Pik Up', 'Scorpio', 'XUV500']],
            'Tata' => ['code' => 'TT', 'country' => 'India', 'models' => ['Indica', 'Indigo', 'Super Ace', 'Xenon']],
            'GWM' => ['code' => 'GW', 'country' => 'China', 'models' => ['C20R', 'H5', 'M4', 'P-Series', 'Steed']],
            'Haval' => ['code' => 'HV', 'country' => 'China', 'models' => ['H1', 'H2', 'H6', 'Jolion']],
            'Chery' => ['code' => 'CH', 'country' => 'China', 'models' => ['Arrizo', 'QQ', 'Tiggo 2', 'Tiggo 4', 'Tiggo 7']],
        ];

        $makeModels = [];
        $usedModelCodes = [];

        foreach ($makes as $makeName => $record) {
            $make = CarMake::updateOrCreate(
                ['code' => $record['code']],
                [
                    'name' => $makeName,
                    'description' => 'Vehicle make commonly found in Malawi imports, regional fleets, or dealer supply between 2000 and 2026.',
                    'is_active' => true,
                ]
            );

            foreach ($record['models'] as $modelName) {
                $modelCode = $this->modelCode($modelName, $usedModelCodes[$make->id] ?? []);
                $usedModelCodes[$make->id][] = $modelCode;

                $model = VehicleModel::updateOrCreate(
                    ['car_make_id' => $make->id, 'name' => $modelName],
                    [
                        'code' => $modelCode,
                        'year' => 2000,
                        'body_style' => null,
                        'description' => 'Model name available for Malawi vehicle identity selection.',
                        'is_active' => true,
                    ]
                );

                $makeModels["{$makeName}|{$modelName}"] = [$make, $model];
            }
        }

        foreach ($this->fitments() as $fitment) {
            [$make, $model] = $makeModels["{$fitment['make']}|{$fitment['model']}"];

            CarModel::updateOrCreate(
                [
                    'car_make_id' => $make->id,
                    'vehicle_model_id' => $model->id,
                    'year' => $fitment['year'],
                    'engine_size' => $fitment['engine_size'],
                    'variant_name' => $fitment['variant_name'],
                ],
                [
                    'make' => $make->name,
                    'make_code' => $make->code,
                    'model' => $model->name,
                    'model_code' => $model->code,
                    'country_of_origin' => $fitment['country_of_origin'] ?? $fitment['origin'] ?? null,
                    'notes' => 'Common Malawi fitment variant seeded for catalogue compatibility.',
                    'is_active' => true,
                ]
            );
        }
    }

    private function modelCode(string $model, array $usedCodes): string
    {
        $base = str($model)->upper()->replaceMatches('/[^A-Z0-9]/', '')->substr(0, 8)->toString() ?: 'MODEL';
        $code = $base;
        $suffix = 1;

        while (in_array($code, $usedCodes, true)) {
            $code = substr($base, 0, 6).str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $code;
    }

    private function fitments(): array
    {
        return [
            ['make' => 'Toyota', 'model' => 'Corolla', 'year' => 2006, 'engine_size' => '1500cc', 'variant_name' => 'NZE Sedan'],
            ['make' => 'Toyota', 'model' => 'Corolla', 'year' => 2014, 'engine_size' => '1600cc', 'variant_name' => 'Sedan'],
            ['make' => 'Toyota', 'model' => 'Axio', 'year' => 2013, 'engine_size' => '1500cc', 'variant_name' => 'Sedan'],
            ['make' => 'Toyota', 'model' => 'Corolla Fielder', 'year' => 2014, 'engine_size' => '1500cc', 'variant_name' => 'Wagon'],
            ['make' => 'Toyota', 'model' => 'Vitz', 'year' => 2012, 'engine_size' => '1300cc', 'variant_name' => 'Hatchback'],
            ['make' => 'Toyota', 'model' => 'Aqua', 'year' => 2014, 'engine_size' => '1500cc', 'variant_name' => 'Hybrid'],
            ['make' => 'Toyota', 'model' => 'Passo', 'year' => 2013, 'engine_size' => '1000cc', 'variant_name' => 'Hatchback'],
            ['make' => 'Toyota', 'model' => 'Probox', 'year' => 2014, 'engine_size' => '1500cc', 'variant_name' => 'Van'],
            ['make' => 'Toyota', 'model' => 'Hiace', 'year' => 2012, 'engine_size' => '2500cc', 'variant_name' => 'Diesel Van'],
            ['make' => 'Toyota', 'model' => 'Hilux', 'year' => 2016, 'engine_size' => '2400cc', 'variant_name' => 'GD-6'],
            ['make' => 'Toyota', 'model' => 'Land Cruiser Prado', 'year' => 2015, 'engine_size' => '3000cc', 'variant_name' => 'Diesel'],
            ['make' => 'Nissan', 'model' => 'Tiida', 'year' => 2012, 'engine_size' => '1500cc', 'variant_name' => 'Hatchback'],
            ['make' => 'Nissan', 'model' => 'Note', 'year' => 2014, 'engine_size' => '1200cc', 'variant_name' => 'Hatchback'],
            ['make' => 'Nissan', 'model' => 'X-Trail', 'year' => 2015, 'engine_size' => '2000cc', 'variant_name' => 'SUV'],
            ['make' => 'Nissan', 'model' => 'Navara', 'year' => 2016, 'engine_size' => '2500cc', 'variant_name' => 'Diesel Pickup'],
            ['make' => 'Honda', 'model' => 'Fit', 'year' => 2015, 'engine_size' => '1300cc', 'variant_name' => 'Hybrid'],
            ['make' => 'Honda', 'model' => 'CR-V', 'year' => 2014, 'engine_size' => '2000cc', 'variant_name' => 'SUV'],
            ['make' => 'Mazda', 'model' => 'Demio', 'year' => 2012, 'engine_size' => '1300cc', 'variant_name' => 'DE'],
            ['make' => 'Mazda', 'model' => 'Axela', 'year' => 2014, 'engine_size' => '1500cc', 'variant_name' => 'Sport'],
            ['make' => 'Mazda', 'model' => 'CX-5', 'year' => 2015, 'engine_size' => '2200cc', 'variant_name' => 'Diesel'],
            ['make' => 'Mitsubishi', 'model' => 'Pajero', 'year' => 2012, 'engine_size' => '3200cc', 'variant_name' => 'Di-D'],
            ['make' => 'Mitsubishi', 'model' => 'L200', 'year' => 2016, 'engine_size' => '2500cc', 'variant_name' => 'Diesel Pickup'],
            ['make' => 'Subaru', 'model' => 'Forester', 'year' => 2014, 'engine_size' => '2000cc', 'variant_name' => 'SUV'],
            ['make' => 'Suzuki', 'model' => 'Swift', 'year' => 2014, 'engine_size' => '1200cc', 'variant_name' => 'Hatchback'],
            ['make' => 'Isuzu', 'model' => 'D-Max', 'year' => 2016, 'engine_size' => '2500cc', 'variant_name' => 'Diesel Pickup'],
            ['make' => 'Ford', 'model' => 'Ranger', 'year' => 2016, 'engine_size' => '2200cc', 'variant_name' => 'TDCi'],
            ['make' => 'Volkswagen', 'model' => 'Polo', 'year' => 2014, 'engine_size' => '1400cc', 'variant_name' => 'Hatchback'],
            ['make' => 'Hyundai', 'model' => 'Tucson', 'year' => 2016, 'engine_size' => '2000cc', 'variant_name' => 'SUV'],
            ['make' => 'Kia', 'model' => 'Sportage', 'year' => 2016, 'engine_size' => '2000cc', 'variant_name' => 'SUV'],
        ];
    }
}
