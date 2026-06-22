<?php

namespace Database\Seeders;

use App\Models\FuelType;
use Illuminate\Database\Seeder;

class FuelTypeSeeder extends Seeder
{
    public function run(): void
    {
        $fuelTypes = [
            [
                'name' => 'Universal',
                'code' => null,
                'description' => 'Fits petrol, diesel, hybrid, electric, or general vehicle variants.',
            ],
            [
                'name' => 'Petrol',
                'code' => 'I',
                'description' => 'Petrol-specific parts. Product code suffix: -I.',
            ],
            [
                'name' => 'Diesel',
                'code' => 'D',
                'description' => 'Diesel-specific parts. Product code suffix: -D.',
            ],
            [
                'name' => 'Hybrid',
                'code' => 'H',
                'description' => 'Hybrid-specific parts. Product code suffix: -H.',
            ],
            [
                'name' => 'Electric',
                'code' => 'E',
                'description' => 'Electric vehicle-specific parts. Product code suffix: -E.',
            ],
        ];

        foreach ($fuelTypes as $fuelType) {
            FuelType::updateOrCreate(
                ['name' => $fuelType['name']],
                $fuelType
            );
        }
    }
}
