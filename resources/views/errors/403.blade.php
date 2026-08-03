@extends('layouts.app', [
    'title' => 'Access Restricted',
    'description' => 'Your account does not have permission to use this part of the system.',
])

@section('content')
    <section class="empty-state access-restricted-state">
        <span class="eyebrow">Permission required</span>
        <h2>This page is not available for your role.</h2>
        <p>Return to the overview or ask a system administrator if you need access.</p>
        <a class="btn" href="{{ route('web.dashboard') }}">Back to overview</a>
    </section>
@endsection
