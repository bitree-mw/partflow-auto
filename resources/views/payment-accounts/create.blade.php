@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.payment-accounts.index') }}">Back to list</a>
@endsection

@section('content')
    <form class="form-panel" method="POST" action="{{ route('web.payment-accounts.store') }}">
        @include('payment-accounts._form', [
            'paymentAccount' => $paymentAccount,
            'accountTypes' => $accountTypes,
            'submitLabel' => 'Create account',
        ])
    </form>
@endsection
