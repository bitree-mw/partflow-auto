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
    <a class="btn-secondary" href="{{ route('web.payment-accounts.show', $paymentAccount) }}">View account</a>
@endsection

@section('content')
    <form
        class="form-panel"
        method="POST"
        action="{{ route('web.payment-accounts.update', $paymentAccount) }}"
        data-track-unsaved-changes
        data-confirm-title="Save account changes?"
        data-confirm="Save the changes made to this payment account?"
        data-confirm-label="Save changes"
    >
        @method('PUT')
        @include('payment-accounts._form', [
            'paymentAccount' => $paymentAccount,
            'accountTypes' => $accountTypes,
            'submitLabel' => 'Save changes',
        ])
    </form>
@endsection
