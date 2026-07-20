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
    <a class="btn" href="{{ route('web.catalog.product-types.create') }}">Add product type</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.catalog.product-types.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search product types..." aria-label="Search product types">

                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>

                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.product-types.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><x-sort-link field="name" label="Product type" /></th>
                        <th><x-sort-link field="code" label="Code" /></th>
                        <th><x-sort-link field="products" label="Products" /></th>
                        <th><x-sort-link field="status" label="Status" /></th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productTypes as $productType)
                        <tr>
                            <td><strong>{{ $productType['name'] }}</strong></td>
                            <td>{{ $productType['code'] }}</td>
                            <td>{{ $productType['products'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $productType['is_active']])>
                                    {{ $productType['status'] }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="icon-action icon-edit" href="{{ route('web.catalog.product-types.edit', $productType['id']) }}" title="Edit product type" aria-label="Edit product type">
                                        <x-icons.pencil />
                                    </a>
                                    @if ($productType['is_active'] && $productType['products'] === 0)
                                        <form method="POST" action="{{ route('web.catalog.product-types.destroy', $productType['id']) }}">
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
                            <td colspan="5" class="empty-state">No product types found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $productTypes->links() }}
        </div>
    </section>
@endsection
