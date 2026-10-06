@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.expenses.index') }}">Back to expenses</a>
@endsection

@section('content')
    @if ($categories->isEmpty())
        <section class="data-panel">
            <p class="empty-state">There are no expense categories yet. Add one on the <a href="{{ route('web.expenses.index') }}">Expenses page</a> first.</p>
        </section>
    @else
        <form class="form-panel" method="POST" action="{{ $expense->exists ? route('web.expenses.update', $expense) : route('web.expenses.store') }}" data-track-unsaved-changes>
            @csrf
            @if ($expense->exists)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="form-field">
                    <label for="expense_date">Date and time paid</label>
                    <input class="form-control" id="expense_date" name="expense_date" type="datetime-local" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d\TH:i')) }}" required data-no-future-date>
                    <x-form-error name="expense_date" />
                </div>

                <div class="form-field">
                    <label for="amount">Amount ({{ config('services.partflow.base_currency', 'MWK') }})</label>
                    <input class="form-control" id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $expense->amount) }}" required>
                    <x-form-error name="amount" />
                </div>

                <div class="form-field">
                    <label for="expense_category_id">Category</label>
                    <select class="form-control" id="expense_category_id" name="expense_category_id" required>
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('expense_category_id', $expense->expense_category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="expense_category_id" />
                </div>

                <div class="form-field">
                    <label for="payment_account_id">Paid from</label>
                    <select class="form-control" id="payment_account_id" name="payment_account_id">
                        <option value="">Not recorded</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('payment_account_id', $expense->payment_account_id) === (string) $account->id)>{{ $account->account_name }} ({{ str_replace('_', ' ', $account->account_type) }})</option>
                        @endforeach
                    </select>
                    <x-form-error name="payment_account_id" />
                </div>

                <div class="form-field">
                    <label for="site_id">Branch</label>
                    <select class="form-control" id="site_id" name="site_id" @unless ($canUseNoSite) required @endunless>
                        @if ($canUseNoSite)
                            <option value="">Whole business</option>
                        @else
                            <option value="">Select branch</option>
                        @endif
                        @foreach ($sites as $site)
                            <option value="{{ $site->id }}" @selected((string) old('site_id', $expense->site_id ?? ($sites->count() === 1 ? $site->id : '')) === (string) $site->id)>{{ $site->name }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="site_id" />
                </div>

                <div class="form-field">
                    <label for="reference">Receipt or reference number</label>
                    <input class="form-control" id="reference" name="reference" value="{{ old('reference', $expense->reference) }}" maxlength="255">
                    <x-form-error name="reference" />
                </div>

                <div class="form-field full">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3" maxlength="1000" required>{{ old('description', $expense->description) }}</textarea>
                    <x-form-error name="description" />
                </div>
            </div>

            <div class="form-actions">
                <a class="btn-secondary" href="{{ route('web.expenses.index') }}">Cancel</a>
                <button class="btn" type="submit">{{ $expense->exists ? 'Save changes' : 'Record expense' }}</button>
            </div>
        </form>
    @endif
@endsection
