<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class CommonPartBrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Bosch', 'code' => 'BOS', 'country' => 'Germany', 'description' => 'Filters, spark plugs, braking, electrical and sensors.'],
            ['name' => 'Denso', 'code' => 'DNS', 'country' => 'Japan', 'description' => 'OEM electrical, ignition, filters, compressors and sensors.'],
            ['name' => 'NGK', 'code' => 'NGK', 'country' => 'Japan', 'description' => 'Spark plugs, glow plugs and ignition components.'],
            ['name' => 'KYB', 'code' => 'KYB', 'country' => 'Japan', 'description' => 'Shock absorbers, struts and suspension components.'],
            ['name' => 'Monroe', 'code' => 'MON', 'country' => 'United States', 'description' => 'Shock absorbers and suspension parts.'],
            ['name' => 'Sachs', 'code' => 'SAC', 'country' => 'Germany', 'description' => 'Clutch, shocks and drivetrain components.'],
            ['name' => 'Brembo', 'code' => 'BRM', 'country' => 'Italy', 'description' => 'Brake pads, discs and hydraulic brake components.'],
            ['name' => 'Ferodo', 'code' => 'FER', 'country' => 'United Kingdom', 'description' => 'Brake pads, shoes and friction material.'],
            ['name' => 'TRW', 'code' => 'TRW', 'country' => 'Germany', 'description' => 'Brakes, steering and suspension components.'],
            ['name' => 'ATE', 'code' => 'ATE', 'country' => 'Germany', 'description' => 'Brake hydraulic and friction parts.'],
            ['name' => 'Mann-Filter', 'code' => 'MNF', 'country' => 'Germany', 'description' => 'Oil, fuel, air and cabin filters.'],
            ['name' => 'Mahle', 'code' => 'MAHLE', 'country' => 'Germany', 'description' => 'Filters, engine parts, pistons and cooling components.'],
            ['name' => 'Hengst', 'code' => 'HEN', 'country' => 'Germany', 'description' => 'Automotive filtration products.'],
            ['name' => 'WIX', 'code' => 'WIX', 'country' => 'United States', 'description' => 'Oil, air and fuel filters.'],
            ['name' => 'GUD', 'code' => 'GUD', 'country' => 'South Africa', 'description' => 'Common filter brand across Southern Africa.'],
            ['name' => 'Donaldson', 'code' => 'DON', 'country' => 'United States', 'description' => 'Heavy duty filtration for trucks and commercial vehicles.'],
            ['name' => 'Fleetguard', 'code' => 'FLG', 'country' => 'United States', 'description' => 'Commercial vehicle and diesel filtration.'],
            ['name' => 'Valeo', 'code' => 'VAL', 'country' => 'France', 'description' => 'Clutch, lighting, cooling, wiper and electrical parts.'],
            ['name' => 'LUK', 'code' => 'LUK', 'country' => 'Germany', 'description' => 'Clutch kits and transmission components.'],
            ['name' => 'Exedy', 'code' => 'EXE', 'country' => 'Japan', 'description' => 'Clutch kits and drivetrain parts.'],
            ['name' => 'AISIN', 'code' => 'AIS', 'country' => 'Japan', 'description' => 'Water pumps, clutch, drivetrain and OEM components.'],
            ['name' => 'Gates', 'code' => 'GAT', 'country' => 'United States', 'description' => 'Belts, hoses and timing kits.'],
            ['name' => 'Dayco', 'code' => 'DAY', 'country' => 'United States', 'description' => 'Belts, hoses, tensioners and timing kits.'],
            ['name' => 'Continental', 'code' => 'CON', 'country' => 'Germany', 'description' => 'Belts, hoses and electronic components.'],
            ['name' => 'SKF', 'code' => 'SKF', 'country' => 'Sweden', 'description' => 'Bearings, hubs and timing kits.'],
            ['name' => 'NTN', 'code' => 'NTN', 'country' => 'Japan', 'description' => 'Bearings, hubs and drivetrain components.'],
            ['name' => 'NSK', 'code' => 'NSK', 'country' => 'Japan', 'description' => 'Bearings and precision rotating parts.'],
            ['name' => 'GMB', 'code' => 'GMB', 'country' => 'Japan', 'description' => 'Water pumps, universal joints and bearing kits.'],
            ['name' => 'Febi Bilstein', 'code' => 'FEB', 'country' => 'Germany', 'description' => 'Steering, suspension, engine and service parts.'],
            ['name' => 'Meyle', 'code' => 'MEY', 'country' => 'Germany', 'description' => 'Suspension, steering and service components.'],
            ['name' => 'Lemforder', 'code' => 'LEM', 'country' => 'Germany', 'description' => 'Premium steering and suspension components.'],
            ['name' => '555', 'code' => '555', 'country' => 'Japan', 'description' => 'Steering and suspension parts.'],
            ['name' => 'CTR', 'code' => 'CTR', 'country' => 'South Korea', 'description' => 'Suspension and steering parts.'],
            ['name' => 'Nissens', 'code' => 'NISEN', 'country' => 'Denmark', 'description' => 'Radiators, condensers and cooling parts.'],
            ['name' => 'Behr Hella', 'code' => 'BHR', 'country' => 'Germany', 'description' => 'Cooling and air conditioning parts.'],
            ['name' => 'Hella', 'code' => 'HEL', 'country' => 'Germany', 'description' => 'Lighting, bulbs, relays and electrical components.'],
            ['name' => 'Depo', 'code' => 'DEP', 'country' => 'Taiwan', 'description' => 'Aftermarket lamps and body lighting.'],
            ['name' => 'TYC', 'code' => 'TYC', 'country' => 'Taiwan', 'description' => 'Lighting, mirrors and cooling assemblies.'],
            ['name' => 'Koito', 'code' => 'KOI', 'country' => 'Japan', 'description' => 'OEM and replacement vehicle lighting.'],
            ['name' => 'Stanley', 'code' => 'STA', 'country' => 'Japan', 'description' => 'Vehicle lamps and bulbs.'],
            ['name' => 'Philips', 'code' => 'PHI', 'country' => 'Netherlands', 'description' => 'Bulbs, lamps and electrical lighting products.'],
            ['name' => 'Osram', 'code' => 'OSR', 'country' => 'Germany', 'description' => 'Automotive bulbs and lighting.'],
            ['name' => 'Varta', 'code' => 'VAR', 'country' => 'Germany', 'description' => 'Automotive batteries.'],
            ['name' => 'Willard', 'code' => 'WIL', 'country' => 'South Africa', 'description' => 'Automotive batteries common in Southern Africa.'],
            ['name' => 'Tudor', 'code' => 'TUD', 'country' => 'Spain', 'description' => 'Automotive batteries.'],
            ['name' => 'Yuasa', 'code' => 'YUA', 'country' => 'Japan', 'description' => 'Automotive and motorcycle batteries.'],
            ['name' => 'Bando', 'code' => 'BAN', 'country' => 'Japan', 'description' => 'Belts and rubber drive components.'],
            ['name' => 'Mitsuboshi', 'code' => 'MSB', 'country' => 'Japan', 'description' => 'Belts and timing components.'],
            ['name' => 'Tokico', 'code' => 'TOK', 'country' => 'Japan', 'description' => 'Shocks and brake components.'],
            ['name' => 'Akebono', 'code' => 'AKE', 'country' => 'Japan', 'description' => 'Brake pads and brake systems.'],
            ['name' => 'Textar', 'code' => 'TXT', 'country' => 'Germany', 'description' => 'Brake pads and discs.'],
            ['name' => 'Aftermarket', 'code' => 'AFT', 'country' => 'China', 'description' => 'General aftermarket replacement parts.'],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(
                ['code' => $brand['code']],
                $brand + ['is_active' => true]
            );
        }
    }
}
