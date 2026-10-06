@extends('layouts.suadmin')

@section('content')
    <form class="data-panel" method="POST" action="{{ route('suadmin.package.update') }}">
        @csrf
        @method('PUT')

        <div class="panel-toolbar">
            <h2>Subscription package</h2>
            <span class="suadmin-muted">Changes apply immediately to every user of this installation. Data is kept when features are switched off.</span>
        </div>

        <div class="suadmin-panel-body">
            <fieldset class="suadmin-package-grid">
                <legend class="suadmin-muted">Choose the package this business pays for</legend>
                @foreach ($packages as $key => $package)
                    <label class="suadmin-package-option">
                        <span class="suadmin-package-head">
                            <input type="radio" name="package" value="{{ $key }}" @checked(old('package', $currentKey) === $key)>
                            <span class="suadmin-package-name">{{ $package['name'] }}</span>
                            @if ($key === $currentKey)
                                <span class="status-pill success">Current</span>
                            @endif
                        </span>
                        <span class="suadmin-package-price">MK{{ number_format($package['monthly_price']) }}</span>
                        <span class="suadmin-muted">Total per month</span>
                        <ul>
                            @foreach ($package['highlights'] as $highlight)
                                <li>{{ $highlight }}</li>
                            @endforeach
                        </ul>
                    </label>
                @endforeach
            </fieldset>
            <x-form-error name="package" />

            <p class="suadmin-muted" style="margin: 0;">
                Ignition allows one active branch. Before switching to Ignition, deactivate or delete the other branches.
                Customer sales on Ignition must be paid in full.
            </p>

            <div class="form-actions">
                <button
                    class="btn"
                    type="submit"
                    data-confirm-title="Change package?"
                    data-confirm="Switch this installation to the selected package? Screens and features change for every user straight away."
                    data-confirm-label="Change package"
                >Save package</button>
            </div>
        </div>
    </form>

    <form class="form-panel" method="POST" action="{{ route('suadmin.package.support') }}">
        @csrf
        @method('PUT')

        <h2>24/7 support contact</h2>
        <p class="suadmin-muted">Shown to administrators on the Autopilot package, in Settings.</p>

        <div class="form-grid">
            <div class="form-field">
                <label for="support_phone">Phone</label>
                <input class="form-control" id="support_phone" name="support_phone" value="{{ old('support_phone', $supportContact['support_phone']) }}" placeholder="+265 ...">
                <x-form-error name="support_phone" />
            </div>
            <div class="form-field">
                <label for="support_whatsapp">WhatsApp</label>
                <input class="form-control" id="support_whatsapp" name="support_whatsapp" value="{{ old('support_whatsapp', $supportContact['support_whatsapp']) }}" placeholder="+265 ...">
                <x-form-error name="support_whatsapp" />
            </div>
            <div class="form-field">
                <label for="support_email">Email</label>
                <input class="form-control" id="support_email" name="support_email" type="email" value="{{ old('support_email', $supportContact['support_email']) }}" placeholder="support@example.com">
                <x-form-error name="support_email" />
            </div>
            <div class="form-field">
                <label for="support_hours">Availability note</label>
                <input class="form-control" id="support_hours" name="support_hours" value="{{ old('support_hours', $supportContact['support_hours']) }}" placeholder="Available 24 hours, 7 days a week">
                <x-form-error name="support_hours" />
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Save support contact</button>
        </div>
    </form>
@endsection
