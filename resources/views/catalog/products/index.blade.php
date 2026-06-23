@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.car-models.create') }}">Add car model</a>
    <a class="btn-secondary" href="{{ route('web.catalog.part-types.create') }}">Add part type</a>
    <a class="btn" href="{{ route('web.catalog.products.create') }}">Add part</a>
@endsection

@section('content')
    <x-catalog-workflow current="products" />

    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Sellable parts</strong>
            <x-page-size-controls />
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
                            <td><strong>{{ $product['name'] }}</strong></td>
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
