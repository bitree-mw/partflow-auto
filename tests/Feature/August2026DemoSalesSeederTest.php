<?php

namespace Tests\Feature;

use App\Models\InventoryDocument;
use App\Models\StockMovement;
use Database\Seeders\August2026DemoSalesSeeder;
use Database\Seeders\PartFlowBaseTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class August2026DemoSalesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_traceable_daily_august_sales_without_duplicates(): void
    {
        Carbon::setTestNow('2026-08-27 16:00:00');

        try {
            $this->seed(PartFlowBaseTestingSeeder::class);
            $this->seed(August2026DemoSalesSeeder::class);

            $sales = InventoryDocument::query()
                ->where('document_type', 'sale')
                ->where('notes', 'like', 'August 2026 demo sale%')
                ->orderBy('document_date')
                ->get();

            $this->assertCount(54, $sales);
            $this->assertSame('2026-08-01', $sales->first()->document_date->toDateString());
            $this->assertSame('2026-08-27', $sales->last()->document_date->toDateString());
            $this->assertTrue($sales->every(fn (InventoryDocument $sale): bool => $sale->items()->exists()));
            $this->assertTrue($sales->contains(fn (InventoryDocument $sale): bool => $sale->payment_status === 'paid'));
            $this->assertTrue($sales->contains(fn (InventoryDocument $sale): bool => $sale->payment_status === 'partial'));
            $this->assertTrue($sales->contains(fn (InventoryDocument $sale): bool => $sale->payment_status === 'unpaid'));
            $this->assertSame(
                $sales->sum(fn (InventoryDocument $sale): int => $sale->items()->count()),
                StockMovement::query()
                    ->where('movement_type', 'sale_out')
                    ->whereIn('inventory_document_id', $sales->pluck('id'))
                    ->count()
            );

            $this->seed(August2026DemoSalesSeeder::class);

            $this->assertSame(
                54,
                InventoryDocument::query()
                    ->where('document_type', 'sale')
                    ->where('notes', 'like', 'August 2026 demo sale%')
                    ->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}
