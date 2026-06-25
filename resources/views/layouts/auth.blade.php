<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trim(($title ?? 'Login').' | PartFlow Auto') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    @vite([
        'resources/css/app.css',
        'resources/css/auth.css',
        'resources/js/auth.js',
    ])
</head>
<body class="auth-shell">
    @yield('content')
</body>
</html>
