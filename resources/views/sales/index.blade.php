@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/operations.css')
@endpush

@section('header_actions')
    <a class="btn" href="{{ route('web.pos') }}">New sale</a>
@endsection

@section('content')
    <section class="operation-summary">
        @foreach ($summary as $item)
            <article>
                <span>{{ $item['label'] }}</span>
                <strong>{{ $item['value'] }}</strong>
            </article>
        @endforeach
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Recent sales</strong>
            <x-page-size-controls />
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Branch</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Profit</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td><strong>{{ $sale['invoice'] }}</strong></td>
                            <td>{{ $sale['branch'] }}</td>
                            <td>{{ $sale['customer'] }}</td>
                            <td>{{ $sale['items'] }}</td>
                            <td>{{ $sale['total'] }}</td>
                            <td>{{ $sale['profit'] }}</td>
                            <td><span @class(['status-pill', $sale['payment_tone'] ?? 'neutral'])>{{ $sale['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
