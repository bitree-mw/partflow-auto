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

@section('content')
    <section class="catalogue-hub">
        <div class="catalogue-summary">
            @foreach ($catalogueSummary as $item)
                <article>
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                    <p>{{ $item['detail'] }}</p>

                    @if ($item['label'] === 'Parts available')
                        <div class="catalogue-card-actions">
                            <a class="btn" href="{{ route('web.catalog.products.create') }}">Add part</a>
                        </div>
                    @elseif ($item['label'] === 'Part types')
                        <div class="catalogue-card-actions">
                            <a class="btn-secondary" href="{{ route('web.catalog.part-types.index') }}">View types</a>
                            <a class="btn-secondary" href="{{ route('web.catalog.part-types.create') }}">Add type</a>
                        </div>
                    @elseif ($item['label'] === 'Fuel types')
                        <div class="catalogue-card-actions">
                            <a class="btn-secondary" href="{{ route('web.catalog.fuel-types.index') }}">View fuels</a>
                            <a class="btn-secondary" href="{{ route('web.catalog.fuel-types.create') }}">Add fuel</a>
                        </div>
                    @elseif ($item['label'] === 'Car models')
                        <div class="catalogue-card-actions">
                            <a class="btn-secondary" href="{{ route('web.catalog.car-models.index') }}">View models</a>
                            <a class="btn-secondary" href="{{ route('web.catalog.car-models.create') }}">Add model</a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Sellable parts</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search parts table..." aria-label="Search sellable parts">
                <x-page-size-controls />
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th>Code</th>
                        <th>Main vehicle</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $product)
                        <tr>
                            <td>
                                <strong>{{ $product['name'] }}</strong>
                                <span class="branch-stock-pills">
                                    @foreach ($product['branch_stock'] as $branch)
                                        <span @class(['branch-stock-pill', 'empty' => $branch['qty'] === 0])>
                                            <span>{{ $branch['site'] }}</span>
                                            <strong>{{ $branch['qty'] }}</strong>
                                        </span>
                                    @endforeach
                                </span>
                            </td>
                            <td>{{ $product['code'] }}</td>
                            <td>{{ $product['model'] }}</td>
                            <td>{{ $product['type'] }}</td>
                            <td>{{ $product['price'] }}</td>
                            <td>{{ $product['stock'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
