<?php

namespace Database\Seeders;

use App\Models\TaxProfile;
use Illuminate\Database\Seeder;

class TaxProfileSeeder extends Seeder
{
    public function run(): void
    {
        $taxProfiles = [
            [
                'name' => 'VAT Inclusive 17.5%',
                'code' => '<VAT-INCLUSIVE-175>',
                'tax_type' => 'vat',
                'tax_rate' => 17.5,
                'price_mode' => 'inclusive',
                'is_exempt' => false,
                'exemption_reason' => null,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'VAT Exclusive 17.5%',
                'code' => '<VAT-EXCLUSIVE-175>',
                'tax_type' => 'vat',
                'tax_rate' => 17.5,
                'price_mode' => 'exclusive',
                'is_exempt' => false,
                'exemption_reason' => null,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Tax Exempt',
                'code' => '<TAX-EXEMPT>',
                'tax_type' => 'vat',
                'tax_rate' => 0,
                'price_mode' => 'exempt',
                'is_exempt' => true,
                'exemption_reason' => 'Customer or item is exempt from tax.',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'No VAT',
                'code' => '<NO-VAT>',
                'tax_type' => 'none',
                'tax_rate' => 0,
                'price_mode' => 'none',
                'is_exempt' => false,
                'exemption_reason' => null,
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($taxProfiles as $taxProfile) {
            TaxProfile::updateOrCreate(
                ['code' => $taxProfile['code']],
                $taxProfile
            );
        }
    }
}
