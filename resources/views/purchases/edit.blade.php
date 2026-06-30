@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/purchases.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.purchases.index') }}">Back to purchases</a>
@endsection

@section('content')
    <form class="form-panel purchase-entry-form" method="POST" action="{{ route('web.purchases.update', $purchase) }}">
        @csrf
        @method('PUT')

        <section class="purchase-edit-summary">
            <article>
                <span>Purchase</span>
                <strong>{{ $purchase->document_number }}</strong>
            </article>
            <article>
                <span>Total</span>
                <strong>{{ $currency }} {{ number_format((float) $purchase->total_amount) }}</strong>
            </article>
            <article>
                <span>Paid</span>
                <strong>{{ $currency }} {{ number_format((float) $purchase->paid_amount) }}</strong>
            </article>
            <article>
                <span>Balance</span>
                <strong>{{ $currency }} {{ number_format((float) $purchase->balance_amount) }}</strong>
            </article>
        </section>

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Purchase details</span>
                <h2>Supplier and notes</h2>
                <p>These changes do not duplicate stock movement. Line items remain locked after receiving.</p>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="contact_id">Supplier</label>
                    <select class="form-control" id="contact_id" name="contact_id">
                        <option value="">No supplier selected</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier['id'] }}" @selected((string) old('contact_id', $purchase->contact_id) === (string) $supplier['id'])>
                                {{ $supplier['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="contact_id" />
                </div>

                <div class="form-field">
                    <label for="document_date">Purchase date</label>
                    <input class="form-control" id="document_date" name="document_date" type="date" value="{{ old('document_date', $purchase->document_date?->toDateString()) }}">
                    <x-form-error name="document_date" />
                </div>

                <div class="form-field full">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $purchase->notes) }}</textarea>
                    <x-form-error name="notes" />
                </div>
            </div>
        </section>

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Payment update</span>
                <h2>Record additional payment</h2>
                <p>Enter only the new amount paid now. Existing payments are listed below.</p>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="amount_paid">Amount paid now</label>
                    <input class="form-control" id="amount_paid" name="amount_paid" inputmode="decimal" value="{{ old('amount_paid') }}" placeholder="0">
                    <x-form-error name="amount_paid" />
                </div>

                <div class="form-field">
                    <label for="payment_account_id">Payment account</label>
                    <select class="form-control" id="payment_account_id" name="payment_account_id">
                        <option value="">No payment account</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account['id'] }}" @selected((string) old('payment_account_id') === (string) $account['id'])>
                                {{ $account['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="payment_account_id" />
                </div>

                <div class="form-field">
                    <label for="payment_method">Payment method</label>
                    <select class="form-control" id="payment_method" name="payment_method">
                        @foreach ($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="payment_method" />
                </div>

                <div class="form-field">
                    <label for="transaction_reference">Payment reference</label>
                    <input class="form-control" id="transaction_reference" name="transaction_reference" value="{{ old('transaction_reference') }}" placeholder="Receipt or transfer ref">
                    <x-form-error name="transaction_reference" />
                </div>
            </div>
        </section>

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Received parts</span>
                <h2>Purchase lines</h2>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Part</th>
                            <th>Quantity</th>
                            <th>Unit cost</th>
                            <th>Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase->items as $item)
                            <tr>
                                <td><strong>{{ $item->product?->product_name }}</strong><br>{{ $item->product?->product_code }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ $currency }} {{ number_format((float) $item->unit_cost) }}</td>
                                <td>{{ $currency }} {{ number_format((float) $item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('web.purchases.index') }}">Cancel</a>
            <button class="btn" type="submit">Update purchase</button>
        </div>
    </form>

    <section class="data-panel purchase-payments-panel">
        <header>
            <div>
                <span class="eyebrow">Payment history</span>
                <h2>Recorded payments</h2>
            </div>
        </header>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchase->payments as $payment)
                        <tr>
                            <td>{{ $payment->payment_date?->toDateString() }}</td>
                            <td>{{ $payment->paymentAccount?->account_name ?? 'Unknown account' }}</td>
                            <td>{{ str($payment->payment_method)->headline() }}</td>
                            <td>{{ $payment->transaction_reference ?: 'N/A' }}</td>
                            <td>{{ $currency }} {{ number_format((float) $payment->amount) }}</td>
                            <td>
                                <form method="POST" action="{{ route('web.purchases.payments.destroy', [$purchase, $payment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="icon-action danger" type="submit" aria-label="Remove payment" title="Remove payment" data-confirm="Remove this payment?">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M3 6h18"></path>
                                            <path d="M8 6V4h8v2"></path>
                                            <path d="M19 6l-1 14H6L5 6"></path>
                                            <path d="M10 11v5"></path>
                                            <path d="M14 11v5"></path>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No payments recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
