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
    <a class="btn" href="{{ route('web.payment-accounts.create') }}">Create account</a>
@endsection

@section('content')
    <section class="payment-sample-strip" aria-label="Payment account display samples">
        <article>
            <span>Cash</span>
            <strong>Area 23 Till</strong>
            <p>Location: Main counter - Drawer: TILL-A23 - Custodian: Cashier Desk 01</p>
        </article>
        <article>
            <span>Mobile Money</span>
            <strong>Airtel Money Sales</strong>
            <p>Provider: Airtel Money - Wallet: +265 991 000 200 - Merchant: PF-AIRTEL-01</p>
        </article>
        <article>
            <span>Bank</span>
            <strong>National Bank Current</strong>
            <p>Bank: National Bank - Account: 1002044001 - Holder: PartFlow Auto Limited</p>
        </article>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.payment-accounts.index') }}">
                <input
                    name="search"
                    type="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search account..."
                    aria-label="Search payment accounts"
                >

                <select name="account_type" aria-label="Filter by account type">
                    <option value="">All types</option>
                    @foreach ($accountTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['account_type'] ?? '') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>

                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.payment-accounts.index') }}">Reset</a>
                <input class="table-search" type="search" data-table-search placeholder="Search shown table..." aria-label="Search shown payment accounts">
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($paymentAccounts as $account)
                        <tr>
                            <td>
                                <strong>{{ $account->account_name }}</strong><br>
                                <span>{{ $account->account_holder_name ?: 'No holder recorded' }}</span>
                            </td>
                            <td>{{ $accountTypes[$account->account_type] ?? $account->account_type }}</td>
                            <td>
                                {{ $account->bank_name ?: $account->mobile_number ?: $account->account_number ?: 'N/A' }}
                            </td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $account->is_active])>
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn-secondary" href="{{ route('web.payment-accounts.show', $account) }}">View</a>
                                    <a class="btn-secondary" href="{{ route('web.payment-accounts.edit', $account) }}">Edit</a>
                                    <form method="POST" action="{{ route('web.payment-accounts.destroy', $account) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">No payment accounts found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $paymentAccounts->links() }}
        </div>
    </section>
@endsection
