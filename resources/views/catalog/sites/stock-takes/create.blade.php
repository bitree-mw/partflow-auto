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
    <a class="btn-secondary" href="{{ route('web.catalog.sites.stock-takes.index') }}">View stock takes</a>
    <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Site management</a>
@endsection

@section('content')
    @php
        $lineItems = collect(old('items', [[]]))->filter(fn ($item): bool => is_array($item))->values();
        $lineItems = $lineItems->isEmpty() ? collect([[]]) : $lineItems;
    @endphp

    <script type="application/json" data-site-stock-map>@json($stockAvailability)</script>

    <form class="form-panel catalog-form site-document-form" method="POST" action="{{ route('web.catalog.sites.stock-takes.store') }}" data-site-document-form data-document-mode="stock-take">
        @csrf
        <x-form-error name="items" />

        <section class="form-section">
            <span class="eyebrow">Count header</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="site_id">Site</label>
                    <select class="form-control" id="site_id" name="site_id" data-source-site required>
                        <option value="">Select site</option>
                        @foreach ($siteOptions as $site)
                            <option value="{{ $site['id'] }}" @selected((string) old('site_id') === (string) $site['id'])>{{ $site['label'] }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="site_id" />
                </div>

                <div class="form-field">
                    <label for="document_date">Count date</label>
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
            <span class="eyebrow">Counted parts</span>
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
                                <small class="field-hint stock-live-summary" data-stock-summary>Select site and part to view system stock</small>
                                <x-form-error name="items.{{ $lineIndex }}.product_id" />
                            </div>
                            <div class="form-field">
                                <label for="counted_{{ $lineIndex }}">Counted quantity</label>
                                <input class="form-control" id="counted_{{ $lineIndex }}" name="items[{{ $lineIndex }}][counted_quantity]" type="number" min="0" value="{{ old("items.{$lineIndex}.counted_quantity", $lineItem['counted_quantity'] ?? 0) }}" required data-line-counted>
                                <small class="field-hint" data-stock-hint>Select site and part</small>
                                <x-form-error name="items.{{ $lineIndex }}.counted_quantity" />
                            </div>
                            <div class="form-field">
                                <label for="reason_{{ $lineIndex }}">Adjustment reason</label>
                                <input class="form-control" id="reason_{{ $lineIndex }}" name="items[{{ $lineIndex }}][notes]" value="{{ old("items.{$lineIndex}.notes", $lineItem['notes'] ?? '') }}" maxlength="500" placeholder="e.g. damaged, expired, or count correction" data-adjustment-reason>
                                <small class="field-hint" data-adjustment-reason-hint>Required when counted stock differs from system stock</small>
                                <x-form-error name="items.{{ $lineIndex }}.notes" />
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
                            <small class="field-hint stock-live-summary" data-stock-summary>Select site and part to view system stock</small>
                        </div>
                        <div class="form-field">
                            <label for="counted___INDEX__">Counted quantity</label>
                            <input class="form-control" id="counted___INDEX__" name="items[__INDEX__][counted_quantity]" type="number" min="0" value="0" required data-line-counted>
                            <small class="field-hint" data-stock-hint>Select site and part</small>
                        </div>
                        <div class="form-field">
                            <label for="reason___INDEX__">Adjustment reason</label>
                            <input class="form-control" id="reason___INDEX__" name="items[__INDEX__][notes]" maxlength="500" placeholder="e.g. damaged, expired, or count correction" data-adjustment-reason>
                            <small class="field-hint" data-adjustment-reason-hint>Required when counted stock differs from system stock</small>
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
            <a class="btn-secondary" href="{{ route('web.catalog.sites.stock-takes.index') }}">Cancel</a>
            <button class="btn" type="submit">Save stock take</button>
        </div>
    </form>
@endsection
