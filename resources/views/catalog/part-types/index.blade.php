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
    <a class="btn" href="{{ route('web.catalog.part-types.create') }}">Add part type</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Part type library</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search part types..." aria-label="Search part types">
                <x-page-size-controls />
            </div>
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
