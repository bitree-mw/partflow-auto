@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn" href="{{ route('web.catalog.part-types.create') }}">Add part type</a>
@endsection

@section('content')
    <x-catalog-workflow current="part-types" />

    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Part type library</strong>
            <x-page-size-controls />
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Part type</th>
                        <th>Code</th>
                        <th>Products</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($partTypes as $partType)
                        <tr>
                            <td><strong>{{ $partType['name'] }}</strong></td>
                            <td>{{ $partType['code'] }}</td>
                            <td>{{ $partType['products'] }}</td>
                            <td><span class="status-pill">{{ $partType['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
