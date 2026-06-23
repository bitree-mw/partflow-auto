<header class="app-header">
    <div>
        <p class="eyebrow">Back office</p>
        <h1>{{ $title }}</h1>
        @if (! empty($description))
            <p>{{ $description }}</p>
        @endif
    </div>

    @hasSection('header_actions')
        <div class="header-actions">
            @yield('header_actions')
        </div>
    @endif
</header>
