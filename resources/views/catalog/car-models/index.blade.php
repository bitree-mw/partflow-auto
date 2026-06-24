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
    <a class="btn" href="{{ route('web.catalog.car-models.create') }}">Add car model</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Vehicle fitment records</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search car models..." aria-label="Search car models">
                <x-page-size-controls />
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Engine</th>
                        <th>Variant</th>
                        <th>Origin</th>
                        <th>Products</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($carModels as $carModel)
                        <tr>
                            <td><strong>{{ $carModel['make'] }} {{ $carModel['model'] }}</strong><br>{{ $carModel['year'] }}</td>
                            <td>{{ $carModel['engine'] }}</td>
                            <td>{{ $carModel['variant'] }}</td>
                            <td>{{ $carModel['origin'] }}</td>
                            <td>{{ $carModel['products'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
