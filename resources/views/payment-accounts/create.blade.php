@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/payment-accounts.css')
@endpush

@push('scripts')
    @vite('resources/js/payment-accounts.js')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ $returnTo ?? route('web.payment-accounts.index') }}">{{ $returnTo ? 'Back' : 'Back to list' }}</a>
@endsection

@section('content')
    <form class="form-panel" method="POST" action="{{ route('web.payment-accounts.store') }}">
        @include('payment-accounts._form', [
            'paymentAccount' => $paymentAccount,
            'accountTypes' => $accountTypes,
            'submitLabel' => 'Create account',
            'returnTo' => $returnTo,
        ])
    </form>
@endsection
