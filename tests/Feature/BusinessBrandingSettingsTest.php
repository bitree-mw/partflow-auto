<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessBrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_update_company_branding(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->administrator())->post(route('web.settings.update'), [
            'business_name' => 'Mwayi Auto Parts',
            'base_currency' => 'MWK',
            'primary_color' => '#123456',
            'secondary_color' => '#d97706',
            'tertiary_color' => '#eef2f7',
            'company_logo' => UploadedFile::fake()->image('mwayi-logo.png', 320, 120),
            'settings_panel' => 'company-profile',
        ]);

        $response->assertRedirect(route('web.settings.index').'#company-profile');

        $logoPath = BusinessSetting::query()->where('key', 'company_logo_path')->firstOrFail()->value;

        Storage::disk('public')->assertExists($logoPath);
        $this->assertDatabaseHas('business_settings', ['key' => 'business_name']);
        $this->assertSame('Mwayi Auto Parts', BusinessSetting::query()->where('key', 'business_name')->firstOrFail()->value);
        $this->assertSame('#123456', BusinessSetting::query()->where('key', 'primary_color')->firstOrFail()->value);
        $this->assertSame('#d97706', BusinessSetting::query()->where('key', 'secondary_color')->firstOrFail()->value);
        $this->assertSame('#eef2f7', BusinessSetting::query()->where('key', 'tertiary_color')->firstOrFail()->value);

        $this->get(route('web.settings.index'))
            ->assertOk()
            ->assertSee('Mwayi Auto Parts')
            ->assertSee('--pf-navy-950: #123456', false)
            ->assertSee('--pf-orange-500: #d97706', false)
            ->assertSee('--pf-canvas: #eef2f7', false)
            ->assertSee(Storage::disk('public')->url($logoPath), false);

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Mwayi Auto Parts')
            ->assertSee(Storage::disk('public')->url($logoPath), false);
    }

    public function test_branding_rejects_invalid_colors_and_logo_files(): void
    {
        Storage::fake('public');

        $this->actingAs($this->administrator())->post(route('web.settings.update'), [
            'business_name' => 'Mwayi Auto Parts',
            'base_currency' => 'MWK',
            'primary_color' => 'navy',
            'secondary_color' => '#f47a2a',
            'tertiary_color' => '#f5f6f8',
            'company_logo' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors(['primary_color', 'company_logo']);

        $this->assertDatabaseMissing('business_settings', ['key' => 'business_name']);
        Storage::disk('public')->assertDirectoryEmpty('branding');
    }

    private function administrator(): User
    {
        $role = Role::query()->create([
            'name' => 'Branding Administrator '.Role::query()->count(),
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
