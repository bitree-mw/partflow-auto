<header class="app-header">
    <button
        class="mobile-nav-toggle"
        type="button"
        aria-label="Open navigation"
        aria-expanded="false"
        aria-controls="app-sidebar-navigation"
        data-mobile-sidebar-toggle
    >
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
        <span aria-hidden="true"></span>
    </button>

    <div class="header-copy">
        <p class="eyebrow">{{ $kicker }}</p>
        <h1>{{ $title }}</h1>
        @if (! empty($description))
            <p>{{ $description }}</p>
        @endif
        <p class="header-system-context">
            {{ $appSystem['business_name'] ?? 'PartFlow Auto' }}
            <span data-header-site-name>{{ $appSystem['site_name'] ?? 'All sites' }}</span>
            <span>{{ $appSystem['currency'] ?? 'MWK' }}</span>
        </p>
    </div>

    <div @class(['header-actions', 'has-dashboard-search' => request()->routeIs('web.dashboard')])>
        @if (request()->routeIs('web.dashboard') && auth()->user()?->hasPermission('catalogue.view'))
            <form class="header-search" method="GET" action="{{ route('web.catalog.products.index') }}" role="search">
                <label class="sr-only" for="global_catalogue_search">Search parts catalogue</label>
                <span aria-hidden="true">⌕</span>
                <input
                    id="global_catalogue_search"
                    type="search"
                    name="search"
                    value="{{ request()->routeIs('web.catalog.products.index') ? request()->string('search') : '' }}"
                    placeholder="Search part, code or vehicle..."
                >
                <kbd>Enter</kbd>
            </form>
        @endif

        @if (! empty($globalSiteOptions ?? []) && auth()->user()?->hasAnyPermission(['sales.view', 'sales.create', 'purchases.view', 'customers.view', 'suppliers.view']))
            <form
                class="header-branch-switcher"
                method="POST"
                action="{{ route('web.pos.site') }}"
                data-global-site-form
                data-can-change-directly="{{ ($globalCanChangeSiteDirectly ?? false) ? '1' : '0' }}"
            >
                @csrf
                <div class="header-branch-field">
                    <label for="global_site_id">Branch</label>
                    <select id="global_site_id" name="site_id" data-global-site-selector data-pos-site-selector aria-label="Active selling branch">
                        @foreach ($globalSiteOptions as $site)
                            <option value="{{ $site['id'] }}" @selected((int) $site['id'] === (int) ($globalCurrentSiteId ?? 0))>{{ $site['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="password" name="admin_password" placeholder="Admin password" autocomplete="current-password" data-global-site-password hidden>
                <button type="submit" class="btn-secondary" data-global-site-submit hidden>Change</button>
                <span data-global-site-error hidden></span>
            </form>
        @endif

        <details class="notification-menu">
            <summary
                @class(['icon-action', 'notification-action', 'has-notifications' => ($notificationSummary['count'] ?? 0) > 0])
                aria-label="Notifications"
                data-count="{{ $notificationSummary['count'] ?? 0 }}"
            >
                <x-icons.bell />
            </summary>
            <div class="notification-dropdown">
                <header>
                    <strong>Notifications</strong>
                    <span>{{ $notificationSummary['count'] ?? 0 }} open</span>
                </header>
                @forelse (($notificationSummary['latest'] ?? []) as $notification)
                    <a href="{{ $notification['review_url'] ?? route('web.alerts.index') }}">
                        <strong>{{ $notification['item'] }}</strong>
                        <span>{{ $notification['type'] }} - {{ $notification['detail'] }}</span>
                    </a>
                @empty
                    <p>No open notifications.</p>
                @endforelse
                <a class="notification-all-link" href="{{ route('web.alerts.index') }}">View all alerts</a>
            </div>
        </details>

        @hasSection('header_actions')
            @yield('header_actions')
        @elseif (auth()->user()?->hasPermission('sales.create'))
            <a class="btn" href="{{ route('web.pos') }}" target="_blank" rel="noopener">New sale</a>
        @endif
    </div>
</header>
