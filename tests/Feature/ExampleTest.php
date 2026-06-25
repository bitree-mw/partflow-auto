<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('web.dashboard'));
    }

    public function test_guest_back_office_pages_redirect_to_login(): void
    {
        $this->get('/back-office/dashboard')->assertRedirect(route('login'));
        $this->get('/pos')->assertRedirect(route('login'));
    }

    public function test_back_office_pages_return_successful_responses(): void
    {
        $this->actingAs(User::factory()->create());

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

    public function test_user_can_login_and_logout_with_session_api_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@partflow.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin@partflow.test',
            'password' => 'password',
        ])
            ->assertRedirect(route('web.dashboard'))
            ->assertSessionHas('partflow_api_token')
            ->assertSessionHas('partflow_api_token_id');

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
