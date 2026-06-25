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
    <a class="btn" href="{{ route('web.catalog.fuel-types.create') }}">Add fuel type</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Fuel type library</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search fuel types..." aria-label="Search fuel types">
                <x-page-size-controls />
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fuel type</th>
                        <th>Code</th>
                        <th>Products</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fuelTypes as $fuelType)
                        <tr>
                            <td><strong>{{ $fuelType['name'] }}</strong></td>
                            <td>{{ $fuelType['code'] }}</td>
                            <td>{{ $fuelType['products'] }}</td>
                            <td><span class="status-pill">{{ $fuelType['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
