@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.payment-accounts.index') }}">Back to list</a>
    <a class="icon-action icon-edit" href="{{ route('web.payment-accounts.edit', $paymentAccount) }}" title="Edit payment account" aria-label="Edit payment account">
        <x-icons.pencil />
    </a>
@endsection

@section('content')
    <section class="detail-panel">
        <div class="detail-grid">
            <div class="detail-item">
                <span>Account name</span>
                <strong>{{ $paymentAccount->account_name }}</strong>
            </div>

            <div class="detail-item">
                <span>Type</span>
                <strong>{{ str($paymentAccount->account_type)->replace('_', ' ')->title() }}</strong>
            </div>

            <div class="detail-item">
                <span>Bank name</span>
                <strong>{{ $paymentAccount->bank_name ?: 'N/A' }}</strong>
            </div>

            <div class="detail-item">
                <span>Account number</span>
                <strong>{{ $paymentAccount->account_number ?: 'N/A' }}</strong>
            </div>

            <div class="detail-item">
                <span>Mobile number</span>
                <strong>{{ $paymentAccount->mobile_number ?: 'N/A' }}</strong>
            </div>

            <div class="detail-item">
                <span>Account holder</span>
                <strong>{{ $paymentAccount->account_holder_name ?: 'N/A' }}</strong>
            </div>

            <div class="detail-item">
                <span>Status</span>
                <strong>{{ $paymentAccount->is_active ? 'Active' : 'Inactive' }}</strong>
            </div>

            <div class="detail-item">
                <span>Last updated</span>
                <strong>{{ $paymentAccount->updated_at?->toDayDateTimeString() ?: 'N/A' }}</strong>
            </div>
        </div>
    </section>
@endsection
