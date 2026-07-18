<header class="app-header">
    <div>
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

    <div class="header-actions">
        @php
            $hideGlobalSiteSwitcher = request()->routeIs([
                'web.dashboard',
                'web.catalog.*',
                'web.alerts.*',
                'web.settings.*',
                'web.reports.*',
            ]);
        @endphp

        @if (! $hideGlobalSiteSwitcher && ! empty($globalSiteOptions ?? []))
            <form
                class="header-branch-switcher"
                method="POST"
                action="{{ route('web.pos.site') }}"
                data-global-site-form
                data-can-change-directly="{{ ($globalCanChangeSiteDirectly ?? false) ? '1' : '0' }}"
            >
                @csrf
                <label for="global_site_id">Branch</label>
                <select id="global_site_id" name="site_id" data-global-site-selector data-pos-site-selector aria-label="Active selling branch">
                    @foreach ($globalSiteOptions as $site)
                        <option value="{{ $site['id'] }}" @selected((int) $site['id'] === (int) ($globalCurrentSiteId ?? 0))>{{ $site['label'] }}</option>
                    @endforeach
                </select>
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
        @else
            <a class="btn" href="{{ route('web.pos') }}">New sale</a>
        @endif
    </div>
</header>
