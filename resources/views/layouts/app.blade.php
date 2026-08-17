<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim(($title ?? 'Dashboard').' | '.($appSystem['business_name'] ?? 'PartFlow Auto')) }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite([
        'resources/css/app.css',
        'resources/css/back-office.css',
        'resources/js/app.js',
    ])
    @stack('styles')
    @vite('resources/css/theme.css')
    @vite('resources/css/responsive.css')
</head>
<body class="{{ $bodyClass ?? 'app-shell' }}">
    <div class="app-frame" data-app-frame>
        @include('partials.sidebar')

        <button class="sidebar-backdrop" type="button" tabindex="-1" aria-label="Close navigation" data-sidebar-backdrop></button>

        <div class="app-main">
            @include('partials.header', [
                'title' => $title ?? 'Dashboard',
                'description' => $description ?? null,
                'kicker' => $kicker ?? ($appSystem['kicker'] ?? 'Operations'),
            ])

            <section class="content-shell">
                <x-flash />
                @yield('content')
            </section>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
