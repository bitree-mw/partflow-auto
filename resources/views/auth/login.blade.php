@extends('layouts.auth', [
    'title' => $title,
])

@section('content')
    <main class="login-page">
        <section class="login-brand-panel" aria-label="PartFlow Auto">
            <a class="login-brand" href="{{ route('login') }}">
                <span>PF</span>
                <div>
                    <strong>PartFlow Auto</strong>
                    <small>Auto parts operations</small>
                </div>
            </a>

            <div class="login-message">
                <span class="eyebrow">Secure workspace</span>
                <h1>Sign in to continue.</h1>
                <p>{{ $description }}</p>
            </div>

            <div class="login-capabilities" aria-label="Protected modules">
                <span>POS</span>
                <span>Purchases</span>
                <span>Parts catalogue</span>
                <span>Reports</span>
            </div>

            <svg class="gear-machine" viewBox="0 0 420 460" aria-hidden="true" focusable="false">
                <defs>
                    <g id="outline-cog">
                        <path
                            class="cog-outline"
                            d="M 43.4 -7.4 L 51.8 -4.9 L 51.8 4.9 L 43.4 7.4 L 42.9 9.8 L 49.7 15.3 L 46.0 24.3 L 37.2 23.4 L 35.9 25.4 L 40.1 33.1 L 33.1 40.1 L 25.4 35.9 L 23.4 37.2 L 24.3 46.0 L 15.3 49.7 L 9.8 42.9 L 7.4 43.4 L 4.9 51.8 L -4.9 51.8 L -7.4 43.4 L -9.8 42.9 L -15.3 49.7 L -24.3 46.0 L -23.4 37.2 L -25.4 35.9 L -33.1 40.1 L -40.1 33.1 L -35.9 25.4 L -37.2 23.4 L -46.0 24.3 L -49.7 15.3 L -42.9 9.8 L -43.4 7.4 L -51.8 4.9 L -51.8 -4.9 L -43.4 -7.4 L -42.9 -9.8 L -49.7 -15.3 L -46.0 -24.3 L -37.2 -23.4 L -35.9 -25.4 L -40.1 -33.1 L -33.1 -40.1 L -25.4 -35.9 L -23.4 -37.2 L -24.3 -46.0 L -15.3 -49.7 L -9.8 -42.9 L -7.4 -43.4 L -4.9 -51.8 L 4.9 -51.8 L 7.4 -43.4 L 9.8 -42.9 L 15.3 -49.7 L 24.3 -46.0 L 23.4 -37.2 L 25.4 -35.9 L 33.1 -40.1 L 40.1 -33.1 L 35.9 -25.4 L 37.2 -23.4 L 46.0 -24.3 L 49.7 -15.3 L 42.9 -9.8 Z"
                        />
                        <circle class="cog-inner" cx="0" cy="0" r="29" />
                        <circle class="cog-hub" cx="0" cy="0" r="8" />
                        <g class="cog-spokes">
                            <line x1="0" y1="-12" x2="0" y2="-31" />
                            <line x1="0" y1="12" x2="0" y2="31" />
                            <line x1="-12" y1="0" x2="-31" y2="0" />
                            <line x1="12" y1="0" x2="31" y2="0" />
                            <line x1="8.5" y1="-8.5" x2="22" y2="-22" />
                            <line x1="-8.5" y1="8.5" x2="-22" y2="22" />
                            <line x1="-8.5" y1="-8.5" x2="-22" y2="-22" />
                            <line x1="8.5" y1="8.5" x2="22" y2="22" />
                        </g>
                    </g>
                </defs>

                <g transform="translate(226 94) scale(1.28)">
                    <g class="cog cog-large"><use href="#outline-cog" /></g>
                </g>
                <g transform="translate(126 151) scale(0.72)">
                    <g class="cog cog-small"><use href="#outline-cog" /></g>
                </g>
                <g transform="translate(218 220) scale(0.86)">
                    <g class="cog cog-medium"><use href="#outline-cog" /></g>
                </g>
                <g transform="translate(86 286) scale(0.76)">
                    <g class="cog cog-small-alt"><use href="#outline-cog" /></g>
                </g>
                <g transform="translate(246 352) scale(1.2)">
                    <g class="cog cog-large-alt"><use href="#outline-cog" /></g>
                </g>
            </svg>
        </section>

        <section class="login-card" aria-label="Login form">
            <x-flash />

            <header>
                <span class="eyebrow">Account access</span>
                <h2>Welcome back</h2>
                <p>Use your system account to open the operations dashboard.</p>
            </header>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <label>
                    Email address
                    <input
                        class="auth-control"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        placeholder="admin@partflow.test"
                        required
                        autofocus
                    >
                </label>

                <label>
                    Password
                    <span class="password-control">
                        <input
                            class="auth-control"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Enter password"
                            data-password-input
                            required
                        >
                        <button type="button" data-password-toggle>Show</button>
                    </span>
                </label>

                <div class="login-options">
                    <label class="remember-option">
                        <input name="remember" type="checkbox" value="1">
                        Remember me
                    </label>
                </div>

                <button class="login-submit" type="submit">Sign in</button>
            </form>
        </section>
    </main>
@endsection
