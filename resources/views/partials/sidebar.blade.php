@php
    $signedInUser = auth()->user();
    $signedInRole = $signedInUser?->relationLoaded('role') ? $signedInUser->role?->name : $signedInUser?->role?->name;
    $alertCount = $notificationSummary['count'] ?? 0;
    $initials = collect(explode(' ', $signedInUser?->name ?? 'User'))
        ->filter()
        ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $icons = [
        'grid' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect></svg>',
        'receipt' => '<svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"></path><path d="M9 8h6"></path><path d="M9 12h6"></path><path d="M9 16h4"></path></svg>',
        'trend' => '<svg viewBox="0 0 24 24"><path d="M4 17l5-5 4 4 7-8"></path><path d="M15 8h5v5"></path></svg>',
        'basket' => '<svg viewBox="0 0 24 24"><path d="M6 9h12l-1.2 10H7.2L6 9z"></path><path d="M9 9l3-6 3 6"></path><path d="M9 14h6"></path></svg>',
        'box' => '<svg viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
        'wallet' => '<svg viewBox="0 0 24 24"><path d="M4 7h15a2 2 0 0 1 2 2v9H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h12"></path><path d="M16 13h5"></path></svg>',
        'users' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'truck' => '<svg viewBox="0 0 24 24"><path d="M3 6h12v10H3z"></path><path d="M15 10h4l2 3v3h-6z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="17" cy="18" r="2"></circle></svg>',
        'parts' => '<svg viewBox="0 0 24 24"><path d="M7 7h10v10H7z"></path><path d="M4 4h4"></path><path d="M16 4h4"></path><path d="M4 20h4"></path><path d="M16 20h4"></path><path d="M4 12h3"></path><path d="M17 12h3"></path><path d="M12 4v3"></path><path d="M12 17v3"></path></svg>',
        'car' => '<svg viewBox="0 0 24 24"><path d="M5 12l2-5h10l2 5"></path><path d="M4 12h16v6H4z"></path><path d="M7 18v2"></path><path d="M17 18v2"></path><circle cx="8" cy="15" r="1"></circle><circle cx="16" cy="15" r="1"></circle></svg>',
        'tag' => '<svg viewBox="0 0 24 24"><path d="M20 13l-7 7L4 11V4h7l9 9z"></path><circle cx="8.5" cy="8.5" r="1.5"></circle></svg>',
        'bars' => '<svg viewBox="0 0 24 24"><path d="M5 20V10"></path><path d="M12 20V4"></path><path d="M19 20v-7"></path></svg>',
        'bell' => '<svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>',
        'shield' => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    ];

    $navigationGroups = [
        'Workspace' => [
            ['label' => 'Overview', 'icon' => 'grid', 'href' => route('web.dashboard'), 'active' => request()->routeIs('web.dashboard')],
            ['label' => 'Point of sale', 'icon' => 'receipt', 'href' => route('web.pos'), 'active' => request()->routeIs('web.pos')],
        ],
        'Operations' => [
            ['label' => 'Sales', 'icon' => 'trend', 'href' => route('web.sales.index'), 'active' => request()->routeIs('web.sales.*')],
            ['label' => 'Purchases', 'icon' => 'basket', 'href' => route('web.purchases.index'), 'active' => request()->routeIs('web.purchases.*')],
            ['label' => 'Customers', 'icon' => 'users', 'href' => route('web.customers.index'), 'active' => request()->routeIs('web.customers.*')],
            ['label' => 'Suppliers', 'icon' => 'truck', 'href' => route('web.suppliers.index'), 'active' => request()->routeIs('web.suppliers.*')],
        ],
        'Catalogue' => [
            ['label' => 'Site management', 'icon' => 'box', 'href' => route('web.catalog.sites.index'), 'active' => request()->routeIs('web.catalog.sites.*')],
            ['label' => 'Parts catalogue', 'icon' => 'parts', 'href' => route('web.catalog.products.index'), 'active' => request()->routeIs('web.catalog.*') && ! request()->routeIs('web.catalog.sites.*')],
        ],
        'Intelligence' => [
            ['label' => 'Reports', 'icon' => 'bars', 'href' => route('web.reports.index'), 'active' => request()->routeIs('web.reports.*')],
            ['label' => 'Alerts', 'icon' => 'bell', 'href' => route('web.alerts.index'), 'active' => request()->routeIs('web.alerts.*'), 'badge' => $alertCount],
        ],
        'Admin' => [
            ['label' => 'Admin settings', 'icon' => 'shield', 'href' => route('web.settings.index'), 'active' => request()->routeIs('web.settings.*')],
        ],
    ];
@endphp

<aside class="app-sidebar" aria-label="Primary navigation">
    <a class="brand-mark" href="{{ route('web.dashboard') }}">
        <span>{{ $appSystem['business_initials'] ?? 'PF' }}</span>
        <strong>{{ $appSystem['business_name'] ?? 'PartFlow Auto' }}</strong>
        <small>{{ $appSystem['tagline'] ?? 'Auto parts operations' }}</small>
    </a>

    <nav class="sidebar-nav" aria-label="Application sections">
        @foreach ($navigationGroups as $group => $items)
            <section class="sidebar-group" aria-label="{{ $group }}">
                <p>{{ $group }}</p>

                @foreach ($items as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @class(['active' => $item['active'], 'disabled' => $item['href'] === '#'])
                        @if ($item['href'] === '#') aria-disabled="true" tabindex="-1" @endif
                    >
                        <span class="nav-icon" aria-hidden="true">{!! $icons[$item['icon']] !!}</span>
                        <span>{{ $item['label'] }}</span>

                        @if (! empty($item['badge']))
                            <em>{{ $item['badge'] }}</em>
                        @endif
                    </a>
                @endforeach
            </section>
        @endforeach
    </nav>

    <form class="sidebar-user" method="POST" action="{{ route('logout') }}" aria-label="Signed in user">
        @csrf
        <span>{{ $initials ?: 'U' }}</span>
        <div>
            <strong>{{ $signedInUser?->name ?? 'Signed in user' }}</strong>
            <small>{{ $signedInRole ?? 'User' }} - Active</small>
        </div>
        <button type="submit" aria-label="Log out">-&gt;</button>
    </form>
</aside>
