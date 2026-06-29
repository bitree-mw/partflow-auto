<header class="app-header">
    <div>
        <p class="eyebrow">{{ $kicker }}</p>
        <h1>{{ $title }}</h1>
        @if (! empty($description))
            <p>{{ $description }}</p>
        @endif
        <p class="header-system-context">
            {{ $appSystem['business_name'] ?? 'PartFlow Auto' }}
            <span>{{ $appSystem['site_name'] ?? 'All sites' }}</span>
            <span>{{ $appSystem['currency'] ?? 'MWK' }}</span>
        </p>
    </div>

    <div class="header-actions">
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
