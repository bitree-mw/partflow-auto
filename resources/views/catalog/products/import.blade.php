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
    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.products.import.preview') }}" enctype="multipart/form-data">
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
                    <input class="form-control" id="file" name="file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    <p class="form-help">
                        Columns: {{ implode(', ', $templateHeaders) }}. All columns are required, up to {{ number_format($maxRows) }} rows.
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
            <button class="btn" type="submit">Upload and review</button>
        </div>
    </form>
@endsection
