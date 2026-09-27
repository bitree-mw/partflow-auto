<?php

namespace Tests\Feature;

use Tests\TestCase;

class SettingsNavigationStyleTest extends TestCase
{
    public function test_settings_navigation_is_an_opaque_full_width_bar_with_visible_active_text(): void
    {
        $css = file_get_contents(resource_path('css/settings.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.settings-nav-panel\s*\{[^}]*margin-inline:\s*-28px;[^}]*background:\s*#fff;/s',
            $css
        );
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
