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
    <a class="btn" href="{{ route('web.purchases.create') }}">New purchase</a>
@endsection

@section('content')
    <section class="operation-summary">
        @foreach ($summary as $item)
            <article @class([$item['tone'] ?? 'neutral'])>
                <span>{{ $item['label'] }}</span>
                <strong>{{ $item['value'] }}</strong>
                <em>{{ $item['detail'] }}</em>
            </article>
        @endforeach
    </section>

    <section class="purchase-workflow">
        <article>
            <span>1</span>
            <div>
                <strong>Supplier invoice</strong>
                <p>Capture supplier, invoice number, purchase date, payment status, and payable amount.</p>
            </div>
        </article>
        <article>
            <span>2</span>
            <div>
                <strong>Parts received</strong>
                <p>Add each part, quantity, buying price, selling price, and receiving branch.</p>
            </div>
        </article>
        <article>
            <span>3</span>
            <div>
                <strong>Stock update</strong>
                <p>Completed purchases increase site stock immediately and feed supplier analytics.</p>
            </div>
        </article>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Purchase documents</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search purchases..." aria-label="Search purchases table">
                <x-page-size-controls />
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Purchase</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Receiving site</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchases as $purchase)
                        <tr>
                            <td><strong>{{ $purchase['number'] }}</strong></td>
                            <td>{{ $purchase['date'] }}</td>
                            <td>{{ $purchase['supplier'] }}</td>
                            <td>{{ $purchase['site'] }}</td>
                            <td>{{ $purchase['items'] }}</td>
                            <td>{{ $purchase['total'] }}</td>
                            <td>{{ $purchase['paid'] }}</td>
                            <td><span @class(['status-pill', $purchase['tone']])>{{ $purchase['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
