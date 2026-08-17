<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim(($title ?? 'Login').' | PartFlow Auto') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite([
        'resources/css/app.css',
        'resources/css/auth.css',
        'resources/js/auth.js',
    ])
    @vite('resources/css/theme.css')
    @vite('resources/css/responsive.css')
</head>
<body class="auth-shell">
    @yield('content')
</body>
</html>
