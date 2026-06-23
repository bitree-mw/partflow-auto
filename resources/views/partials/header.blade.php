<header class="app-header">
    <div>
        <p class="eyebrow">{{ $kicker }}</p>
        <h1>{{ $title }}</h1>
        @if (! empty($description))
            <p>{{ $description }}</p>
        @endif
    </div>

    <div class="header-actions">
        <button class="icon-action" type="button" aria-label="Refresh">
            <span class="refresh-glyph" aria-hidden="true"></span>
        </button>
        <button class="icon-action notification-action" type="button" aria-label="Notifications">
            <span class="alert-glyph" aria-hidden="true"></span>
        </button>

        @hasSection('header_actions')
            @yield('header_actions')
        @else
            <a class="btn" href="{{ route('web.pos') }}">New sale</a>
        @endif
    </div>
</header>
