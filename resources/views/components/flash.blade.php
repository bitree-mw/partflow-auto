@if (session('success'))
    <div class="flash-message success" role="status">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="flash-message error" role="alert">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="flash-message error" role="alert">
        <strong>Please check the form.</strong>
        <span>{{ $errors->first() }}</span>
    </div>
@endif
