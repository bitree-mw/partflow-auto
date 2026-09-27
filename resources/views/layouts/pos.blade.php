<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim(($title ?? 'Point of sale').' | '.($appSystem['business_name'] ?? 'PartFlow Auto')) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite([
        'resources/css/app.css',
        'resources/css/back-office.css',
        'resources/css/pos.css',
        'resources/js/app.js',
        'resources/js/pos.js',
    ])
    @vite('resources/css/theme.css')
    @vite('resources/css/responsive.css')
    @include('partials.theme-variables')
</head>
<body class="pos-page">
    <section class="pos-device-warning" role="alert" aria-labelledby="pos-device-warning-title">
        <span class="pos-device-warning-icon" aria-hidden="true">!</span>
        <p class="eyebrow">Desktop POS required</p>
        <h1 id="pos-device-warning-title">POS is not supported on mobile or tablet devices.</h1>
        <p>Open PartFlow Auto on a desktop computer with a screen at least 1200 pixels wide to process sales safely.</p>
        <button
            class="btn-secondary"
            type="button"
            data-close-pos
            data-fallback-url="{{ route('web.dashboard') }}"
        >Close POS</button>
    </section>

    <div class="pos-shell" data-pos-shell>
        <header class="app-header pos-header" aria-label="Point of sale controls">
            <div class="pos-header-identity">
                <span class="pos-brand-icon"><x-company-logo :system="$appSystem" /></span>
                <div>
                    <p class="eyebrow">Live service</p>
                    <h1>Point of sale</h1>
                    <p class="header-system-context">
                        {{ $appSystem['business_name'] ?? 'PartFlow Auto' }}
                        <span data-header-site-name>{{ $currentBranch }}</span>
                        <span>{{ $appSystem['currency'] ?? config('services.partflow.base_currency', 'MWK') }}</span>
                    </p>
                </div>
            </div>

            <div class="header-actions pos-header-actions">
                @if (! empty($siteOptions))
                    <form class="header-branch-switcher" action="{{ route('web.pos.site') }}" method="POST">
                        @csrf
                        <label for="pos_site_id">Selling branch</label>
                        <select id="pos_site_id" name="site_id" data-pos-site-selector aria-label="Active selling branch">
                            @foreach ($siteOptions as $site)
                                <option value="{{ $site['id'] }}" @selected((int) $site['id'] === (int) ($currentSiteId ?? 0))>{{ $site['label'] }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <button
                    class="btn-secondary pos-close-button"
                    type="button"
                    data-close-pos
                    data-fallback-url="{{ route('web.dashboard') }}"
                >Close POS</button>
            </div>
        </header>

        <main class="app-main pos-main">
            <section class="content-shell">
                <x-flash />
                @yield('content')
            </section>
        </main>
    </div>
</body>
</html>
