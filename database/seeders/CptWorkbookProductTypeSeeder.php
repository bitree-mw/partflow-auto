<?php

namespace Database\Seeders;

use App\Models\ProductType;
use Illuminate\Database\Seeder;

class CptWorkbookProductTypeSeeder extends Seeder
{
    public function run(): void
    {
        $productTypes = [
            [
                'name' => 'CV Joint',
                'code' => 'CVJ',
                'description' => 'Constant velocity joint where inner or outer position is not specified.',
            ],
            [
                'name' => 'Head Gasket',
                'code' => 'HG',
                'description' => 'Engine cylinder head gasket.',
            ],
            [
                'name' => 'Timing Belt',
                'code' => 'TBLT',
                'description' => 'Engine timing belt supplied without a complete timing kit.',
            ],
            [
                'name' => 'Battery Terminal',
                'code' => 'BTERM',
                'description' => 'Battery cable terminal or terminal connector.',
            ],
            [
                'name' => 'Wiper Blade',
                'code' => 'WBLADE',
                'description' => 'Windscreen wiper blade where front or rear position is not specified.',
            ],
            [
                'name' => 'Ball Joint',
                'code' => 'BJ',
                'description' => 'Suspension ball joint where position is not specified.',
            ],
            [
                'name' => 'Window Tint Film',
                'code' => 'WTINT',
                'description' => 'Automotive window tint film.',
            ],
        ];

        foreach ($productTypes as $productType) {
            $record = ProductType::withTrashed()->firstOrCreate(
                ['code' => $productType['code']],
                $productType + ['is_active' => true]
            );

            if ($record->trashed()) {
                $record->fill($productType + ['is_active' => true]);
                $record->restore();
            }
        }
    }
}
