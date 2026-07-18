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
    @php
        $purchaseLineItems = collect(old('items', ! empty($prefillItem ?? []) ? [$prefillItem] : [[]]))->filter(fn ($item): bool => is_array($item))->values();
        $purchaseLineItems = $purchaseLineItems->isEmpty() ? collect([[]]) : $purchaseLineItems;
        $purchaseCurrency = $appSystem['currency'] ?? config('services.partflow.base_currency', 'MWK');
    @endphp

    <form class="form-panel purchase-entry-form" method="POST" action="{{ route('web.purchases.store') }}" data-purchase-form data-purchase-currency="{{ $purchaseCurrency }}">
        @csrf
        <x-form-error name="items" />

        <section class="purchase-entry-section">
            <div>
                <span class="eyebrow">Supplier document</span>
                <h2>Purchase details</h2>
                <p>Record who supplied the parts, where stock is being received, and whether payment is complete or still payable.</p>
            </div>

            <div class="form-grid">
                <div class="form-field">
                    <label for="contact_id">Supplier</label>
                    <select class="form-control" id="contact_id" name="contact_id">
                        <option value="">No supplier selected</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier['id'] }}" @selected((string) old('contact_id') === (string) $supplier['id'])>
                                {{ $supplier['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="contact_id" />
                </div>
                <div class="form-field">
                    <label for="destination_site_id">Receiving site</label>
                    <select class="form-control" id="destination_site_id" name="destination_site_id" required>
                        <option value="">Select site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site['id'] }}" @selected((string) old('destination_site_id', $prefillDestinationSiteId ?? '') === (string) $site['id'])>
                                {{ $site['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="destination_site_id" />
                </div>
                <div class="form-field">
                    <label for="purchase_date">Purchase date and time</label>
                    <input class="form-control" id="purchase_date" name="document_date" type="datetime-local" value="{{ old('document_date') }}">
                    <x-form-error name="document_date" />
                </div>
                <div class="form-field">
                    <label for="status">Document status</label>
                    <select class="form-control" id="status" name="status">
                        @foreach ($documentStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'completed') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="status" />
                </div>
                <div class="form-field">
                    <label for="amount_paid">Amount paid</label>
                    <input class="form-control" id="amount_paid" name="amount_paid" inputmode="decimal" value="{{ old('amount_paid') }}" placeholder="0" data-purchase-amount-paid>
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
                <span class="eyebrow">Parts received</span>
                <h2>Purchase lines</h2>
                <p>Use the catalogue price as a guide, then capture actual buying cost and new selling price when needed.</p>
            </div>

            <div class="purchase-lines" data-purchase-lines>
                @foreach ($purchaseLineItems as $lineIndex => $lineItem)
                    @php
                        $lineNumber = $lineIndex + 1;
                    @endphp
                    <article data-purchase-line>
                        <span data-purchase-line-number>{{ $lineNumber }}</span>
                        <div class="purchase-line-body">
                            <div class="purchase-line-top">
                                <div class="form-field">
                                    <label for="part_{{ $lineIndex }}">Part</label>
                                    <select class="form-control" id="part_{{ $lineIndex }}" name="items[{{ $lineIndex }}][product_id]" data-searchable-select>
                                        <option value="">Select part</option>
                                        @foreach ($parts as $part)
                                            <option value="{{ $part['id'] }}" @selected((string) old("items.{$lineIndex}.product_id", $lineItem['product_id'] ?? '') === (string) $part['id'])>
                                                {{ $part['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-form-error name="items.{{ $lineIndex }}.product_id" />
                                </div>
                                <button class="btn-secondary purchase-line-remove" type="button" data-remove-purchase-line>Remove</button>
                            </div>

                            <div class="purchase-line-grid">
                                <div class="form-field">
                                    <label for="qty_{{ $lineIndex }}">Quantity</label>
                                    <input class="form-control" id="qty_{{ $lineIndex }}" name="items[{{ $lineIndex }}][quantity]" inputmode="numeric" value="{{ old("items.{$lineIndex}.quantity", $lineItem['quantity'] ?? '') }}" placeholder="0" data-purchase-quantity>
                                    <x-form-error name="items.{{ $lineIndex }}.quantity" />
                                </div>
                                <div class="form-field">
                                    <label for="cost_{{ $lineIndex }}">Unit cost</label>
                                    <input class="form-control" id="cost_{{ $lineIndex }}" name="items[{{ $lineIndex }}][unit_cost]" inputmode="decimal" value="{{ old("items.{$lineIndex}.unit_cost", $lineItem['unit_cost'] ?? '') }}" placeholder="0" data-purchase-unit-cost>
                                    <x-form-error name="items.{{ $lineIndex }}.unit_cost" />
                                </div>
                                <div class="form-field purchase-line-total">
                                    <span>Line total</span>
                                    <strong data-purchase-line-total>{{ $purchaseCurrency }} 0</strong>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <template data-purchase-line-template>
                <article data-purchase-line>
                    <span data-purchase-line-number></span>
                    <div class="purchase-line-body">
                        <div class="purchase-line-top">
                            <div class="form-field">
                                <label for="part___INDEX__">Part</label>
                                <select class="form-control" id="part___INDEX__" name="items[__INDEX__][product_id]" data-searchable-select>
                                    <option value="">Select part</option>
                                    @foreach ($parts as $part)
                                        <option value="{{ $part['id'] }}">{{ $part['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn-secondary purchase-line-remove" type="button" data-remove-purchase-line>Remove</button>
                        </div>

                        <div class="purchase-line-grid">
                            <div class="form-field">
                                <label for="qty___INDEX__">Quantity</label>
                                <input class="form-control" id="qty___INDEX__" name="items[__INDEX__][quantity]" inputmode="numeric" placeholder="0" data-purchase-quantity>
                            </div>
                            <div class="form-field">
                                <label for="cost___INDEX__">Unit cost</label>
                                <input class="form-control" id="cost___INDEX__" name="items[__INDEX__][unit_cost]" inputmode="decimal" placeholder="0" data-purchase-unit-cost>
                            </div>
                            <div class="form-field purchase-line-total">
                                <span>Line total</span>
                                <strong data-purchase-line-total>{{ $purchaseCurrency }} 0</strong>
                            </div>
                        </div>
                    </div>
                </article>
            </template>

            <div class="purchase-line-actions">
                <button class="btn-secondary" type="button" data-add-purchase-line>Add line item</button>
            </div>

            <div class="purchase-totals">
                <div>
                    <span>Subtotal</span>
                    <strong data-purchase-subtotal>{{ $purchaseCurrency }} 0</strong>
                </div>
                <div>
                    <span>Amount paid</span>
                    <strong data-purchase-paid>{{ $purchaseCurrency }} 0</strong>
                </div>
                <div>
                    <span>Balance</span>
                    <strong data-purchase-balance>{{ $purchaseCurrency }} 0</strong>
                </div>
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
                <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Delivery note, return issue, or supplier follow-up">{{ old('notes') }}</textarea>
                <x-form-error name="notes" />
            </div>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('web.purchases.index') }}">Cancel</a>
            <button class="btn" type="submit">Save purchase</button>
        </div>
    </form>
@endsection
