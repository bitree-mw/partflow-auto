@extends('layouts.auth', [
    'title' => $title,
])

@section('content')
    <main class="login-page">
        <section class="login-brand-panel" aria-label="{{ $appSystem['business_name'] ?? 'PartFlow Auto' }}">
            <a class="login-brand" href="{{ route('login') }}">
                <span><x-company-logo :system="$appSystem" /></span>
                <div>
                    <strong>{{ $appSystem['business_name'] ?? 'PartFlow Auto' }}</strong>
                    <small>Auto parts operations</small>
                </div>
            </a>

            <div class="login-message">
                <span class="eyebrow">One clear view of every branch</span>
                <h1>Keep every part, sale, and branch moving.</h1>
                <p>{{ $description }}</p>
            </div>

            <div class="login-capabilities" aria-label="Workspace benefits">
                <div>
                    <strong>Live</strong>
                    <span>branch stock</span>
                </div>
                <div>
                    <strong>Traceable</strong>
                    <span>stock movements</span>
                </div>
                <div>
                    <strong>Secure</strong>
                    <span>role-based access</span>
                </div>
            </div>

            <small class="login-brand-footer">Built for focused auto-parts teams.</small>

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

        <section class="login-form-panel" aria-label="Login form">
            <div class="login-card">
                <x-flash />

                <header>
                    <span class="eyebrow">Staff access</span>
                    <h2>Welcome back.</h2>
                    <p>Sign in with the account provided by your administrator.</p>
                </header>

                <form method="POST" action="{{ route('login.store') }}" data-login-form>
                    @csrf

                    <label>
                        <span class="login-field-heading">Email address or username</span>
                        <span class="auth-input-shell">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 6h16v12H4z"></path>
                                <path d="m4 7 8 6 8-6"></path>
                            </svg>
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
                        </span>
                    </label>

                    <label>
                        <span class="login-field-heading">
                            <span>Password</span>
                            <small>Case sensitive</small>
                        </span>
                        <span class="auth-input-shell password-control">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                            </svg>
                            <input
                                class="auth-control"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                data-password-input
                                required
                            >
                            <button type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5z"></path>
                                    <circle cx="12" cy="12" r="2.5"></circle>
                                </svg>
                            </button>
                        </span>
                    </label>

                    <div class="login-options">
                        <label class="remember-option">
                            <input name="remember" type="checkbox" value="1">
                            <span>
                                <strong>Keep me signed in</strong>
                                <small>Use only on a trusted device.</small>
                            </span>
                        </label>
                    </div>

                    <button class="login-submit" type="submit" data-login-submit>
                        <span data-login-submit-label>Sign in</span>
                        <svg class="login-submit-arrow" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12h14m-5-5 5 5-5 5"></path>
                        </svg>
                        <span class="login-submit-spinner" aria-hidden="true"></span>
                    </button>
                </form>

                <aside class="login-help">
                    <span aria-hidden="true">?</span>
                    <p><strong>Cannot sign in?</strong> Ask an administrator to confirm your account is active or reset your password.</p>
                </aside>
            </div>

            <small class="login-legal">© {{ date('Y') }} {{ $appSystem['business_name'] ?? 'PartFlow Auto' }}. Authorized staff only.</small>
        </section>
    </main>
@endsection
