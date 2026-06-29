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
            <form class="filter-form" method="GET" action="{{ route('web.catalog.fuel-types.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search fuel types..." aria-label="Search fuel types">

                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>

                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.fuel-types.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><x-sort-link field="name" label="Fuel type" /></th>
                        <th><x-sort-link field="code" label="Code" /></th>
                        <th><x-sort-link field="products" label="Products" /></th>
                        <th><x-sort-link field="status" label="Status" /></th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fuelTypes as $fuelType)
                        <tr>
                            <td><strong>{{ $fuelType['name'] }}</strong></td>
                            <td>{{ $fuelType['code'] }}</td>
                            <td>{{ $fuelType['products'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $fuelType['is_active']])>
                                    {{ $fuelType['status'] }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-action icon-edit" href="{{ route('web.catalog.fuel-types.edit', $fuelType['id']) }}" title="Edit fuel type" aria-label="Edit fuel type">
                                        <x-icons.pencil />
                                    </a>
                                    @if ($fuelType['is_active'])
                                        <form method="POST" action="{{ route('web.catalog.fuel-types.destroy', $fuelType['id']) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-action icon-danger" type="submit" title="Make inactive" aria-label="Make inactive">
                                                <x-icons.trash />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">No fuel types found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $fuelTypes->links() }}
        </div>
    </section>
@endsection
