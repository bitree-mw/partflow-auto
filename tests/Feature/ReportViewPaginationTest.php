<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportViewPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_tables_are_paginated_and_ordered_newest_first(): void
    {
        $role = Role::create(['name' => 'Report Administrator', 'permissions' => ['*'], 'is_active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $site = Site::create(['name' => 'Report Branch', 'code' => 'RPB', 'type' => 'branch', 'is_active' => true]);
        $productType = ProductType::create(['name' => 'Report Parts', 'code' => 'RPS', 'is_active' => true]);
        $product = Product::create([
            'product_code' => 'RP-001',
            'product_name' => 'Paginated Report Part',
            'product_type_id' => $productType->id,
            'default_purchase_price' => 100,
            'default_selling_price' => 150,
            'is_active' => true,
        ]);
        $customer = Contact::create([
            'contact_type' => 'customer',
            'code' => 'RP-CUSTOMER',
            'name' => 'Report Customer',
            'is_active' => true,
        ]);

        foreach (range(1, 11) as $position) {
            $document = InventoryDocument::create([
                'document_number' => sprintf('SALE-PAGE-%02d', $position),
                'document_type' => 'sale',
                'contact_id' => $customer->id,
                'source_site_id' => $site->id,
                'document_date' => today()->subDays($position - 1),
                'status' => 'completed',
                'subtotal_amount' => 150,
                'total_amount' => 150,
                'paid_amount' => 150,
                'balance_amount' => 0,
                'payment_status' => 'paid',
                'created_by' => $user->id,
            ]);
            InventoryDocumentItem::create([
                'inventory_document_id' => $document->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => 100,
                'unit_price' => 150,
                'line_total' => 150,
                'profit_amount' => 50,
            ]);
        }

        $filters = [
            'report_type' => 'sales',
            'date_from' => today()->subDays(20)->toDateString(),
            'date_to' => today()->toDateString(),
            'site_id' => $site->id,
            'per_page' => 10,
        ];

        $this->actingAs($user)
            ->get(route('web.reports.view', $filters))
            ->assertOk()
            ->assertSeeInOrder(['SALE-PAGE-01', 'SALE-PAGE-02', 'SALE-PAGE-10'])
            ->assertDontSee('SALE-PAGE-11')
            ->assertSee('data-report-pagination', false)
            ->assertSee('page=2', false);

        $this->get(route('web.reports.view', [...$filters, 'page' => 2]))
            ->assertOk()
            ->assertSee('SALE-PAGE-11')
            ->assertDontSee('SALE-PAGE-01');
    }
}
