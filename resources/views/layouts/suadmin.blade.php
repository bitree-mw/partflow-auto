<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ trim(($title ?? 'Super admin').' | '.($appSystem['business_name'] ?? 'PartFlow Auto')) }} super admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite([
        'resources/css/app.css',
        'resources/css/back-office.css',
        'resources/js/app.js',
    ])
    @vite('resources/css/theme.css')
    @vite('resources/css/responsive.css')
    @include('partials.theme-variables')
    {{-- Layout-only rules kept inline so the console needs no asset rebuild. --}}
    <style>
        .suadmin-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 20px; padding: 14px 24px; background: var(--pf-navy-950); color: var(--pf-on-color); }
        .suadmin-bar strong { font-size: 1rem; }
        .suadmin-bar small { display: block; opacity: .75; font-size: .75rem; }
        .suadmin-nav { display: flex; flex-wrap: wrap; gap: 6px; flex: 1; }
        .suadmin-nav a { color: inherit; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: .9rem; }
        .suadmin-nav a:hover, .suadmin-nav a[aria-current="page"] { background: rgba(255, 255, 255, .14); }
        .suadmin-bar form button { color: inherit; background: transparent; border: 1px solid rgba(255, 255, 255, .4); border-radius: 8px; padding: 7px 12px; font-weight: 600; cursor: pointer; }
        .suadmin-main { max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; display: grid; gap: 20px; }
        .suadmin-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
        .suadmin-heading h1 { margin: 0; font-size: 1.5rem; }
        .suadmin-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .suadmin-cards article { background: #fff; border: 1px solid #e3e8ef; border-radius: 12px; padding: 16px; }
        .suadmin-cards strong { display: block; font-size: 1.75rem; margin: 6px 0; }
        .suadmin-muted { color: #5b6678; font-size: .85rem; }
        .suadmin-details summary { cursor: pointer; color: #5b6678; font-size: .85rem; }
        .suadmin-details pre { white-space: pre-wrap; word-break: break-word; font-size: .75rem; background: #f5f6f8; padding: 8px; border-radius: 6px; max-width: 420px; }
        .row-actions form { display: inline; }
    </style>
</head>
<body class="app-shell suadmin-shell">
    <header class="suadmin-bar">
        <div>
            <strong>{{ $appSystem['business_name'] ?? 'PartFlow Auto' }}</strong>
            <small>Super admin console</small>
        </div>
        <nav class="suadmin-nav" aria-label="Super admin">
            <a href="{{ route('suadmin.dashboard') }}" @if (request()->routeIs('suadmin.dashboard')) aria-current="page" @endif>Overview</a>
            <a href="{{ route('suadmin.sites.index') }}" @if (request()->routeIs('suadmin.sites.*')) aria-current="page" @endif>Sites</a>
            <a href="{{ route('suadmin.users.index') }}" @if (request()->routeIs('suadmin.users.*')) aria-current="page" @endif>Users &amp; admins</a>
            <a href="{{ route('suadmin.audit-logs.index') }}" @if (request()->routeIs('suadmin.audit-logs.*')) aria-current="page" @endif>Audit log</a>
        </nav>
        <form method="POST" action="{{ route('suadmin.logout') }}">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </header>

    <main class="suadmin-main">
        <x-flash />
        <div class="suadmin-heading">
            <h1>{{ $title ?? 'Super admin' }}</h1>
            @yield('actions')
        </div>
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
