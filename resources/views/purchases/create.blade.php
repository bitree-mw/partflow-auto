@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/purchases.css')
@endpush

@push('scripts')
    @vite('resources/js/purchases.js')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.purchases.index') }}">Back to purchases</a>
@endsection

@section('content')
    <datalist id="supplier-options">
        @foreach ($suppliers as $supplier)
            <option value="{{ $supplier }}"></option>
        @endforeach
    </datalist>
    <datalist id="site-options">
        @foreach ($sites as $site)
            <option value="{{ $site }}"></option>
        @endforeach
    </datalist>
    <datalist id="part-options">
        @foreach ($parts as $part)
            <option value="{{ $part }}"></option>
        @endforeach
    </datalist>
    <datalist id="payment-status-options">
        @foreach ($paymentStatuses as $status)
            <option value="{{ $status }}"></option>
        @endforeach
    </datalist>

    <form class="form-panel purchase-entry-form" method="POST" action="{{ route('web.purchases.store') }}">
        @csrf

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Supplier document</span>
                <h2>Purchase details</h2>
                <p>Record who supplied the parts, where stock is being received, and whether payment is complete or still payable.</p>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="supplier">Supplier</label>
                    <input class="form-control searchable-input" id="supplier" name="supplier" list="supplier-options" placeholder="Search supplier">
                </div>
                <div class="form-field">
                    <label for="receiving_site">Receiving site</label>
                    <input class="form-control searchable-input" id="receiving_site" name="receiving_site" list="site-options" placeholder="Search branch or warehouse">
                </div>
                <div class="form-field">
                    <label for="supplier_invoice">Supplier invoice</label>
                    <input class="form-control" id="supplier_invoice" name="supplier_invoice" placeholder="INV-2026-001">
                </div>
                <div class="form-field">
                    <label for="purchase_date">Purchase date</label>
                    <input class="form-control" id="purchase_date" name="purchase_date" type="date">
                </div>
                <div class="form-field">
                    <label for="payment_status">Payment status</label>
                    <input class="form-control searchable-input" id="payment_status" name="payment_status" list="payment-status-options" placeholder="Paid, partial, or unpaid">
                </div>
                <div class="form-field">
                    <label for="amount_paid">Amount paid</label>
                    <input class="form-control" id="amount_paid" name="amount_paid" inputmode="decimal" placeholder="0">
                </div>
            </div>
        </section>

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Parts received</span>
                <h2>Purchase lines</h2>
                <p>Use the catalogue price as a guide, then capture actual buying cost and new selling price when needed.</p>
            </div>

            <div class="purchase-lines">
                @for ($line = 1; $line <= 3; $line++)
                    <article>
                        <span>{{ $line }}</span>
                        <div class="form-grid">
                            <div class="form-field full">
                                <label for="part_{{ $line }}">Part</label>
                                <input class="form-control searchable-input" id="part_{{ $line }}" name="lines[{{ $line }}][part]" list="part-options" placeholder="Search part catalogue">
                            </div>
                            <div class="form-field">
                                <label for="qty_{{ $line }}">Qty</label>
                                <input class="form-control" id="qty_{{ $line }}" name="lines[{{ $line }}][quantity]" inputmode="numeric" placeholder="0">
                            </div>
                            <div class="form-field">
                                <label for="cost_{{ $line }}">Unit cost</label>
                                <input class="form-control" id="cost_{{ $line }}" name="lines[{{ $line }}][unit_cost]" inputmode="decimal" placeholder="0">
                            </div>
                            <div class="form-field">
                                <label for="price_{{ $line }}">Selling price</label>
                                <input class="form-control" id="price_{{ $line }}" name="lines[{{ $line }}][selling_price]" inputmode="decimal" placeholder="0">
                            </div>
                            <div class="form-field">
                                <label for="note_{{ $line }}">Line note</label>
                                <input class="form-control" id="note_{{ $line }}" name="lines[{{ $line }}][note]" placeholder="Optional">
                            </div>
                        </div>
                    </article>
                @endfor
            </div>
        </section>

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Receiving notes</span>
                <h2>Operational notes</h2>
                <p>Capture delivery condition, supplier promises, and any parts that should be flagged for return.</p>
            </div>

            <div class="form-field">
                <label for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Delivery note, return issue, or supplier follow-up"></textarea>
            </div>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('web.purchases.index') }}">Cancel</a>
            <button class="btn" type="submit">Save purchase</button>
        </div>
    </form>
@endsection
