<style>
    :root {
        --pf-navy-950: {{ $appSystem['primary_color'] ?? '#0a1630' }};
        --pf-navy-900: color-mix(in srgb, {{ $appSystem['primary_color'] ?? '#0a1630' }} 90%, white);
        --pf-navy-800: color-mix(in srgb, {{ $appSystem['primary_color'] ?? '#0a1630' }} 80%, white);
        --pf-orange-600: color-mix(in srgb, {{ $appSystem['secondary_color'] ?? '#f47a2a' }} 84%, black);
        --pf-orange-500: {{ $appSystem['secondary_color'] ?? '#f47a2a' }};
        --pf-orange-100: color-mix(in srgb, {{ $appSystem['secondary_color'] ?? '#f47a2a' }} 16%, white);
        --pf-orange-text: color-mix(in srgb, {{ $appSystem['secondary_color'] ?? '#f47a2a' }} 70%, black);
        --pf-orange-border: color-mix(in srgb, {{ $appSystem['secondary_color'] ?? '#f47a2a' }} 48%, white);
        --pf-orange-hover: color-mix(in srgb, {{ $appSystem['secondary_color'] ?? '#f47a2a' }} 5%, white);
        --pf-canvas: {{ $appSystem['tertiary_color'] ?? '#f5f6f8' }};
        --pf-surface-muted: color-mix(in srgb, {{ $appSystem['tertiary_color'] ?? '#f5f6f8' }} 42%, white);
        --pf-on-color: {{ $appSystem['on_primary_color'] ?? '#ffffff' }};
        --pf-on-accent: {{ $appSystem['on_secondary_color'] ?? '#ffffff' }};
        --pf-focus: 0 0 0 3px color-mix(in srgb, {{ $appSystem['secondary_color'] ?? '#f47a2a' }} 24%, transparent);
    }
</style>
