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
            <form class="filter-form" method="GET" action="{{ route('web.catalog.brands.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search brands..." aria-label="Search brands">

                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>

                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.brands.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><x-sort-link field="name" label="Brand" /></th>
                        <th><x-sort-link field="code" label="Code" /></th>
                        <th><x-sort-link field="country" label="Country" /></th>
                        <th><x-sort-link field="products" label="Products" /></th>
                        <th><x-sort-link field="status" label="Status" /></th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr>
                            <td><strong>{{ $brand['name'] }}</strong></td>
                            <td>{{ $brand['code'] }}</td>
                            <td>{{ $brand['country'] }}</td>
                            <td>{{ $brand['products'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $brand['is_active']])>
                                    {{ $brand['status'] }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-action icon-edit" href="{{ route('web.catalog.brands.edit', $brand['id']) }}" title="Edit brand" aria-label="Edit brand">
                                        <x-icons.pencil />
                                    </a>
                                    @if ($brand['is_active'])
                                        <form method="POST" action="{{ route('web.catalog.brands.destroy', $brand['id']) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                class="icon-action icon-danger"
                                                type="submit"
                                                title="Deactivate brand"
                                                aria-label="Deactivate brand"
                                                data-confirm-title="Deactivate brand?"
                                                data-confirm="Deactivate &quot;{{ $brand['name'] }}&quot;? Existing products remain unchanged."
                                                data-confirm-label="Deactivate"
                                            >
                                                <x-icons.trash />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No brands found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $brands->links() }}
        </div>
    </section>
@endsection
