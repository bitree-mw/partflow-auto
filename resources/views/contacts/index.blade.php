@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/contacts.css')
@endpush

@push('scripts')
    @vite('resources/js/contacts.js')
@endpush

@section('header_actions')
    <a class="btn" href="{{ $createRoute }}">Add {{ $mode === 'customers' ? 'customer' : 'supplier' }}</a>
@endsection

@section('content')
    <section class="operation-summary">
        @foreach ($analytics as $item)
            <article @class(['summary-card', $item['tone'] ?? 'neutral'])>
                <span>{{ $item['label'] }}</span>
                <strong>{{ $item['value'] }}</strong>
                <em>{{ $item['detail'] }}</em>
            </article>
        @endforeach
    </section>

    <section class="contact-performance-panel">
        <header>
            <div>
                <span class="eyebrow">{{ $mode === 'customers' ? 'Credit sales' : 'Purchase performance' }}</span>
                <h2>{{ $mode === 'customers' ? 'Account health' : 'Supplier activity' }}</h2>
                <p>{{ $mode === 'customers' ? 'Track customer balances, credit limits, and collection pressure.' : 'Track purchase value, payable balances, lead times, and supplier return exposure.' }}</p>
            </div>
        </header>

        <div class="performance-bars">
            @foreach ($contacts as $contact)
                <article>
                    <div>
                        <strong>{{ $contact['name'] }}</strong>
                        <span>{{ $contact['performance'] }}</span>
                    </div>
                    <em>{{ $contact['balance'] }}</em>
                </article>
            @endforeach
        </div>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>{{ $mode === 'customers' ? 'Customer accounts' : 'Supplier accounts' }}</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search {{ $mode }}..." aria-label="Search {{ $mode }}">
                <x-page-size-controls />
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Credit limit</th>
                        <th>{{ $mode === 'customers' ? 'Customer balance' : 'Supplier payable' }}</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contacts as $contact)
                        <tr>
                            <td><strong>{{ $contact['name'] }}</strong><br><span>{{ $contact['code'] }}</span></td>
                            <td>{{ $contact['phone'] }}</td>
                            <td>{{ $contact['email'] }}</td>
                            <td>{{ $contact['credit_limit'] }}</td>
                            <td><span class="status-pill warning">{{ $contact['balance'] }}</span></td>
                            <td>{{ $contact['performance'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
