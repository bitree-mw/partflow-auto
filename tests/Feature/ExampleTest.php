<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('web.dashboard'));
    }

    public function test_back_office_pages_return_successful_responses(): void
    {
        $pages = [
            '/pos',
            '/back-office/dashboard',
            '/back-office/sales',
            '/back-office/purchases',
            '/back-office/purchases/create',
            '/back-office/customers',
            '/back-office/customers/create',
            '/back-office/suppliers',
            '/back-office/suppliers/create',
            '/back-office/reports',
            '/back-office/alerts',
            '/back-office/settings',
            '/back-office/catalog/car-models',
            '/back-office/catalog/car-models/create',
            '/back-office/catalog/part-types',
            '/back-office/catalog/part-types/create',
            '/back-office/catalog/products',
            '/back-office/catalog/products/create',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }
}
