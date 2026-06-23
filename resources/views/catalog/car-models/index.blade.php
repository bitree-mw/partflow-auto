@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn" href="{{ route('web.catalog.car-models.create') }}">Add car model</a>
@endsection

@section('content')
    <x-catalog-workflow current="car-models" />

    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Vehicle fitment records</strong>
            <x-page-size-controls />
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
