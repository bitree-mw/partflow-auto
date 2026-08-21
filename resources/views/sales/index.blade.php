@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/sales.css')
@endpush

@push('scripts')
    @vite('resources/js/sales.js')
@endpush

@section('header_actions')
    @if (auth()->user()?->hasPermission('sales.create'))
        <a class="btn" href="{{ route('web.pos') }}">New sale</a>
    @endif
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
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search sales..." aria-label="Search sales table">
                <x-page-size-controls />
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Profit</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sales as $sale)
                        <tr>
                            <td><strong>{{ $sale['invoice'] }}</strong></td>
                            <td>
                                <span class="date-stack">
                                    <strong>{{ $sale['date'] }}</strong>
                                    <em>{{ $sale['time'] }}</em>
                                </span>
                            </td>
                            <td>{{ $sale['branch'] }}</td>
                            <td>{{ $sale['customer'] }}</td>
                            <td>{{ $sale['items'] }}</td>
                            <td>{{ $sale['total'] }}</td>
                            <td>{{ $sale['profit'] }}</td>
                            <td><span @class(['status-pill', $sale['payment_tone'] ?? 'neutral'])>{{ $sale['status'] }}</span></td>
                            <td>
                                @if (auth()->user()?->hasPermission('sales.manage'))
                                    <a class="icon-action icon-edit" href="{{ route('web.sales.edit', $sale['id']) }}" aria-label="Edit sale" title="Edit sale">
                                        <x-icons.pencil />
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $sales->links() }}
        </div>
    </section>
@endsection
