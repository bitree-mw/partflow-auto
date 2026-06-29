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
    <a class="btn" href="{{ route('web.catalog.brands.create') }}">Add brand</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Brand library</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search brands..." aria-label="Search brands">
                <x-page-size-controls />
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Brand</th>
                        <th>Code</th>
                        <th>Country</th>
                        <th>Products</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr>
                            <td><strong>{{ $brand['name'] }}</strong></td>
                            <td>{{ $brand['code'] }}</td>
                            <td>{{ $brand['country'] }}</td>
                            <td>{{ $brand['products'] }}</td>
                            <td><span class="status-pill">{{ $brand['status'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">No brands have been added yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
