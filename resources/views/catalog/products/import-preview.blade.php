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
    <a class="btn-secondary" href="{{ route('web.catalog.products.index') }}">Back to catalogue</a>
@endsection

@section('content')
    <section class="catalogue-hub">
        <div class="catalogue-summary import-summary">
            @foreach ($summary as $item)
                <article>
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                    <p>{{ $item['detail'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar import-preview-meta">
            <span><small>File</small> <strong>{{ $fileName }}</strong></span>
            <span><small>Site</small> <strong>{{ $site }}</strong></span>
            <span><small>Supplier</small> <strong>{{ $supplier }}</strong></span>
            <span><small>Paid from</small> <strong>{{ $paymentAccount }}</strong></span>
        </div>
        <div class="table-wrap">
            <table class="data-table import-preview-table">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Item name (type)</th>
                        <th>Description (product)</th>
                        <th class="numeric">Qty</th>
                        <th class="numeric">Cost price</th>
                        <th class="numeric">Min selling</th>
                        <th class="numeric">Max selling</th>
                        <th class="numeric">Warning</th>
                        <th class="numeric">Line total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['row_number'] }}</td>
                            <td>
                                {{ $row['product_type'] }}
                                @if ($row['is_new_type'])
                                    <small class="import-new-type">New type</small>
                                @endif
                            </td>
                            <td>{{ $row['product_name'] }}</td>
                            <td class="numeric">{{ $row['quantity'] }}</td>
                            <td class="numeric">{{ $row['unit_cost'] }}</td>
                            <td class="numeric">{{ $row['minimum_selling_price'] }}</td>
                            <td class="numeric">{{ $row['maximum_selling_price'] }}</td>
                            <td class="numeric">{{ $row['low_stock_level'] }}</td>
                            <td class="numeric">{{ $row['line_total'] }}</td>
                            <td><span @class(['status-pill', $row['status_tone']])>{{ $row['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="8">Purchase total (paid in full)</th>
                        <th class="numeric">{{ $totalCost }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="form-actions import-preview-actions">
            <form method="POST" action="{{ route('web.catalog.products.import.cancel') }}">
                @csrf
                <button class="btn-secondary" type="submit">Cancel</button>
            </form>
            <form method="POST" action="{{ route('web.catalog.products.import.store') }}">
                @csrf
                <input type="hidden" name="import_token" value="{{ $token }}">
                <button
                    class="btn"
                    type="submit"
                    data-confirm-title="Import these parts?"
                    data-confirm="This adds the stock to {{ $site }}, records a purchase of {{ $totalCost }}, and records a payment for the same amount."
                    data-confirm-label="Import"
                >Confirm import</button>
            </form>
        </div>
    </section>
@endsection
