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
