@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@push('scripts')
    @vite('resources/js/catalog.js')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.products.import.template') }}">Download template</a>
    <a class="btn-secondary" href="{{ route('web.catalog.products.index') }}">Back to catalogue</a>
@endsection

@section('content')
    <form
        class="form-panel catalog-form import-form"
        method="POST"
        action="{{ route('web.catalog.products.import.preview') }}"
        enctype="multipart/form-data"
        data-import-form
        data-max-rows="{{ $maxRows }}"
        data-max-bytes="{{ $maxBytes }}"
        data-default-low-stock="{{ $defaultLowStockLevel }}"
    >
        @csrf

        @if ($errors->has('file'))
            <div class="import-errors" role="alert">
                <strong>The sheet could not be imported:</strong>
                <ul>
                    @foreach ($errors->get('file') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="form-section">
            <span class="eyebrow">Parts sheet</span>
            <div class="form-grid">
                <div class="form-field import-file-field">
                    <label for="file">CSV or Excel file</label>
                    <input class="sr-only" id="file" name="file" type="file" accept=".csv,.xlsx,.txt,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required data-import-file>
                    <div class="import-file-picker">
                        <label class="btn-secondary import-file-button" for="file">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span data-import-file-button-label>Choose file</span>
                        </label>
                        <span class="import-file-name" data-import-file-name>No file chosen</span>
                        <a class="btn-secondary import-template-button" href="{{ route('web.catalog.products.import.template') }}" download>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v12m0 0 4-4m-4 4-4-4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Download template
                        </a>
                    </div>
                    <div class="import-file-check" data-import-file-check hidden role="status" aria-live="polite">
                        <span class="import-file-check-icon" aria-hidden="true"></span>
                        <div>
                            <strong data-import-file-check-title></strong>
                            <ul data-import-file-check-messages></ul>
                        </div>
                    </div>
                    <p class="form-help">
                        Columns: {{ implode(', ', $templateHeaders) }}. Up to {{ number_format($maxRows) }} rows.
                        Warning Number is optional: if the column is missing or a cell is blank, the default of {{ $defaultLowStockLevel }} is used. Every other column is required.
                        Item Name is the product type and Description is the product name. Amounts may include commas, e.g. 12,500.00.
                    </p>
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Stock and purchase</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="site_id">Site receiving the stock</label>
                    <select class="form-control" id="site_id" name="site_id" required>
                        <option value="">Select site</option>
                        @foreach ($sites as $site)
                            <option value="{{ $site['id'] }}" @selected((string) old('site_id') === (string) $site['id'])>{{ $site['label'] }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="site_id" />
                </div>

                <div class="form-field">
                    <label for="contact_id">Supplier</label>
                    <select class="form-control" id="contact_id" name="contact_id">
                        <option value="">No supplier selected</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier['id'] }}" @selected((string) old('contact_id') === (string) $supplier['id'])>{{ $supplier['label'] }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="contact_id" />
                </div>

                <div class="form-field">
                    <label for="payment_account_id">Paid from account</label>
                    <select class="form-control" id="payment_account_id" name="payment_account_id" required>
                        <option value="">Select payment account</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account['id'] }}" @selected((string) old('payment_account_id', $defaultPaymentAccountId ?? '') === (string) $account['id'])>{{ $account['label'] }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="payment_account_id" />
                </div>

                <div class="form-field">
                    <label for="transaction_reference">Payment reference</label>
                    <input class="form-control" id="transaction_reference" name="transaction_reference" value="{{ old('transaction_reference') }}" maxlength="255" placeholder="Receipt or transfer ref">
                    <x-form-error name="transaction_reference" />
                </div>
            </div>
            <p class="form-help">The sheet is recorded as one completed purchase into this site, paid in full from the chosen account. Cost Price is the final amount paid; no VAT is added.</p>
        </section>

        <div class="form-actions">
            <button class="btn" type="submit" data-import-submit>Upload and review</button>
        </div>

        @include('catalog.products.partials.import-progress', [
            'title' => 'Uploading and reviewing your sheet',
            'steps' => ['Uploading file', 'Reading the sheet', 'Checking parts and prices', 'Preparing the review'],
        ])
    </form>
@endsection
