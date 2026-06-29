@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@push('scripts')
    @vite('resources/js/site-documents.js')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.sites.transfers.index') }}">View transfers</a>
    <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Site management</a>
@endsection

@section('content')
    @php
        $lineItems = collect(old('items', [[]]))->filter(fn ($item): bool => is_array($item))->values();
        $lineItems = $lineItems->isEmpty() ? collect([[]]) : $lineItems;
    @endphp

    <script type="application/json" data-site-stock-map>@json($stockAvailability)</script>

    <form class="form-panel catalog-form site-document-form" method="POST" action="{{ route('web.catalog.sites.transfers.store') }}" data-site-document-form data-document-mode="transfer">
        @csrf
        <x-form-error name="items" />

        <section class="form-section">
            <span class="eyebrow">Transfer header</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="source_site_id">From site</label>
                    <select class="form-control" id="source_site_id" name="source_site_id" data-source-site required>
                        <option value="">Select source</option>
                        @foreach ($siteOptions as $site)
                            <option value="{{ $site['id'] }}" @selected((string) old('source_site_id') === (string) $site['id'])>{{ $site['label'] }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="source_site_id" />
                </div>

                <div class="form-field">
                    <label for="destination_site_id">To site</label>
                    <select class="form-control" id="destination_site_id" name="destination_site_id" required>
                        <option value="">Select destination</option>
                        @foreach ($siteOptions as $site)
                            <option value="{{ $site['id'] }}" @selected((string) old('destination_site_id') === (string) $site['id'])>{{ $site['label'] }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="destination_site_id" />
                </div>

                <div class="form-field">
                    <label for="document_date">Transfer date</label>
                    <input class="form-control" id="document_date" name="document_date" type="date" value="{{ old('document_date', now()->toDateString()) }}">
                    <x-form-error name="document_date" />
                </div>

                <div class="form-field full">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    <x-form-error name="notes" />
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Line items</span>
            <div class="site-document-lines" data-site-document-lines>
                @foreach ($lineItems as $lineIndex => $lineItem)
                    <article class="site-document-line" data-site-document-line>
                        <span data-site-line-number>{{ $lineIndex + 1 }}</span>
                        <div class="site-document-line-grid">
                            <div class="form-field">
                                <label for="product_{{ $lineIndex }}">Part</label>
                                <select class="form-control" id="product_{{ $lineIndex }}" name="items[{{ $lineIndex }}][product_id]" data-searchable-select data-line-product required>
                                    <option value="">Select part</option>
                                    @foreach ($productOptions as $product)
                                        <option value="{{ $product['id'] }}" @selected((string) old("items.{$lineIndex}.product_id", $lineItem['product_id'] ?? '') === (string) $product['id'])>{{ $product['label'] }}</option>
                                    @endforeach
                                </select>
                                <small class="field-hint stock-live-summary" data-stock-summary>Select source and part to view stock</small>
                                <x-form-error name="items.{{ $lineIndex }}.product_id" />
                            </div>
                            <div class="form-field">
                                <label for="quantity_{{ $lineIndex }}">Quantity</label>
                                <input class="form-control" id="quantity_{{ $lineIndex }}" name="items[{{ $lineIndex }}][quantity]" type="number" min="1" value="{{ old("items.{$lineIndex}.quantity", $lineItem['quantity'] ?? 1) }}" data-line-quantity required>
                                <small class="field-hint" data-stock-hint>Select source and part</small>
                                <x-form-error name="items.{{ $lineIndex }}.quantity" />
                            </div>
                            <button class="icon-action danger site-line-remove" type="button" data-remove-site-line aria-label="Remove line item" title="Remove line item">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3 6h18"></path>
                                    <path d="M8 6V4h8v2"></path>
                                    <path d="M19 6l-1 14H6L5 6"></path>
                                    <path d="M10 11v5"></path>
                                    <path d="M14 11v5"></path>
                                </svg>
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>

            <template data-site-line-template>
                <article class="site-document-line" data-site-document-line>
                    <span data-site-line-number></span>
                    <div class="site-document-line-grid">
                        <div class="form-field">
                            <label for="product___INDEX__">Part</label>
                            <select class="form-control" id="product___INDEX__" name="items[__INDEX__][product_id]" data-searchable-select data-line-product required>
                                <option value="">Select part</option>
                                @foreach ($productOptions as $product)
                                    <option value="{{ $product['id'] }}">{{ $product['label'] }}</option>
                                @endforeach
                            </select>
                            <small class="field-hint stock-live-summary" data-stock-summary>Select source and part to view stock</small>
                        </div>
                        <div class="form-field">
                            <label for="quantity___INDEX__">Quantity</label>
                            <input class="form-control" id="quantity___INDEX__" name="items[__INDEX__][quantity]" type="number" min="1" value="1" data-line-quantity required>
                            <small class="field-hint" data-stock-hint>Select source and part</small>
                        </div>
                        <button class="icon-action danger site-line-remove" type="button" data-remove-site-line aria-label="Remove line item" title="Remove line item">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M3 6h18"></path>
                                <path d="M8 6V4h8v2"></path>
                                <path d="M19 6l-1 14H6L5 6"></path>
                                <path d="M10 11v5"></path>
                                <path d="M14 11v5"></path>
                            </svg>
                        </button>
                    </div>
                </article>
            </template>

            <button class="btn-secondary compatibility-add-button" type="button" data-add-site-line>Add line item</button>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('web.catalog.sites.transfers.index') }}">Cancel</a>
            <button class="btn" type="submit">Save transfer</button>
        </div>
    </form>
@endsection
