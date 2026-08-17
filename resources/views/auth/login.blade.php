@extends('layouts.auth', [
    'title' => $title,
])

@section('content')
    <main class="login-page">
        <section class="login-brand-panel" aria-label="PartFlow Auto">
            <a class="login-brand" href="{{ route('login') }}">
                <span><x-brand-icon /></span>
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
                    <path
                        id="cog-outer"
                        d="M 43.4 -7.4 L 51.8 -4.9 L 51.8 4.9 L 43.4 7.4 L 42.9 9.8 L 49.7 15.3 L 46.0 24.3 L 37.2 23.4 L 35.9 25.4 L 40.1 33.1 L 33.1 40.1 L 25.4 35.9 L 23.4 37.2 L 24.3 46.0 L 15.3 49.7 L 9.8 42.9 L 7.4 43.4 L 4.9 51.8 L -4.9 51.8 L -7.4 43.4 L -9.8 42.9 L -15.3 49.7 L -24.3 46.0 L -23.4 37.2 L -25.4 35.9 L -33.1 40.1 L -40.1 33.1 L -35.9 25.4 L -37.2 23.4 L -46.0 24.3 L -49.7 15.3 L -42.9 9.8 L -43.4 7.4 L -51.8 4.9 L -51.8 -4.9 L -43.4 -7.4 L -42.9 -9.8 L -49.7 -15.3 L -46.0 -24.3 L -37.2 -23.4 L -35.9 -25.4 L -40.1 -33.1 L -33.1 -40.1 L -25.4 -35.9 L -23.4 -37.2 L -24.3 -46.0 L -15.3 -49.7 L -9.8 -42.9 L -7.4 -43.4 L -4.9 -51.8 L 4.9 -51.8 L 7.4 -43.4 L 9.8 -42.9 L 15.3 -49.7 L 24.3 -46.0 L 23.4 -37.2 L 25.4 -35.9 L 33.1 -40.1 L 40.1 -33.1 L 35.9 -25.4 L 37.2 -23.4 L 46.0 -24.3 L 49.7 -15.3 L 42.9 -9.8 Z"
                    />
                    <g id="outline-cog">
                        <use class="cog-outline" href="#cog-outer" />
                        <circle class="cog-inner" cx="0" cy="0" r="29" />
                    </g>
                    <g id="solid-cog">
                        <path
                            class="cog-solid-shape"
                            fill-rule="evenodd"
                            d="M 43.4 -7.4 L 51.8 -4.9 L 51.8 4.9 L 43.4 7.4 L 42.9 9.8 L 49.7 15.3 L 46.0 24.3 L 37.2 23.4 L 35.9 25.4 L 40.1 33.1 L 33.1 40.1 L 25.4 35.9 L 23.4 37.2 L 24.3 46.0 L 15.3 49.7 L 9.8 42.9 L 7.4 43.4 L 4.9 51.8 L -4.9 51.8 L -7.4 43.4 L -9.8 42.9 L -15.3 49.7 L -24.3 46.0 L -23.4 37.2 L -25.4 35.9 L -33.1 40.1 L -40.1 33.1 L -35.9 25.4 L -37.2 23.4 L -46.0 24.3 L -49.7 15.3 L -42.9 9.8 L -43.4 7.4 L -51.8 4.9 L -51.8 -4.9 L -43.4 -7.4 L -42.9 -9.8 L -49.7 -15.3 L -46.0 -24.3 L -37.2 -23.4 L -35.9 -25.4 L -40.1 -33.1 L -33.1 -40.1 L -25.4 -35.9 L -23.4 -37.2 L -24.3 -46.0 L -15.3 -49.7 L -9.8 -42.9 L -7.4 -43.4 L -4.9 -51.8 L 4.9 -51.8 L 7.4 -43.4 L 9.8 -42.9 L 15.3 -49.7 L 24.3 -46.0 L 23.4 -37.2 L 25.4 -35.9 L 33.1 -40.1 L 40.1 -33.1 L 35.9 -25.4 L 37.2 -23.4 L 46.0 -24.3 L 49.7 -15.3 L 42.9 -9.8 Z M 29 0 A 29 29 0 1 1 -29 0 A 29 29 0 1 1 29 0 Z"
                        />
                    </g>
                </defs>

                <g transform="translate(110 140) scale(0.9)">
                    <g class="cog cog-small"><use href="#outline-cog" /></g>
                </g>
                <g transform="translate(215 225) scale(1.78)">
                    <g class="cog cog-large"><use href="#solid-cog" /></g>
                </g>
                <g transform="translate(318 336) scale(1.54)">
                    <g class="cog cog-large-alt"><use href="#solid-cog" /></g>
                </g>
                <g transform="translate(376 192) scale(0.92)">
                    <g class="cog cog-small-alt"><use href="#outline-cog" /></g>
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

            <form method="POST" action="{{ route('login.store') }}" data-login-form>
                @csrf

                <label>
                    Email or username
                    <input
                        class="auth-control"
                        name="login"
                        type="text"
                        value="{{ old('login', old('email')) }}"
                        autocomplete="username"
                        placeholder="Enter email or username"
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

                <button class="login-submit" type="submit" data-login-submit>
                    <span data-login-submit-label>Sign in</span>
                    <span class="login-submit-spinner" aria-hidden="true"></span>
                </button>
            </form>
        </section>
    </main>
@endsection
