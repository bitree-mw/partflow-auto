<?php

namespace Tests\Feature;

use App\Models\ProductType;
use Database\Seeders\CptWorkbookProductTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CptWorkbookProductTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_only_the_missing_cpt_product_types_and_is_idempotent(): void
    {
        $existing = ProductType::query()->create([
            'name' => 'Existing Type',
            'code' => 'EXIST',
            'description' => 'Must not be changed.',
            'is_active' => true,
        ]);

        $this->seed(CptWorkbookProductTypeSeeder::class);
        $this->seed(CptWorkbookProductTypeSeeder::class);

        $this->assertDatabaseCount('product_types', 8);
        $this->assertDatabaseHas('product_types', [
            'id' => $existing->id,
            'name' => 'Existing Type',
            'code' => 'EXIST',
            'description' => 'Must not be changed.',
        ]);

        $this->assertSame([
            'BJ' => 'Ball Joint',
            'BTERM' => 'Battery Terminal',
            'CVJ' => 'CV Joint',
            'HG' => 'Head Gasket',
            'TBLT' => 'Timing Belt',
            'WBLADE' => 'Wiper Blade',
            'WTINT' => 'Window Tint Film',
        ], ProductType::query()
            ->where('code', '!=', 'EXIST')
            ->orderBy('code')
            ->pluck('name', 'code')
            ->all());
    }

    public function test_it_restores_a_missing_type_that_was_soft_deleted(): void
    {
        $headGasket = ProductType::query()->create([
            'name' => 'Head Gasket',
            'code' => 'HG',
            'description' => null,
            'is_active' => false,
        ]);
        $headGasket->delete();

        $this->seed(CptWorkbookProductTypeSeeder::class);

        $this->assertDatabaseCount('product_types', 7);
        $this->assertDatabaseHas('product_types', [
            'id' => $headGasket->id,
            'name' => 'Head Gasket',
            'code' => 'HG',
            'is_active' => true,
            'deleted_at' => null,
        ]);
    }
}
