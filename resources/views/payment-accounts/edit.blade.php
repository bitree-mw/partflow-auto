@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.payment-accounts.show', $paymentAccount) }}">View account</a>
@endsection

@section('content')
    <form class="form-panel" method="POST" action="{{ route('web.payment-accounts.update', $paymentAccount) }}">
        @method('PUT')
        @include('payment-accounts._form', [
            'paymentAccount' => $paymentAccount,
            'accountTypes' => $accountTypes,
            'submitLabel' => 'Save changes',
        ])
    </form>
@endsection
