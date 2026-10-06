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
    @if (auth()->user()?->hasPermission($mode === 'customers' ? 'customers.manage' : 'suppliers.manage'))
        <a class="btn" href="{{ $createRoute }}">Add {{ $mode === 'customers' ? 'customer' : 'supplier' }}</a>
    @endif
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

    @if ($showBalances)
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
    @endif

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
                        @if ($showBalances)
                            <th>Credit limit</th>
                            <th>{{ $mode === 'customers' ? 'Customer balance' : 'Supplier payable' }}</th>
                        @endif
                        <th>Performance</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contacts as $contact)
                        <tr>
                            <td><strong>{{ $contact['name'] }}</strong><br><span>{{ $contact['code'] }}</span></td>
                            <td>{{ $contact['phone'] }}</td>
                            <td>{{ $contact['email'] }}</td>
                            @if ($showBalances)
                                <td>{{ $contact['credit_limit'] }}</td>
                                <td><span class="status-pill warning">{{ $contact['balance'] }}</span></td>
                            @endif
                            <td>{{ $contact['performance'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $contact['is_active']])>
                                    {{ $contact['status'] }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    @if (
                                        auth()->user()?->hasPermission($mode === 'customers' ? 'customers.manage' : 'suppliers.manage')
                                        && $contact['is_active']
                                        && (float) $contact['balance_amount'] <= 0
                                    )
                                        <form method="POST" action="{{ $mode === 'customers' ? route('web.customers.destroy', $contact['id']) : route('web.suppliers.destroy', $contact['id']) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                class="icon-action icon-danger"
                                                type="submit"
                                                title="Deactivate {{ $mode === 'customers' ? 'customer' : 'supplier' }}"
                                                aria-label="Deactivate {{ $mode === 'customers' ? 'customer' : 'supplier' }}"
                                                data-confirm-title="Deactivate {{ $mode === 'customers' ? 'customer' : 'supplier' }}?"
                                                data-confirm="Deactivate &quot;{{ $contact['name'] }}&quot;? The contact will be hidden from new transactions."
                                                data-confirm-label="Deactivate"
                                            >
                                                <x-icons.trash />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showBalances ? 8 : 6 }}" class="empty-state">No {{ $mode }} found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $contacts->links() }}
        </div>
    </section>
@endsection
