@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/sales.css')
@endpush

@section('header_actions')
    <a class="btn" href="{{ route('web.expenses.create') }}">Record expense</a>
@endsection

@php
    $currency = config('services.partflow.base_currency', 'MWK');
@endphp

@section('content')
    <section class="operation-summary">
        <article class="summary-card warning">
            <span>Total expenses</span>
            <strong>{{ $currency }} {{ number_format($summary['total'], 2) }}</strong>
            <em>{{ \Illuminate\Support\Carbon::parse($filters['date_from'] ?? today())->format('j M Y') }} to {{ \Illuminate\Support\Carbon::parse($filters['date_to'] ?? today())->format('j M Y') }}</em>
        </article>
        <article class="summary-card neutral">
            <span>Expenses recorded</span>
            <strong>{{ number_format($summary['count']) }}</strong>
            <em>Matching the filters below</em>
        </article>
        <article class="summary-card neutral">
            <span>Largest category</span>
            <strong>{{ $summary['top_category'] ?? 'None' }}</strong>
            <em>{{ $summary['top_category'] ? $currency.' '.number_format($summary['top_category_total'], 2) : 'No expenses yet' }}</em>
        </article>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.expenses.index') }}">
                <input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" aria-label="From date">
                <input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" aria-label="To date">
                <select name="expense_category_id" aria-label="Filter by category">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['expense_category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="payment_account_id" aria-label="Filter by payment account">
                    <option value="">All accounts</option>
                    @foreach ($paymentAccounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['payment_account_id'] ?? '') === (string) $account->id)>{{ $account->account_name }}</option>
                    @endforeach
                </select>
                @if ($sites->count() > 1)
                    <select name="site_id" aria-label="Filter by branch">
                        <option value="">All branches</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.expenses.index') }}">This month</a>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Branch</th>
                        <th>Paid from</th>
                        <th style="text-align: right;">Amount</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->expense_date?->format('j M Y') }}<br><span>{{ $expense->expense_date?->format('g:i A') }}</span></td>
                            <td>{{ $expense->expenseCategory?->name ?? 'Uncategorised' }}</td>
                            <td>
                                {{ $expense->description }}
                                @if ($expense->reference)
                                    <br><span>Ref: {{ $expense->reference }}</span>
                                @endif
                                <br><span>By {{ $expense->creator?->name ?? 'Unknown' }}</span>
                            </td>
                            <td>{{ $expense->site?->name ?? 'Whole business' }}</td>
                            <td>{{ $expense->paymentAccount?->account_name ?? 'Not recorded' }}</td>
                            <td style="text-align: right;"><strong>{{ $currency }} {{ number_format((float) $expense->amount, 2) }}</strong></td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-action icon-edit" href="{{ route('web.expenses.edit', $expense) }}" title="Edit expense" aria-label="Edit expense">
                                        <x-icons.pencil />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">No expenses recorded for these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $expenses->links() }}
        </div>
    </section>

    <section class="form-panel">
        <span class="eyebrow">Expense categories</span>
        <p>{{ $categories->isEmpty() ? 'Add a category such as Rent, Fuel, Wages or Utilities before recording expenses.' : $categories->pluck('name')->implode(' · ') }}</p>
        <form method="POST" action="{{ route('web.expense-categories.store') }}">
            @csrf
            <div class="form-grid">
                <div class="form-field">
                    <label for="category_name">New category</label>
                    <input class="form-control" id="category_name" name="name" value="{{ old('name') }}" placeholder="e.g. Rent" required maxlength="255">
                    <x-form-error name="name" />
                </div>
                <div class="form-field">
                    <label for="category_description">Description (optional)</label>
                    <input class="form-control" id="category_description" name="description" value="{{ old('description') }}" maxlength="500">
                    <x-form-error name="description" />
                </div>
            </div>
            <div class="form-actions">
                <button class="btn-secondary" type="submit">Add category</button>
            </div>
        </form>
    </section>
@endsection
