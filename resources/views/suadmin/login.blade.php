@extends('layouts.auth', [
    'title' => $title,
])

@section('content')
    <main class="login-page">
        <section class="login-form-panel" aria-label="Super admin sign in" style="grid-column: 1 / -1;">
            <div class="login-card">
                <x-flash />

                <header>
                    <span class="eyebrow">Restricted area</span>
                    <h2>Super admin console</h2>
                    <p>Manage sites, user and admin accounts, and review the audit log.</p>
                </header>

                @if ($configured)
                    <form method="POST" action="{{ route('suadmin.login.store') }}" data-login-form>
                        @csrf

                        <label>
                            <span class="login-field-heading">
                                <span>Super admin password</span>
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
                                    placeholder="Enter the super admin password"
                                    data-password-input
                                    required
                                    autofocus
                                >
                                <button type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5z"></path>
                                        <circle cx="12" cy="12" r="2.5"></circle>
                                    </svg>
                                </button>
                            </span>
                        </label>

                        <button class="login-submit" type="submit" data-login-submit>
                            <span data-login-submit-label>Enter console</span>
                            <span class="login-submit-spinner" aria-hidden="true"></span>
                        </button>
                    </form>
                @else
                    <aside class="login-help">
                        <span aria-hidden="true">!</span>
                        <p><strong>Not configured.</strong> Set <code>SUADMIN_PASSWORD_HASH</code> in the server's .env file to enable the super admin console.</p>
                    </aside>
                @endif

                <aside class="login-help">
                    <span aria-hidden="true">?</span>
                    <p>Every sign-in attempt is recorded in the audit log. After 5 wrong attempts, wait one minute.</p>
                </aside>
            </div>
        </section>
    </main>
@endsection
