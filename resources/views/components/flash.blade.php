@php
    $alert = null;

    if (session('success')) {
        $alert = [
            'tone' => 'success',
            'title' => 'Success',
            'message' => session('success'),
        ];
    } elseif (session('error')) {
        $alert = [
            'tone' => 'error',
            'title' => 'Action needed',
            'message' => session('error'),
        ];
    } elseif ($errors->any()) {
        $alert = [
            'tone' => 'error',
            'title' => 'Please check the form',
            'message' => $errors->first(),
        ];
    }
@endphp

@if ($alert)
    <dialog class="app-alert-dialog {{ $alert['tone'] }}" open role="alertdialog" aria-labelledby="app-alert-title" aria-describedby="app-alert-message">
        <div class="app-alert-dialog-card">
            <div class="app-alert-icon" aria-hidden="true">
                {{ $alert['tone'] === 'success' ? 'OK' : '!' }}
            </div>
            <div class="app-alert-content">
                <h2 id="app-alert-title">{{ $alert['title'] }}</h2>
                <p id="app-alert-message">{{ $alert['message'] }}</p>
            </div>
            <form method="dialog">
                <button class="app-alert-button" type="submit">OK</button>
            </form>
        </div>
    </dialog>
@endif
