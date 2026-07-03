<?php

namespace Database\Seeders;

use App\Models\CarMake;
use App\Models\CarModel;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MalawiVehicleMakeModelSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '1024M');

        $makes = [
            'Toyota' => ['code' => 'TY', 'country' => 'Japan', 'models' => ['4Runner', '86', 'Agya', 'Allex', 'Allion', 'Alphard', 'Altezza', 'Aqua', 'Aristo', 'Auris', 'Avanza', 'Avensis', 'Axio', 'bB', 'Belta', 'Brevis', 'Caldina', 'Cami', 'Camry', 'Celica', 'C-HR', 'Coaster', 'Comfort', 'Corolla', 'Corolla Cross', 'Corolla Fielder', 'Corolla Rumion', 'Crown', 'Duet', 'Dyna', 'Echo', 'Estima', 'FJ Cruiser', 'Fortuner', 'Gaia', 'Granvia', 'Harrier', 'Hiace', 'Hilux', 'iQ', 'ISIS', 'ist', 'Kluger', 'Land Cruiser', 'Land Cruiser Prado', 'LiteAce', 'Mark II', 'Mark X', 'Matrix', 'Nadia', 'Noah', 'Opa', 'Origin', 'Passo', 'Platz', 'Porte', 'Premio', 'Prius', 'Prius Alpha', 'Probox', 'Progres', 'Ractis', 'Raum', 'RAV4', 'Regius', 'Roomy', 'Rush', 'Sai', 'Sequoia', 'Sienna', 'Sienta', 'Soarer', 'Spacio', 'Starlet', 'Succeed', 'Supra', 'Tacoma', 'Tank', 'TownAce', 'Tundra', 'Vanguard', 'Vellfire', 'Verossa', 'Vios', 'Vitz', 'Voxy', 'Wish', 'Yaris', 'Yaris Cross']],
            'Nissan' => ['code' => 'NS', 'country' => 'Japan', 'models' => ['AD Van', 'Almera', 'Atlas', 'Bluebird Sylphy', 'Caravan', 'Cedric', 'Cefiro', 'Cima', 'Cube', 'Dualis', 'Elgrand', 'Fairlady Z', 'Fuga', 'GT-R', 'Juke', 'Kicks', 'Lafesta', 'Latio', 'Leaf', 'Liberty', 'March', 'Maxima', 'Micra', 'Moco', 'Murano', 'Navara', 'Note', 'NV200', 'Pathfinder', 'Patrol', 'Primera', 'Pulsar', 'Qashqai', 'Rogue', 'Serena', 'Silvia', 'Skyline', 'Stagea', 'Sunny', 'Sylphy', 'Teana', 'Terra', 'Tiida', 'Vanette', 'Versa', 'Wingroad', 'X-Trail']],
            'Honda' => ['code' => 'HN', 'country' => 'Japan', 'models' => ['Accord', 'Airwave', 'Avancier', 'BR-V', 'City', 'Civic', 'Clarity', 'CR-V', 'CR-Z', 'Crossroad', 'Elysion', 'Fit', 'Fit Shuttle', 'Freed', 'Grace', 'HR-V', 'Insight', 'Jade', 'Jazz', 'Mobilio', 'N-Box', 'N-One', 'Odyssey', 'Partner', 'Ridgeline', 'Shuttle', 'Stepwgn', 'Stream', 'Vezel', 'Zest']],
            'Mazda' => ['code' => 'MZ', 'country' => 'Japan', 'models' => ['2', '3', '5', '6', 'Atenza', 'Axela', 'Biante', 'Bongo', 'BT-50', 'Carol', 'CX-3', 'CX-30', 'CX-5', 'CX-7', 'CX-8', 'CX-9', 'Demio', 'Familia Van', 'Flair', 'MPV', 'Premacy', 'Roadster', 'RX-8', 'Tribute', 'Verisa']],
            'Mitsubishi' => ['code' => 'MT', 'country' => 'Japan', 'models' => ['Airtrek', 'ASX', 'Canter', 'Colt', 'Delica', 'Dion', 'eK', 'eK Space', 'Eclipse Cross', 'Fuso Fighter', 'Galant', 'Grandis', 'i-MiEV', 'L200', 'Lancer', 'Mirage', 'Montero', 'Outlander', 'Pajero', 'Pajero IO', 'Pajero Mini', 'RVR', 'Triton', 'Xpander']],
            'Subaru' => ['code' => 'SB', 'country' => 'Japan', 'models' => ['Baja', 'BRZ', 'Chiffon', 'Dex', 'Exiga', 'Forester', 'Impreza', 'Justy', 'Legacy', 'Levorg', 'Outback', 'Pleo', 'R1', 'R2', 'Sambar', 'Stella', 'Trezia', 'WRX', 'XV']],
            'Suzuki' => ['code' => 'SZ', 'country' => 'Japan', 'models' => ['Aerio', 'Alto', 'Baleno', 'Carry', 'Celerio', 'Ciaz', 'Ertiga', 'Escudo', 'Every', 'Grand Vitara', 'Hustler', 'Ignis', 'Jimny', 'Kei', 'Liana', 'MR Wagon', 'Palette', 'Solio', 'Spacia', 'Splash', 'Swift', 'SX4', 'Vitara', 'Wagon R', 'Xbee']],
            'Isuzu' => ['code' => 'IZ', 'country' => 'Japan', 'models' => ['Bighorn', 'D-Max', 'Elf', 'F-Series', 'Forward', 'Giga', 'Journey', 'MU-7', 'MU-X', 'N-Series', 'Rodeo', 'Trooper', 'Wizard']],
            'Daihatsu' => ['code' => 'DH', 'country' => 'Japan', 'models' => ['Altis', 'Be-Go', 'Boon', 'Copen', 'Esse', 'Hijet', 'Mira', 'Mira e:S', 'Move', 'Naked', 'Rocky', 'Sonica', 'Tanto', 'Terios', 'Thor', 'Wake', 'YRV']],
            'Lexus' => ['code' => 'LX', 'country' => 'Japan', 'models' => ['CT', 'ES', 'GS', 'GX', 'HS', 'IS', 'LC', 'LFA', 'LM', 'LS', 'LX', 'NX', 'RC', 'RX', 'UX']],
            'Hino' => ['code' => 'HI', 'country' => 'Japan', 'models' => ['300 Series', '500 Series', '700 Series', 'Dutro', 'Liesse', 'Melpha', 'Profia', 'Ranger']],
            'Ford' => ['code' => 'FD', 'country' => 'United States', 'models' => ['EcoSport', 'Everest', 'Fiesta', 'Focus', 'Ranger', 'Transit']],
            'Volkswagen' => ['code' => 'VW', 'country' => 'Germany', 'models' => ['Amarok', 'Arteon', 'Caddy', 'Golf', 'Jetta', 'Passat', 'Polo', 'T-Cross', 'Tiguan', 'Touareg']],
            'Mercedes-Benz' => ['code' => 'MB', 'country' => 'Germany', 'models' => ['A-Class', 'B-Class', 'C-Class', 'CLA-Class', 'E-Class', 'GLA-Class', 'GLC-Class', 'GLE-Class', 'GLK-Class', 'M-Class', 'S-Class', 'Sprinter', 'Vito']],
            'BMW' => ['code' => 'BM', 'country' => 'Germany', 'models' => ['1 Series', '2 Series', '3 Series', '4 Series', '5 Series', '7 Series', 'X1', 'X3', 'X5', 'X6']],
            'Audi' => ['code' => 'AD', 'country' => 'Germany', 'models' => ['A1', 'A3', 'A4', 'A5', 'A6', 'Q3', 'Q5', 'Q7', 'TT']],
            'Opel' => ['code' => 'OP', 'country' => 'Germany', 'models' => ['Astra', 'Corsa', 'Insignia', 'Mokka', 'Zafira']],
            'Mini' => ['code' => 'MN', 'country' => 'United Kingdom', 'models' => ['Cooper', 'Countryman', 'Clubman']],
            'Porsche' => ['code' => 'PR', 'country' => 'Germany', 'models' => ['Cayenne', 'Macan', 'Panamera']],
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

        $existingFitments = [];

        DB::table('car_models')
            ->select(['car_make_id', 'vehicle_model_id', 'year', 'engine_size', 'variant_name', 'country_of_origin'])
            ->orderBy('id')
            ->cursor()
            ->each(function (object $fitment) use (&$existingFitments): void {
                $existingFitments[$this->fitmentKey(
                    (int) $fitment->car_make_id,
                    (int) $fitment->vehicle_model_id,
                    (int) $fitment->year,
                    (string) $fitment->engine_size,
                    (string) $fitment->variant_name,
                    (string) $fitment->country_of_origin
                )] = true;
            });
        $fitmentRows = [];
        $now = now();

        foreach ($this->fitments() as $fitment) {
            [$make, $model] = $makeModels["{$fitment['make']}|{$fitment['model']}"];
            $origin = $fitment['country_of_origin'] ?? $fitment['origin'] ?? null;
            $fitmentKey = $this->fitmentKey(
                (int) $make->id,
                (int) $model->id,
                (int) $fitment['year'],
                (string) $fitment['engine_size'],
                (string) $fitment['variant_name'],
                (string) $origin
            );

            if (isset($existingFitments[$fitmentKey])) {
                continue;
            }

            $existingFitments[$fitmentKey] = true;
            $fitmentRows[] = [
                'car_make_id' => $make->id,
                'vehicle_model_id' => $model->id,
                'make' => $make->name,
                'make_code' => $make->code,
                'model' => $model->name,
                'model_code' => $model->code,
                'year' => $fitment['year'],
                'engine_size' => $fitment['engine_size'],
                'variant_name' => $fitment['variant_name'],
                'country_of_origin' => $origin,
                'fitment_hash' => CarModel::fitmentHashFor(
                    $make->id,
                    $model->id,
                    $fitment['year'],
                    $fitment['engine_size'],
                    $fitment['variant_name'],
                    $origin
                ),
                'notes' => 'Common Malawi fitment variant seeded for catalogue compatibility.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($fitmentRows) >= 1000) {
                DB::table('car_models')->insert($fitmentRows);
                $fitmentRows = [];
            }
        }

        if ($fitmentRows !== []) {
            DB::table('car_models')->insert($fitmentRows);
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

    private function fitmentKey(int $makeId, int $modelId, int $year, string $engineSize, string $variantName, string $country): string
    {
        return implode('|', [
            $makeId,
            $modelId,
            $year,
            $engineSize,
            $variantName,
            $country,
        ]);
    }

    private function fitments(): array
    {
        return array_merge([
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
        ], $this->importedJapaneseFitments(), $this->importedEuropeanFitments(), $this->importedRegionalFitments());
    }

    private function importedJapaneseFitments(): array
    {
        $catalogue = [
            'Toyota' => [
                '4Runner' => [['SR5', '2700cc'], ['Limited', '4000cc']],
                '86' => [['GT', '2000cc'], ['GR86', '2400cc']],
                'Agya' => [['1.0', '1000cc'], ['1.2', '1200cc']],
                'Allex' => [['X', '1500cc'], ['RS180', '1800cc']],
                'Allion' => [['A15', '1500cc'], ['A18', '1800cc'], ['A20', '2000cc']],
                'Alphard' => [['2.4', '2400cc'], ['2.5', '2500cc'], ['3.0', '3000cc'], ['3.5', '3500cc'], ['Hybrid', '2500cc']],
                'Altezza' => [['AS200', '2000cc'], ['RS200', '2000cc']],
                'Aqua' => [['Hybrid', '1500cc'], ['Crossover', '1500cc']],
                'Aristo' => [['S300', '3000cc'], ['V300', '3000cc']],
                'Auris' => [['150X', '1500cc'], ['180G', '1800cc'], ['Hybrid', '1800cc']],
                'Avanza' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Avensis' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Axio' => [['X', '1500cc'], ['G', '1500cc'], ['Hybrid', '1500cc']],
                'bB' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Belta' => [['1.0', '1000cc'], ['1.3', '1300cc'], ['1.5', '1500cc']],
                'Brevis' => [['Ai250', '2500cc'], ['Ai300', '3000cc']],
                'Caldina' => [['1.8', '1800cc'], ['2.0', '2000cc'], ['GT-Four', '2000cc']],
                'Cami' => [['1.3', '1300cc']],
                'Camry' => [['2.4', '2400cc'], ['2.5', '2500cc'], ['Hybrid', '2500cc'], ['3.5 V6', '3500cc']],
                'Celica' => [['SS-I', '1800cc'], ['SS-II', '1800cc']],
                'C-HR' => [['1.2 Turbo', '1200cc'], ['Hybrid', '1800cc']],
                'Coaster' => [['Diesel Bus', '4000cc'], ['Diesel Bus', '4200cc']],
                'Comfort' => [['Taxi', '2000cc']],
                'Corolla' => [['X', '1500cc'], ['G', '1500cc'], ['Altis', '1800cc'], ['Hybrid', '1800cc']],
                'Corolla Cross' => [['1.8', '1800cc'], ['Hybrid', '1800cc'], ['2.0', '2000cc']],
                'Corolla Fielder' => [['X', '1500cc'], ['G', '1500cc'], ['S', '1800cc'], ['Hybrid', '1500cc']],
                'Corolla Rumion' => [['1.5G', '1500cc'], ['1.8S', '1800cc']],
                'Crown' => [['Royal Saloon', '2500cc'], ['Athlete', '2500cc'], ['Hybrid', '2500cc'], ['Majesta', '4300cc']],
                'Duet' => [['1.0', '1000cc'], ['1.3', '1300cc']],
                'Dyna' => [['Diesel Truck', '3000cc'], ['Diesel Truck', '4000cc']],
                'Echo' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Estima' => [['2.4', '2400cc'], ['3.0', '3000cc'], ['3.5', '3500cc'], ['Hybrid', '2400cc']],
                'FJ Cruiser' => [['4.0 V6', '4000cc']],
                'Fortuner' => [['2.7 Petrol', '2700cc'], ['2.8 Diesel', '2800cc'], ['3.0 Diesel', '3000cc']],
                'Gaia' => [['2.0', '2000cc']],
                'Granvia' => [['2.7', '2700cc'], ['3.4', '3400cc']],
                'Harrier' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['2.5 Hybrid', '2500cc'], ['3.0', '3000cc'], ['3.5', '3500cc']],
                'Hiace' => [['Petrol Van', '2000cc'], ['Diesel Van', '2500cc'], ['Diesel Van', '2800cc'], ['Diesel Van', '3000cc']],
                'Hilux' => [['2.4 Diesel', '2400cc'], ['2.5 Diesel', '2500cc'], ['2.8 Diesel', '2800cc'], ['3.0 Diesel', '3000cc']],
                'iQ' => [['1.0', '1000cc'], ['1.3', '1300cc']],
                'ISIS' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'ist' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Kluger' => [['2.4', '2400cc'], ['3.0', '3000cc'], ['3.5', '3500cc']],
                'Land Cruiser' => [['4.2 Diesel', '4200cc'], ['4.5 Diesel', '4500cc'], ['4.6 Petrol', '4600cc'], ['5.7 Petrol', '5700cc']],
                'Land Cruiser Prado' => [['2.7 Petrol', '2700cc'], ['2.8 Diesel', '2800cc'], ['3.0 Diesel', '3000cc'], ['4.0 Petrol', '4000cc']],
                'LiteAce' => [['Van', '1500cc'], ['Truck', '1500cc']],
                'Mark II' => [['Grande', '2000cc'], ['Grande', '2500cc']],
                'Mark X' => [['250G', '2500cc'], ['300G', '3000cc'], ['350S', '3500cc']],
                'Matrix' => [['1.8', '1800cc'], ['2.4', '2400cc']],
                'Nadia' => [['2.0', '2000cc']],
                'Noah' => [['2.0', '2000cc'], ['Hybrid', '1800cc']],
                'Opa' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Origin' => [['3.0', '3000cc']],
                'Passo' => [['1.0', '1000cc'], ['1.3', '1300cc']],
                'Platz' => [['1.0', '1000cc'], ['1.3', '1300cc'], ['1.5', '1500cc']],
                'Porte' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Premio' => [['1.5F', '1500cc'], ['1.8X', '1800cc'], ['2.0G', '2000cc']],
                'Prius' => [['Hybrid', '1500cc'], ['Hybrid', '1800cc'], ['PHEV', '1800cc'], ['Hybrid', '2000cc']],
                'Prius Alpha' => [['Hybrid', '1800cc']],
                'Probox' => [['Van', '1300cc'], ['Van', '1500cc'], ['Hybrid', '1500cc']],
                'Progres' => [['NC250', '2500cc'], ['NC300', '3000cc']],
                'Ractis' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Raum' => [['1.5', '1500cc']],
                'RAV4' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['2.5', '2500cc'], ['Hybrid', '2500cc']],
                'Regius' => [['2.7', '2700cc'], ['3.0 Diesel', '3000cc']],
                'Roomy' => [['1.0', '1000cc']],
                'Rush' => [['1.5', '1500cc']],
                'Sai' => [['Hybrid', '2400cc']],
                'Sequoia' => [['4.7', '4700cc'], ['5.7', '5700cc']],
                'Sienna' => [['3.3', '3300cc'], ['3.5', '3500cc'], ['Hybrid', '2500cc']],
                'Sienta' => [['1.5', '1500cc'], ['Hybrid', '1500cc']],
                'Soarer' => [['3.0', '3000cc'], ['4.3', '4300cc']],
                'Spacio' => [['1.5', '1500cc'], ['1.8', '1800cc']],
                'Starlet' => [['1.3', '1300cc']],
                'Succeed' => [['Van', '1500cc'], ['Hybrid', '1500cc']],
                'Supra' => [['RZ', '3000cc'], ['SZ', '2000cc']],
                'Tacoma' => [['2.7', '2700cc'], ['4.0', '4000cc']],
                'Tank' => [['1.0', '1000cc']],
                'TownAce' => [['Van', '1500cc'], ['Truck', '1500cc']],
                'Tundra' => [['4.7', '4700cc'], ['5.7', '5700cc']],
                'Vanguard' => [['2.4', '2400cc'], ['3.5', '3500cc']],
                'Vellfire' => [['2.4', '2400cc'], ['2.5', '2500cc'], ['3.5', '3500cc'], ['Hybrid', '2500cc']],
                'Verossa' => [['2.0', '2000cc'], ['2.5', '2500cc']],
                'Vios' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Vitz' => [['1.0', '1000cc'], ['1.3', '1300cc'], ['1.5', '1500cc'], ['RS', '1500cc']],
                'Voxy' => [['2.0', '2000cc'], ['Hybrid', '1800cc']],
                'Wish' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Yaris' => [['1.0', '1000cc'], ['1.3', '1300cc'], ['1.5', '1500cc'], ['Hybrid', '1500cc']],
                'Yaris Cross' => [['1.5', '1500cc'], ['Hybrid', '1500cc']],
            ],
            'Nissan' => [
                'AD Van' => [['1.5', '1500cc'], ['1.8', '1800cc']],
                'Almera' => [['1.5', '1500cc'], ['1.6', '1600cc']],
                'Atlas' => [['Diesel Truck', '3000cc'], ['Diesel Truck', '4200cc']],
                'Bluebird Sylphy' => [['1.5', '1500cc'], ['1.8', '1800cc'], ['2.0', '2000cc']],
                'Caravan' => [['2.0 Petrol', '2000cc'], ['2.5 Diesel', '2500cc'], ['3.0 Diesel', '3000cc']],
                'Cedric' => [['2.5', '2500cc'], ['3.0', '3000cc']],
                'Cefiro' => [['2.0', '2000cc'], ['2.5', '2500cc']],
                'Cima' => [['3.0', '3000cc'], ['4.5', '4500cc']],
                'Cube' => [['1.4', '1400cc'], ['1.5', '1500cc']],
                'Dualis' => [['2.0', '2000cc']],
                'Elgrand' => [['2.5', '2500cc'], ['3.5', '3500cc']],
                'Fairlady Z' => [['3.5', '3500cc'], ['3.7', '3700cc'], ['3.0 Turbo', '3000cc']],
                'Fuga' => [['2.5', '2500cc'], ['3.5', '3500cc'], ['3.7', '3700cc']],
                'GT-R' => [['VR38DETT', '3800cc']],
                'Juke' => [['1.5', '1500cc'], ['1.6 Turbo', '1600cc']],
                'Kicks' => [['1.2 e-Power', '1200cc'], ['1.5', '1500cc'], ['1.6', '1600cc']],
                'Lafesta' => [['2.0', '2000cc']],
                'Latio' => [['1.2', '1200cc'], ['1.5', '1500cc']],
                'Leaf' => [['EV', '0cc']],
                'Liberty' => [['2.0', '2000cc']],
                'March' => [['1.0', '1000cc'], ['1.2', '1200cc'], ['1.5', '1500cc']],
                'Maxima' => [['3.5', '3500cc']],
                'Micra' => [['1.0', '1000cc'], ['1.2', '1200cc'], ['1.5', '1500cc']],
                'Moco' => [['660', '660cc']],
                'Murano' => [['2.5', '2500cc'], ['3.5', '3500cc']],
                'Navara' => [['2.5 Diesel', '2500cc'], ['2.3 Diesel', '2300cc']],
                'Note' => [['1.2', '1200cc'], ['1.5', '1500cc'], ['e-Power', '1200cc']],
                'NV200' => [['1.6', '1600cc'], ['2.0', '2000cc']],
                'Pathfinder' => [['2.5 Diesel', '2500cc'], ['3.5', '3500cc'], ['4.0', '4000cc']],
                'Patrol' => [['4.2 Diesel', '4200cc'], ['4.8 Petrol', '4800cc'], ['5.6 Petrol', '5600cc']],
                'Primera' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Pulsar' => [['1.2 Turbo', '1200cc'], ['1.5', '1500cc'], ['1.6', '1600cc']],
                'Qashqai' => [['1.2 Turbo', '1200cc'], ['1.5 Diesel', '1500cc'], ['2.0', '2000cc']],
                'Rogue' => [['2.0', '2000cc'], ['2.5', '2500cc']],
                'Serena' => [['2.0', '2000cc'], ['Hybrid', '2000cc'], ['e-Power', '1200cc']],
                'Silvia' => [['S15', '2000cc']],
                'Skyline' => [['250GT', '2500cc'], ['350GT', '3500cc'], ['Hybrid', '3500cc']],
                'Stagea' => [['2.5', '2500cc']],
                'Sunny' => [['1.5', '1500cc'], ['1.6', '1600cc']],
                'Sylphy' => [['1.5', '1500cc'], ['1.8', '1800cc']],
                'Teana' => [['2.3', '2300cc'], ['2.5', '2500cc'], ['3.5', '3500cc']],
                'Terra' => [['2.5 Diesel', '2500cc']],
                'Tiida' => [['1.5', '1500cc'], ['1.6', '1600cc'], ['1.8', '1800cc']],
                'Vanette' => [['1.8', '1800cc'], ['2.0 Diesel', '2000cc']],
                'Versa' => [['1.5', '1500cc'], ['1.6', '1600cc']],
                'Wingroad' => [['1.5', '1500cc'], ['1.8', '1800cc']],
                'X-Trail' => [['2.0', '2000cc'], ['2.5', '2500cc'], ['Hybrid', '2000cc']],
            ],
            'Honda' => [
                'Accord' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['Hybrid', '2000cc']],
                'Airwave' => [['1.5', '1500cc']],
                'Avancier' => [['2.4', '2400cc'], ['1.5 Turbo', '1500cc']],
                'BR-V' => [['1.5', '1500cc']],
                'City' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Civic' => [['1.5', '1500cc'], ['1.8', '1800cc'], ['2.0', '2000cc'], ['Hybrid', '1300cc'], ['Type R', '2000cc']],
                'Clarity' => [['Hybrid', '1500cc'], ['Fuel Cell', '0cc']],
                'CR-V' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['1.5 Turbo', '1500cc'], ['Hybrid', '2000cc']],
                'CR-Z' => [['Hybrid', '1500cc']],
                'Crossroad' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Elysion' => [['2.4', '2400cc'], ['3.0', '3000cc'], ['3.5', '3500cc']],
                'Fit' => [['1.3', '1300cc'], ['1.5', '1500cc'], ['Hybrid', '1500cc']],
                'Fit Shuttle' => [['1.5', '1500cc'], ['Hybrid', '1300cc']],
                'Freed' => [['1.5', '1500cc'], ['Hybrid', '1500cc']],
                'Grace' => [['1.5', '1500cc'], ['Hybrid', '1500cc']],
                'HR-V' => [['1.5', '1500cc'], ['1.8', '1800cc']],
                'Insight' => [['Hybrid', '1300cc'], ['Hybrid', '1500cc']],
                'Jade' => [['1.5 Turbo', '1500cc'], ['Hybrid', '1500cc']],
                'Jazz' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Mobilio' => [['1.5', '1500cc']],
                'N-Box' => [['660', '660cc']],
                'N-One' => [['660', '660cc']],
                'Odyssey' => [['2.4', '2400cc'], ['Hybrid', '2000cc']],
                'Partner' => [['1.5', '1500cc']],
                'Ridgeline' => [['3.5', '3500cc']],
                'Shuttle' => [['1.5', '1500cc'], ['Hybrid', '1500cc']],
                'Stepwgn' => [['2.0', '2000cc'], ['1.5 Turbo', '1500cc'], ['Hybrid', '2000cc']],
                'Stream' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Vezel' => [['1.5', '1500cc'], ['Hybrid', '1500cc']],
                'Zest' => [['660', '660cc']],
            ],
            'Mazda' => [
                '2' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                '3' => [['1.5', '1500cc'], ['2.0', '2000cc'], ['2.5', '2500cc']],
                '5' => [['2.0', '2000cc']],
                '6' => [['2.0', '2000cc'], ['2.5', '2500cc']],
                'Atenza' => [['2.0', '2000cc'], ['2.3', '2300cc'], ['2.5', '2500cc'], ['2.2 Diesel', '2200cc']],
                'Axela' => [['1.5', '1500cc'], ['2.0', '2000cc'], ['2.2 Diesel', '2200cc']],
                'Biante' => [['2.0', '2000cc']],
                'Bongo' => [['1.8', '1800cc'], ['2.0 Diesel', '2000cc']],
                'BT-50' => [['2.2 Diesel', '2200cc'], ['3.2 Diesel', '3200cc']],
                'Carol' => [['660', '660cc']],
                'CX-3' => [['1.5 Diesel', '1500cc'], ['2.0', '2000cc']],
                'CX-30' => [['2.0', '2000cc'], ['2.5', '2500cc']],
                'CX-5' => [['2.0', '2000cc'], ['2.2 Diesel', '2200cc'], ['2.5', '2500cc']],
                'CX-7' => [['2.3 Turbo', '2300cc']],
                'CX-8' => [['2.2 Diesel', '2200cc'], ['2.5', '2500cc']],
                'CX-9' => [['2.5 Turbo', '2500cc'], ['3.7', '3700cc']],
                'Demio' => [['1.3', '1300cc'], ['1.5', '1500cc'], ['1.5 Diesel', '1500cc']],
                'Familia Van' => [['1.5', '1500cc']],
                'Flair' => [['660', '660cc']],
                'MPV' => [['2.3', '2300cc'], ['2.5', '2500cc']],
                'Premacy' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Roadster' => [['1.5', '1500cc'], ['2.0', '2000cc']],
                'RX-8' => [['Rotary', '1300cc']],
                'Tribute' => [['2.3', '2300cc'], ['3.0', '3000cc']],
                'Verisa' => [['1.5', '1500cc']],
            ],
            'Mitsubishi' => [
                'Airtrek' => [['2.0', '2000cc'], ['2.4', '2400cc']],
                'ASX' => [['2.0', '2000cc']],
                'Canter' => [['Diesel Truck', '3000cc'], ['Diesel Truck', '4900cc']],
                'Colt' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Delica' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['2.5 Diesel', '2500cc'], ['2.8 Diesel', '2800cc']],
                'Dion' => [['2.0', '2000cc']],
                'eK' => [['660', '660cc']],
                'eK Space' => [['660', '660cc']],
                'Eclipse Cross' => [['1.5 Turbo', '1500cc'], ['PHEV', '2400cc']],
                'Fuso Fighter' => [['Diesel Truck', '7500cc']],
                'Galant' => [['2.0', '2000cc'], ['2.4', '2400cc']],
                'Grandis' => [['2.4', '2400cc']],
                'i-MiEV' => [['EV', '0cc']],
                'L200' => [['2.5 Diesel', '2500cc'], ['2.4 Diesel', '2400cc']],
                'Lancer' => [['1.5', '1500cc'], ['1.8', '1800cc'], ['Evolution', '2000cc']],
                'Mirage' => [['1.0', '1000cc'], ['1.2', '1200cc']],
                'Montero' => [['3.2 Diesel', '3200cc'], ['3.8 Petrol', '3800cc']],
                'Outlander' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['PHEV', '2400cc']],
                'Pajero' => [['3.2 Diesel', '3200cc'], ['3.5 Petrol', '3500cc'], ['3.8 Petrol', '3800cc']],
                'Pajero IO' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Pajero Mini' => [['660', '660cc']],
                'RVR' => [['1.8', '1800cc'], ['2.0', '2000cc']],
                'Triton' => [['2.5 Diesel', '2500cc'], ['2.4 Diesel', '2400cc']],
                'Xpander' => [['1.5', '1500cc']],
            ],
            'Subaru' => [
                'Baja' => [['2.5', '2500cc']],
                'BRZ' => [['2.0', '2000cc'], ['2.4', '2400cc']],
                'Chiffon' => [['660', '660cc']],
                'Dex' => [['1.3', '1300cc']],
                'Exiga' => [['2.0', '2000cc'], ['2.5', '2500cc']],
                'Forester' => [['2.0', '2000cc'], ['2.5', '2500cc'], ['XT Turbo', '2000cc']],
                'Impreza' => [['1.5', '1500cc'], ['1.6', '1600cc'], ['2.0', '2000cc'], ['WRX', '2000cc']],
                'Justy' => [['1.0', '1000cc'], ['1.2', '1200cc']],
                'Legacy' => [['2.0', '2000cc'], ['2.5', '2500cc'], ['3.0', '3000cc']],
                'Levorg' => [['1.6 Turbo', '1600cc'], ['2.0 Turbo', '2000cc'], ['1.8 Turbo', '1800cc']],
                'Outback' => [['2.5', '2500cc'], ['3.6', '3600cc']],
                'Pleo' => [['660', '660cc']],
                'R1' => [['660', '660cc']],
                'R2' => [['660', '660cc']],
                'Sambar' => [['660', '660cc']],
                'Stella' => [['660', '660cc']],
                'Trezia' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'WRX' => [['2.0 Turbo', '2000cc'], ['2.5 Turbo', '2500cc']],
                'XV' => [['1.6', '1600cc'], ['2.0', '2000cc'], ['Hybrid', '2000cc']],
            ],
            'Suzuki' => [
                'Aerio' => [['1.5', '1500cc'], ['1.8', '1800cc']],
                'Alto' => [['660', '660cc'], ['800', '800cc']],
                'Baleno' => [['1.4', '1400cc'], ['1.5', '1500cc']],
                'Carry' => [['660', '660cc'], ['1.5', '1500cc']],
                'Celerio' => [['1.0', '1000cc']],
                'Ciaz' => [['1.4', '1400cc'], ['1.5', '1500cc']],
                'Ertiga' => [['1.4', '1400cc'], ['1.5', '1500cc']],
                'Escudo' => [['1.6', '1600cc'], ['2.0', '2000cc'], ['2.4', '2400cc']],
                'Every' => [['660', '660cc']],
                'Grand Vitara' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['2.7', '2700cc']],
                'Hustler' => [['660', '660cc']],
                'Ignis' => [['1.2', '1200cc']],
                'Jimny' => [['660', '660cc'], ['1.3', '1300cc'], ['1.5', '1500cc']],
                'Kei' => [['660', '660cc']],
                'Liana' => [['1.6', '1600cc']],
                'MR Wagon' => [['660', '660cc']],
                'Palette' => [['660', '660cc']],
                'Solio' => [['1.2', '1200cc']],
                'Spacia' => [['660', '660cc']],
                'Splash' => [['1.2', '1200cc']],
                'Swift' => [['1.2', '1200cc'], ['1.3', '1300cc'], ['1.5', '1500cc'], ['Sport', '1600cc']],
                'SX4' => [['1.5', '1500cc'], ['1.6', '1600cc'], ['2.0', '2000cc']],
                'Vitara' => [['1.4 Turbo', '1400cc'], ['1.6', '1600cc']],
                'Wagon R' => [['660', '660cc']],
                'Xbee' => [['1.0 Turbo', '1000cc']],
            ],
            'Isuzu' => [
                'Bighorn' => [['3.0 Diesel', '3000cc'], ['3.5 Petrol', '3500cc']],
                'D-Max' => [['2.5 Diesel', '2500cc'], ['3.0 Diesel', '3000cc'], ['1.9 Diesel', '1900cc']],
                'Elf' => [['Diesel Truck', '3000cc'], ['Diesel Truck', '5200cc']],
                'F-Series' => [['Diesel Truck', '5200cc'], ['Diesel Truck', '7800cc']],
                'Forward' => [['Diesel Truck', '5200cc'], ['Diesel Truck', '7800cc']],
                'Giga' => [['Diesel Truck', '9800cc'], ['Diesel Truck', '15600cc']],
                'Journey' => [['Diesel Bus', '5200cc']],
                'MU-7' => [['3.0 Diesel', '3000cc']],
                'MU-X' => [['2.5 Diesel', '2500cc'], ['3.0 Diesel', '3000cc'], ['1.9 Diesel', '1900cc']],
                'N-Series' => [['Diesel Truck', '3000cc'], ['Diesel Truck', '5200cc']],
                'Rodeo' => [['2.5 Diesel', '2500cc'], ['3.0 Diesel', '3000cc']],
                'Trooper' => [['3.0 Diesel', '3000cc'], ['3.5 Petrol', '3500cc']],
                'Wizard' => [['3.0 Diesel', '3000cc'], ['3.2 Petrol', '3200cc']],
            ],
            'Daihatsu' => [
                'Altis' => [['2.4', '2400cc'], ['2.5 Hybrid', '2500cc']],
                'Be-Go' => [['1.5', '1500cc']],
                'Boon' => [['1.0', '1000cc'], ['1.3', '1300cc']],
                'Copen' => [['660', '660cc']],
                'Esse' => [['660', '660cc']],
                'Hijet' => [['660', '660cc']],
                'Mira' => [['660', '660cc']],
                'Mira e:S' => [['660', '660cc']],
                'Move' => [['660', '660cc']],
                'Naked' => [['660', '660cc']],
                'Rocky' => [['1.0 Turbo', '1000cc'], ['1.2', '1200cc']],
                'Sonica' => [['660', '660cc']],
                'Tanto' => [['660', '660cc']],
                'Terios' => [['1.3', '1300cc'], ['1.5', '1500cc']],
                'Thor' => [['1.0', '1000cc']],
                'Wake' => [['660', '660cc']],
                'YRV' => [['1.3', '1300cc']],
            ],
            'Lexus' => [
                'CT' => [['CT200h', '1800cc']],
                'ES' => [['ES250', '2500cc'], ['ES300h', '2500cc'], ['ES350', '3500cc']],
                'GS' => [['GS250', '2500cc'], ['GS300', '3000cc'], ['GS350', '3500cc'], ['GS450h', '3500cc']],
                'GX' => [['GX460', '4600cc'], ['GX550', '3500cc']],
                'HS' => [['HS250h', '2400cc']],
                'IS' => [['IS200', '2000cc'], ['IS250', '2500cc'], ['IS300', '2000cc'], ['IS350', '3500cc']],
                'LC' => [['LC500', '5000cc'], ['LC500h', '3500cc']],
                'LFA' => [['V10', '4800cc']],
                'LM' => [['LM350', '3500cc'], ['LM500h', '2400cc']],
                'LS' => [['LS460', '4600cc'], ['LS500', '3500cc'], ['LS600h', '5000cc']],
                'LX' => [['LX470', '4700cc'], ['LX570', '5700cc'], ['LX600', '3500cc']],
                'NX' => [['NX200t', '2000cc'], ['NX250', '2500cc'], ['NX300h', '2500cc'], ['NX350', '2400cc']],
                'RC' => [['RC200t', '2000cc'], ['RC300', '2000cc'], ['RC350', '3500cc']],
                'RX' => [['RX300', '3000cc'], ['RX330', '3300cc'], ['RX350', '3500cc'], ['RX450h', '3500cc'], ['RX500h', '2400cc']],
                'UX' => [['UX200', '2000cc'], ['UX250h', '2000cc'], ['UX300e', '0cc']],
            ],
            'Hino' => [
                '300 Series' => [['Light Truck', '4000cc'], ['Light Truck', '5300cc']],
                '500 Series' => [['Medium Truck', '5100cc'], ['Medium Truck', '7700cc']],
                '700 Series' => [['Heavy Truck', '8860cc'], ['Heavy Truck', '12900cc']],
                'Dutro' => [['Light Truck', '4000cc']],
                'Liesse' => [['Bus', '4000cc'], ['Bus', '5300cc']],
                'Melpha' => [['Bus', '6400cc']],
                'Profia' => [['Heavy Truck', '12900cc']],
                'Ranger' => [['Medium Truck', '5100cc'], ['Medium Truck', '7700cc']],
            ],
        ];

        return $this->expandFitmentCatalogue($catalogue, ['Japan', 'Singapore', 'South Africa', 'United Kingdom']);
    }

    private function importedEuropeanFitments(): array
    {
        $catalogue = [
            'Mercedes-Benz' => [
                'A-Class' => [['A180', '1600cc'], ['A200', '1600cc'], ['A250', '2000cc'], ['A45 AMG', '2000cc']],
                'B-Class' => [['B180', '1600cc'], ['B200', '1600cc'], ['B220d', '2000cc']],
                'C-Class' => [['C180', '1600cc'], ['C200', '1800cc'], ['C220d', '2200cc'], ['C250', '1800cc'], ['C300', '2000cc'], ['C350e', '2000cc'], ['AMG C43', '3000cc'], ['AMG C63', '4000cc']],
                'CLA-Class' => [['CLA180', '1600cc'], ['CLA200', '1600cc'], ['CLA250', '2000cc']],
                'E-Class' => [['E200', '2000cc'], ['E220d', '2200cc'], ['E250', '1800cc'], ['E300', '2000cc'], ['E350', '3500cc'], ['E400', '3000cc'], ['AMG E43', '3000cc']],
                'GLA-Class' => [['GLA180', '1600cc'], ['GLA200', '1600cc'], ['GLA220d', '2100cc'], ['GLA250', '2000cc']],
                'GLC-Class' => [['GLC200', '2000cc'], ['GLC220d', '2100cc'], ['GLC250', '2000cc'], ['GLC300', '2000cc']],
                'GLE-Class' => [['GLE250d', '2100cc'], ['GLE350d', '3000cc'], ['GLE400', '3000cc'], ['GLE450', '3000cc']],
                'GLK-Class' => [['GLK250 CDI', '2100cc'], ['GLK350', '3500cc']],
                'M-Class' => [['ML250', '2100cc'], ['ML350', '3500cc']],
                'S-Class' => [['S350', '3500cc'], ['S400', '3500cc'], ['S500', '4700cc'], ['S560', '4000cc']],
                'Sprinter' => [['313 CDI', '2200cc'], ['316 CDI', '2200cc'], ['319 CDI', '3000cc']],
                'Vito' => [['111 CDI', '1600cc'], ['114 CDI', '2100cc'], ['116 CDI', '2100cc']],
            ],
            'BMW' => [
                '1 Series' => [['116i', '1600cc'], ['118i', '1500cc'], ['120i', '2000cc'], ['120d', '2000cc'], ['M135i', '3000cc']],
                '2 Series' => [['218i', '1500cc'], ['220i', '2000cc'], ['220d', '2000cc']],
                '3 Series' => [['316i', '1600cc'], ['318i', '1500cc'], ['320i', '2000cc'], ['320d', '2000cc'], ['328i', '2000cc'], ['330i', '2000cc'], ['330e', '2000cc'], ['335i', '3000cc'], ['M340i', '3000cc']],
                '4 Series' => [['420i', '2000cc'], ['420d', '2000cc'], ['430i', '2000cc'], ['435i', '3000cc']],
                '5 Series' => [['520i', '2000cc'], ['520d', '2000cc'], ['528i', '2000cc'], ['530i', '2000cc'], ['530d', '3000cc'], ['535i', '3000cc']],
                '7 Series' => [['730d', '3000cc'], ['740i', '3000cc'], ['750i', '4400cc']],
                'X1' => [['sDrive18i', '1500cc'], ['sDrive20i', '2000cc'], ['xDrive20d', '2000cc']],
                'X3' => [['xDrive20i', '2000cc'], ['xDrive20d', '2000cc'], ['xDrive30d', '3000cc'], ['xDrive35i', '3000cc']],
                'X5' => [['xDrive30d', '3000cc'], ['xDrive35i', '3000cc'], ['xDrive40i', '3000cc'], ['M50d', '3000cc']],
                'X6' => [['xDrive30d', '3000cc'], ['xDrive35i', '3000cc'], ['xDrive40i', '3000cc']],
            ],
            'Volkswagen' => [
                'Polo' => [['1.0 TSI', '1000cc'], ['1.2 TSI', '1200cc'], ['1.4', '1400cc'], ['1.6', '1600cc'], ['GTI', '2000cc']],
                'Golf' => [['1.2 TSI', '1200cc'], ['1.4 TSI', '1400cc'], ['1.6 TDI', '1600cc'], ['2.0 TDI', '2000cc'], ['GTI', '2000cc']],
                'Jetta' => [['1.4 TSI', '1400cc'], ['1.6', '1600cc'], ['2.0 TDI', '2000cc']],
                'Passat' => [['1.8 TSI', '1800cc'], ['2.0 TDI', '2000cc'], ['2.0 TSI', '2000cc']],
                'Tiguan' => [['1.4 TSI', '1400cc'], ['2.0 TDI', '2000cc'], ['2.0 TSI', '2000cc']],
                'Touareg' => [['3.0 TDI', '3000cc'], ['3.6 FSI', '3600cc']],
                'Amarok' => [['2.0 BiTDI', '2000cc'], ['3.0 V6 TDI', '3000cc']],
                'Caddy' => [['1.6 TDI', '1600cc'], ['2.0 TDI', '2000cc']],
                'T-Cross' => [['1.0 TSI', '1000cc'], ['1.5 TSI', '1500cc']],
                'Arteon' => [['2.0 TSI', '2000cc'], ['2.0 TDI', '2000cc']],
            ],
            'Audi' => [
                'A1' => [['1.0 TFSI', '1000cc'], ['1.4 TFSI', '1400cc']],
                'A3' => [['1.4 TFSI', '1400cc'], ['1.8 TFSI', '1800cc'], ['2.0 TDI', '2000cc']],
                'A4' => [['1.8 TFSI', '1800cc'], ['2.0 TFSI', '2000cc'], ['2.0 TDI', '2000cc']],
                'A5' => [['2.0 TFSI', '2000cc'], ['2.0 TDI', '2000cc']],
                'A6' => [['2.0 TFSI', '2000cc'], ['2.0 TDI', '2000cc'], ['3.0 TDI', '3000cc']],
                'Q3' => [['1.4 TFSI', '1400cc'], ['2.0 TFSI', '2000cc'], ['2.0 TDI', '2000cc']],
                'Q5' => [['2.0 TFSI', '2000cc'], ['2.0 TDI', '2000cc'], ['3.0 TDI', '3000cc']],
                'Q7' => [['3.0 TDI', '3000cc'], ['3.0 TFSI', '3000cc']],
                'TT' => [['2.0 TFSI', '2000cc']],
            ],
            'Opel' => [
                'Astra' => [['1.4 Turbo', '1400cc'], ['1.6', '1600cc'], ['1.6 Turbo', '1600cc']],
                'Corsa' => [['1.0 Turbo', '1000cc'], ['1.2', '1200cc'], ['1.4', '1400cc']],
                'Insignia' => [['1.6 Turbo', '1600cc'], ['2.0 CDTI', '2000cc']],
                'Mokka' => [['1.4 Turbo', '1400cc'], ['1.6 CDTI', '1600cc']],
                'Zafira' => [['1.8', '1800cc'], ['2.0 CDTI', '2000cc']],
            ],
            'Mini' => [
                'Cooper' => [['Cooper', '1500cc'], ['Cooper S', '2000cc'], ['One', '1200cc']],
                'Countryman' => [['Cooper', '1500cc'], ['Cooper S', '2000cc'], ['Cooper D', '2000cc']],
                'Clubman' => [['Cooper', '1500cc'], ['Cooper S', '2000cc']],
            ],
            'Porsche' => [
                'Cayenne' => [['V6', '3000cc'], ['Diesel', '3000cc'], ['S', '3600cc']],
                'Macan' => [['2.0', '2000cc'], ['S', '3000cc'], ['GTS', '3000cc']],
                'Panamera' => [['V6', '3000cc'], ['4S', '2900cc'], ['Turbo', '4000cc']],
            ],
            'Land Rover' => [
                'Defender' => [['110 TD5', '2500cc'], ['110 D240', '2000cc'], ['110 D300', '3000cc']],
                'Discovery' => [['TDV6', '3000cc'], ['SDV6', '3000cc'], ['D300', '3000cc']],
                'Freelander' => [['TD4', '2200cc'], ['Si4', '2000cc']],
                'Range Rover' => [['TDV6', '3000cc'], ['SDV8', '4400cc'], ['P400', '3000cc']],
                'Range Rover Sport' => [['TDV6', '3000cc'], ['SDV6', '3000cc'], ['P400', '3000cc']],
            ],
        ];

        return $this->expandFitmentCatalogue($catalogue, ['South Africa', 'Singapore', 'Germany', 'Japan', 'United Kingdom']);
    }

    private function importedRegionalFitments(): array
    {
        return array_merge(
            $this->expandFitmentCatalogue([
                'Ford' => [
                    'EcoSport' => [['1.0 EcoBoost', '1000cc'], ['1.5 Ti-VCT', '1500cc'], ['1.5 TDCi', '1500cc']],
                    'Everest' => [['2.2 TDCi', '2200cc'], ['3.2 TDCi', '3200cc'], ['2.0 Bi-Turbo', '2000cc']],
                    'Fiesta' => [['1.0 EcoBoost', '1000cc'], ['1.4', '1400cc'], ['1.6', '1600cc'], ['ST', '1600cc']],
                    'Focus' => [['1.0 EcoBoost', '1000cc'], ['1.6', '1600cc'], ['2.0', '2000cc'], ['ST', '2000cc']],
                    'Ranger' => [['2.2 TDCi', '2200cc'], ['2.5 Petrol', '2500cc'], ['3.2 TDCi', '3200cc'], ['2.0 Bi-Turbo', '2000cc']],
                    'Transit' => [['2.2 TDCi', '2200cc'], ['2.0 EcoBlue', '2000cc'], ['2.4 TDCi', '2400cc']],
                ],
            ], ['South Africa', 'United Kingdom', 'Japan']),
            $this->expandFitmentCatalogue([
                'Hyundai' => [
                    'Accent' => [['1.4', '1400cc'], ['1.6', '1600cc']],
                    'Elantra' => [['1.6', '1600cc'], ['1.8', '1800cc'], ['2.0', '2000cc']],
                    'H-1' => [['2.4 Petrol', '2400cc'], ['2.5 CRDi', '2500cc']],
                    'i10' => [['1.0', '1000cc'], ['1.1', '1100cc'], ['1.2', '1200cc']],
                    'i20' => [['1.2', '1200cc'], ['1.4', '1400cc'], ['1.6', '1600cc']],
                    'i30' => [['1.6', '1600cc'], ['2.0', '2000cc']],
                    'Santa Fe' => [['2.2 CRDi', '2200cc'], ['2.4 Petrol', '2400cc'], ['3.5 V6', '3500cc']],
                    'Sonata' => [['2.0', '2000cc'], ['2.4', '2400cc'], ['Hybrid', '2000cc']],
                    'Tucson' => [['1.6 Turbo', '1600cc'], ['2.0 Petrol', '2000cc'], ['2.0 CRDi', '2000cc']],
                ],
                'Kia' => [
                    'Cerato' => [['1.6', '1600cc'], ['2.0', '2000cc']],
                    'K2700' => [['2.7 Diesel', '2700cc']],
                    'Morning' => [['1.0', '1000cc'], ['1.2', '1200cc']],
                    'Picanto' => [['1.0', '1000cc'], ['1.2', '1200cc']],
                    'Rio' => [['1.4', '1400cc'], ['1.6', '1600cc']],
                    'Sorento' => [['2.2 CRDi', '2200cc'], ['2.4 Petrol', '2400cc'], ['3.5 V6', '3500cc']],
                    'Sportage' => [['1.6 Turbo', '1600cc'], ['2.0 Petrol', '2000cc'], ['2.0 CRDi', '2000cc']],
                ],
            ], ['South Korea', 'South Africa', 'Japan', 'Singapore']),
            $this->expandFitmentCatalogue([
                'Chevrolet' => [
                    'Aveo' => [['1.4', '1400cc'], ['1.6', '1600cc']],
                    'Captiva' => [['2.0 Diesel', '2000cc'], ['2.4 Petrol', '2400cc'], ['3.0 V6', '3000cc']],
                    'Colorado' => [['2.5 Diesel', '2500cc'], ['2.8 Duramax', '2800cc']],
                    'Cruze' => [['1.6', '1600cc'], ['1.8', '1800cc'], ['2.0 Diesel', '2000cc']],
                    'Spark' => [['1.0', '1000cc'], ['1.2', '1200cc']],
                    'Trailblazer' => [['2.5 Diesel', '2500cc'], ['2.8 Duramax', '2800cc']],
                ],
                'Jeep' => [
                    'Cherokee' => [['2.4', '2400cc'], ['3.2 V6', '3200cc'], ['2.8 CRD', '2800cc']],
                    'Compass' => [['2.0', '2000cc'], ['2.4', '2400cc']],
                    'Grand Cherokee' => [['3.0 CRD', '3000cc'], ['3.6 V6', '3600cc'], ['5.7 Hemi', '5700cc']],
                    'Renegade' => [['1.4 Turbo', '1400cc'], ['2.4', '2400cc']],
                    'Wrangler' => [['2.8 CRD', '2800cc'], ['3.6 V6', '3600cc'], ['2.0 Turbo', '2000cc']],
                ],
            ], ['South Africa', 'United States', 'Japan']),
            $this->expandFitmentCatalogue([
                'Peugeot' => [
                    '206' => [['1.4', '1400cc'], ['1.6', '1600cc'], ['2.0 GTi', '2000cc']],
                    '207' => [['1.4', '1400cc'], ['1.6', '1600cc'], ['1.6 HDi', '1600cc']],
                    '208' => [['1.2 PureTech', '1200cc'], ['1.6', '1600cc'], ['1.6 HDi', '1600cc']],
                    '307' => [['1.6', '1600cc'], ['2.0', '2000cc'], ['2.0 HDi', '2000cc']],
                    '308' => [['1.2 PureTech', '1200cc'], ['1.6', '1600cc'], ['2.0 HDi', '2000cc']],
                    '3008' => [['1.6 THP', '1600cc'], ['2.0 HDi', '2000cc'], ['Hybrid', '1600cc']],
                    'Partner' => [['1.6 Petrol', '1600cc'], ['1.6 HDi', '1600cc']],
                ],
                'Renault' => [
                    'Clio' => [['0.9 TCe', '900cc'], ['1.2', '1200cc'], ['1.5 dCi', '1500cc']],
                    'Duster' => [['1.5 dCi', '1500cc'], ['1.6 Petrol', '1600cc'], ['2.0 Petrol', '2000cc']],
                    'Kangoo' => [['1.5 dCi', '1500cc'], ['1.6 Petrol', '1600cc']],
                    'Koleos' => [['2.0 dCi', '2000cc'], ['2.5 Petrol', '2500cc']],
                    'Megane' => [['1.4 TCe', '1400cc'], ['1.6', '1600cc'], ['1.5 dCi', '1500cc']],
                    'Sandero' => [['0.9 TCe', '900cc'], ['1.4', '1400cc'], ['1.6', '1600cc']],
                ],
            ], ['France', 'South Africa', 'United Kingdom', 'Japan']),
            $this->expandFitmentCatalogue([
                'Mahindra' => [
                    'Bolero' => [['2.5 Diesel', '2500cc'], ['mHawk', '2200cc']],
                    'Pik Up' => [['2.2 mHawk', '2200cc'], ['2.5 CRDe', '2500cc']],
                    'Scorpio' => [['2.2 mHawk', '2200cc'], ['2.6 CRDe', '2600cc']],
                    'XUV500' => [['2.2 mHawk', '2200cc']],
                ],
                'Tata' => [
                    'Indica' => [['1.4 Petrol', '1400cc'], ['1.4 Diesel', '1400cc']],
                    'Indigo' => [['1.4 Petrol', '1400cc'], ['1.4 Diesel', '1400cc']],
                    'Super Ace' => [['1.4 Diesel', '1400cc']],
                    'Xenon' => [['2.2 Dicor', '2200cc'], ['3.0 Dicor', '3000cc']],
                ],
            ], ['India', 'South Africa']),
            $this->expandFitmentCatalogue([
                'GWM' => [
                    'C20R' => [['1.5', '1500cc']],
                    'H5' => [['2.0', '2000cc'], ['2.4 Petrol', '2400cc']],
                    'M4' => [['1.5', '1500cc']],
                    'P-Series' => [['2.0 Turbo Diesel', '2000cc'], ['2.0 Turbo Petrol', '2000cc']],
                    'Steed' => [['2.0 VGT', '2000cc'], ['2.2 Petrol', '2200cc'], ['2.8 TCi', '2800cc']],
                ],
                'Haval' => [
                    'H1' => [['1.5', '1500cc']],
                    'H2' => [['1.5 Turbo', '1500cc']],
                    'H6' => [['1.5 Turbo', '1500cc'], ['2.0 Turbo', '2000cc']],
                    'Jolion' => [['1.5 Turbo', '1500cc'], ['Hybrid', '1500cc']],
                ],
                'Chery' => [
                    'Arrizo' => [['1.5', '1500cc'], ['1.5 Turbo', '1500cc']],
                    'QQ' => [['0.8', '800cc'], ['1.0', '1000cc']],
                    'Tiggo 2' => [['1.5', '1500cc']],
                    'Tiggo 4' => [['1.5', '1500cc'], ['1.5 Turbo', '1500cc']],
                    'Tiggo 7' => [['1.5 Turbo', '1500cc'], ['1.6 Turbo', '1600cc']],
                ],
            ], ['China', 'South Africa'])
        );
    }

    private function expandFitmentCatalogue(array $catalogue, array $origins): array
    {
        $fitments = [];

        foreach ($catalogue as $make => $models) {
            foreach ($models as $model => $variants) {
                foreach ($variants as [$variant, $engineSize]) {
                    foreach ($this->modelYears() as $year) {
                        foreach ($origins as $origin) {
                            $fitments[] = [
                                'make' => $make,
                                'model' => $model,
                                'year' => $year,
                                'engine_size' => $engineSize,
                                'variant_name' => $variant,
                                'country_of_origin' => $origin,
                            ];
                        }
                    }
                }
            }
        }

        return $fitments;
    }

    private function modelYears(): array
    {
        return range(2000, 2026);
    }
}
