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
    @if (auth()->user()?->hasPermission('catalogue.manage'))
        <a class="btn" href="{{ route('web.catalog.car-models.create') }}">Add car model</a>
    @endif
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.catalog.car-models.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search car models..." aria-label="Search car models">

                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>

                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.car-models.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><x-sort-link field="vehicle" label="Vehicle" /></th>
                        <th><x-sort-link field="engine" label="Engine" /></th>
                        <th><x-sort-link field="variant" label="Variant" /></th>
                        <th><x-sort-link field="origin" label="Origin" /></th>
                        <th><x-sort-link field="products" label="Products" /></th>
                        <th><x-sort-link field="status" label="Status" /></th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($carModels as $carModel)
                        <tr>
                            <td><strong>{{ $carModel['make'] }} {{ $carModel['model'] }}</strong><br>{{ $carModel['year'] }}</td>
                            <td>{{ $carModel['engine'] }}</td>
                            <td>{{ $carModel['variant'] }}</td>
                            <td>{{ $carModel['origin'] }}</td>
                            <td>{{ $carModel['products'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $carModel['is_active']])>
                                    {{ $carModel['status'] }}
                                </span>
                            </td>
                            <td>
                                @if (auth()->user()?->hasPermission('catalogue.manage'))
                                    <div class="row-actions">
                                        <a class="icon-action icon-edit" href="{{ route('web.catalog.car-models.edit', $carModel['id']) }}" title="Edit car model" aria-label="Edit car model">
                                            <x-icons.pencil />
                                        </a>
                                        @if ($carModel['is_active'] && $carModel['linked_products'] === 0)
                                            <form method="POST" action="{{ route('web.catalog.car-models.destroy', $carModel['id']) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    class="icon-action icon-danger"
                                                    type="submit"
                                                    title="Deactivate vehicle"
                                                    aria-label="Deactivate vehicle"
                                                    data-confirm-title="Deactivate vehicle?"
                                                    data-confirm="Deactivate &quot;{{ $carModel['make'] }} {{ $carModel['model'] }} {{ $carModel['year'] }}&quot;? It will no longer be available for new product fitments."
                                                    data-confirm-label="Deactivate"
                                                >
                                                    <x-icons.trash />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">No car models found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $carModels->links() }}
        </div>
    </section>
@endsection
