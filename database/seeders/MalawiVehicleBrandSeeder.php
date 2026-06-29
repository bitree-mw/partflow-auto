<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class MalawiVehicleBrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Toyota', 'code' => 'TOY', 'country' => 'Japan'],
            ['name' => 'Nissan', 'code' => 'NIS', 'country' => 'Japan'],
            ['name' => 'Honda', 'code' => 'HON', 'country' => 'Japan'],
            ['name' => 'Mazda', 'code' => 'MAZ', 'country' => 'Japan'],
            ['name' => 'Mitsubishi', 'code' => 'MIT', 'country' => 'Japan'],
            ['name' => 'Subaru', 'code' => 'SUB', 'country' => 'Japan'],
            ['name' => 'Suzuki', 'code' => 'SUZ', 'country' => 'Japan'],
            ['name' => 'Isuzu', 'code' => 'ISU', 'country' => 'Japan'],
            ['name' => 'Daihatsu', 'code' => 'DAI', 'country' => 'Japan'],
            ['name' => 'Lexus', 'code' => 'LEX', 'country' => 'Japan'],
            ['name' => 'Hino', 'code' => 'HIN', 'country' => 'Japan'],
            ['name' => 'UD Trucks', 'code' => 'UDT', 'country' => 'Japan'],
            ['name' => 'Volkswagen', 'code' => 'VWG', 'country' => 'Germany'],
            ['name' => 'Mercedes-Benz', 'code' => 'MBZ', 'country' => 'Germany'],
            ['name' => 'BMW', 'code' => 'BMW', 'country' => 'Germany'],
            ['name' => 'Audi', 'code' => 'AUD', 'country' => 'Germany'],
            ['name' => 'Opel', 'code' => 'OPE', 'country' => 'Germany'],
            ['name' => 'Porsche', 'code' => 'POR', 'country' => 'Germany'],
            ['name' => 'Ford', 'code' => 'FOR', 'country' => 'United States'],
            ['name' => 'Chevrolet', 'code' => 'CHE', 'country' => 'United States'],
            ['name' => 'Jeep', 'code' => 'JEP', 'country' => 'United States'],
            ['name' => 'Dodge', 'code' => 'DOD', 'country' => 'United States'],
            ['name' => 'Chrysler', 'code' => 'CHR', 'country' => 'United States'],
            ['name' => 'Hyundai', 'code' => 'HYU', 'country' => 'South Korea'],
            ['name' => 'Kia', 'code' => 'KIA', 'country' => 'South Korea'],
            ['name' => 'Daewoo', 'code' => 'DAE', 'country' => 'South Korea'],
            ['name' => 'SsangYong', 'code' => 'SSY', 'country' => 'South Korea'],
            ['name' => 'Renault', 'code' => 'REN', 'country' => 'France'],
            ['name' => 'Peugeot', 'code' => 'PEU', 'country' => 'France'],
            ['name' => 'Citroen', 'code' => 'CIT', 'country' => 'France'],
            ['name' => 'Fiat', 'code' => 'FIA', 'country' => 'Italy'],
            ['name' => 'Alfa Romeo', 'code' => 'ALF', 'country' => 'Italy'],
            ['name' => 'Land Rover', 'code' => 'LRV', 'country' => 'United Kingdom'],
            ['name' => 'Range Rover', 'code' => 'RRO', 'country' => 'United Kingdom'],
            ['name' => 'Jaguar', 'code' => 'JAG', 'country' => 'United Kingdom'],
            ['name' => 'Mini', 'code' => 'MIN', 'country' => 'United Kingdom'],
            ['name' => 'Volvo', 'code' => 'VOL', 'country' => 'Sweden'],
            ['name' => 'Scania', 'code' => 'SCA', 'country' => 'Sweden'],
            ['name' => 'Iveco', 'code' => 'IVE', 'country' => 'Italy'],
            ['name' => 'MAN', 'code' => 'MAN', 'country' => 'Germany'],
            ['name' => 'Mahindra', 'code' => 'MAH', 'country' => 'India'],
            ['name' => 'Tata', 'code' => 'TAT', 'country' => 'India'],
            ['name' => 'Ashok Leyland', 'code' => 'ASH', 'country' => 'India'],
            ['name' => 'GWM', 'code' => 'GWM', 'country' => 'China'],
            ['name' => 'Haval', 'code' => 'HAV', 'country' => 'China'],
            ['name' => 'Chery', 'code' => 'CHY', 'country' => 'China'],
            ['name' => 'JAC', 'code' => 'JAC', 'country' => 'China'],
            ['name' => 'Foton', 'code' => 'FOT', 'country' => 'China'],
            ['name' => 'BAIC', 'code' => 'BAI', 'country' => 'China'],
            ['name' => 'Geely', 'code' => 'GEE', 'country' => 'China'],
            ['name' => 'BYD', 'code' => 'BYD', 'country' => 'China'],
            ['name' => 'MG', 'code' => 'MGM', 'country' => 'China'],
            ['name' => 'Proton', 'code' => 'PRO', 'country' => 'Malaysia'],
            ['name' => 'Perodua', 'code' => 'PER', 'country' => 'Malaysia'],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['code' => $brand['code']],
                $brand + [
                    'description' => 'Vehicle make commonly seen in Malawi through imports, regional dealer supply, or commercial fleets from 2000 to 2026.',
                    'is_active' => true,
                ]
            );
        }
    }
}
