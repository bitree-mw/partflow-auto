@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/purchases.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.sales.index') }}">Back to sales</a>
@endsection

@section('content')
    <form
        class="form-panel purchase-entry-form"
        method="POST"
        action="{{ route('web.sales.update', $sale) }}"
        data-confirm-title="Save sale changes?"
        data-confirm="Save the changes made to this sale?"
        data-confirm-label="Save changes"
    >
        @csrf
        @method('PUT')

        <section class="purchase-edit-summary">
            <article>
                <span>Sale</span>
                <strong>{{ $sale->document_number }}</strong>
            </article>
            <article>
                <span>Total</span>
                <strong>{{ $currency }} {{ number_format((float) $sale->total_amount) }}</strong>
            </article>
            <article>
                <span>Paid</span>
                <strong>{{ $currency }} {{ number_format((float) $sale->paid_amount) }}</strong>
            </article>
            <article>
                <span>Balance</span>
                <strong>{{ $currency }} {{ number_format((float) $sale->balance_amount) }}</strong>
            </article>
        </section>

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Sale details</span>
                <h2>Customer and notes</h2>
                <p>Line items remain locked so stock and profit history stay consistent.</p>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="contact_id">Customer</label>
                    <select class="form-control" id="contact_id" name="contact_id">
                        <option value="">Walk-in customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer['id'] }}" @selected((string) old('contact_id', $sale->contact_id) === (string) $customer['id'])>
                                {{ $customer['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="contact_id" />
                </div>

                <div class="form-field">
                    <label for="document_date">Sale date and time</label>
                    <input class="form-control" id="document_date" name="document_date" type="datetime-local" value="{{ old('document_date', $sale->document_date?->format('Y-m-d\TH:i')) }}">
                    <x-form-error name="document_date" />
                </div>

                <div class="form-field full">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $sale->notes) }}</textarea>
                    <x-form-error name="notes" />
                </div>
            </div>
        </section>

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Payment update</span>
                <h2>Record additional payment</h2>
                <p>Enter only the new amount received now. Existing payments are listed below.</p>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="amount_paid">Amount received now</label>
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
                <span class="eyebrow">Sold parts</span>
                <h2>Sale lines</h2>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Part</th>
                            <th>Quantity</th>
                            <th>Unit price</th>
                            <th>Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->items as $item)
                            <tr>
                                <td><strong>{{ $item->product?->product_name }}</strong><br>{{ $item->product?->product_code }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ $currency }} {{ number_format((float) $item->unit_price) }}</td>
                                <td>{{ $currency }} {{ number_format((float) $item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('web.sales.index') }}">Cancel</a>
            <button class="btn" type="submit">Update sale</button>
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
                    @forelse ($sale->payments as $payment)
                        <tr>
                            <td>
                                <span class="date-stack">
                                    <strong>{{ $payment->payment_date?->format('M j, Y') ?? 'Not dated' }}</strong>
                                    <em>{{ $payment->payment_date?->format('g:i A') }}</em>
                                </span>
                            </td>
                            <td>{{ $payment->paymentAccount?->account_name ?? 'Unknown account' }}</td>
                            <td>{{ str($payment->payment_method)->headline() }}</td>
                            <td>{{ $payment->transaction_reference ?: 'N/A' }}</td>
                            <td>{{ $currency }} {{ number_format((float) $payment->amount) }}</td>
                            <td>
                                <form method="POST" action="{{ route('web.sales.payments.destroy', [$sale, $payment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="icon-action icon-danger" type="submit" aria-label="Remove payment" title="Remove payment" data-confirm="Remove this payment?">
                                        <x-icons.trash />
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
