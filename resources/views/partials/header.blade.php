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
        <button class="icon-action" type="button" aria-label="Refresh" onclick="window.location.reload()">
            <span class="refresh-glyph" aria-hidden="true"></span>
        </button>
        <a
            @class(['icon-action', 'notification-action', 'has-notifications' => ($notificationSummary['count'] ?? 0) > 0])
            href="{{ route('web.alerts.index') }}"
            aria-label="Notifications"
            data-count="{{ $notificationSummary['count'] ?? 0 }}"
        >
            <span class="alert-glyph" aria-hidden="true"></span>
        </a>

        @hasSection('header_actions')
            @yield('header_actions')
        @else
            <a class="btn" href="{{ route('web.pos') }}">New sale</a>
        @endif
    </div>
</header>
