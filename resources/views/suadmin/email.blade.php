@extends('layouts.suadmin')

@section('content')
    <section class="form-panel">
        <h2>Send a test email</h2>
        <p class="suadmin-muted">Sends one message immediately using the server's mail settings. If the mail server returns an error, it is shown at the top of the page.</p>

        <form method="POST" action="{{ route('suadmin.email.test') }}">
            @csrf
            <div class="form-grid">
                <div class="form-field full">
                    <label for="test_email_recipient">Send a test email to</label>
                    <input
                        class="form-control"
                        id="test_email_recipient"
                        name="test_email_recipient"
                        type="email"
                        value="{{ old('test_email_recipient', $defaultRecipient) }}"
                        autocomplete="email"
                        placeholder="you@example.com"
                        required
                    >
                    <x-form-error name="test_email_recipient" />
                </div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Send test email</button>
            </div>
        </form>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <h2>Email delivery status</h2>
            <span class="suadmin-muted">Low-stock alerts and reminders also need the every-minute cron job.</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <tbody>
                    @foreach ($deliveryStatus as $check)
                        <tr>
                            <td>{{ $check['label'] }}</td>
                            <td><span @class(['status-pill', $check['tone']])>{{ $check['status'] }}</span></td>
                            <td>{{ $check['detail'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
