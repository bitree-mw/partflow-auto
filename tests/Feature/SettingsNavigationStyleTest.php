<?php

namespace Tests\Feature;

use Tests\TestCase;

class SettingsNavigationStyleTest extends TestCase
{
    public function test_settings_navigation_is_an_opaque_static_bar_with_visible_active_text(): void
    {
        $css = file_get_contents(resource_path('css/settings.css'));

        $this->assertIsString($css);
        // The section selector stays in place at the top of the page rather than floating over content.
        $this->assertMatchesRegularExpression(
            '/\.settings-nav-panel\s*\{[^}]*position:\s*static;[^}]*background:\s*#fff;/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression('/\.settings-nav-panel\s*\{[^}]*position:\s*(sticky|fixed)/s', $css);
        $this->assertMatchesRegularExpression(
            '/\.settings-nav-panel button\.active\s*\{[^}]*color:\s*#fff;[^}]*background:\s*var\(--pf-orange-600\);/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.settings-nav-panel button\.active strong,\s*\.settings-nav-panel button\.active small\s*\{[^}]*color:\s*#fff;/s',
            $css
        );
    }
}
