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
    <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Warehouse</a>
    <a class="btn" href="{{ route('web.pos') }}">New sale</a>
@endsection

@section('content')
    @php
        $selectedProductType = collect($selectedProductTypes ?? [])->keyBy('id')->get((int) ($filters['product_type_id'] ?? 0));
    @endphp

    <section class="catalogue-hub">
        <div class="catalogue-summary">
            @foreach ($catalogueSummary as $item)
                <article>
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                    <p>{{ $item['detail'] }}</p>

                    @if ($item['label'] === 'Products available')
                        <div class="catalogue-card-actions">
                            <a class="btn catalogue-add-button" href="{{ route('web.catalog.products.create') }}">Add product</a>
                        </div>
                    @elseif ($item['label'] === 'Brands')
                        <div class="catalogue-card-actions">
                            <a class="btn-secondary" href="{{ route('web.catalog.brands.index') }}">View</a>
                            <a class="icon-action catalogue-add-button catalogue-icon-add" href="{{ route('web.catalog.brands.create') }}" title="Add brand" aria-label="Add brand">
                                <x-icons.plus />
                            </a>
                        </div>
                    @elseif ($item['label'] === 'Product types')
                        <div class="catalogue-card-actions">
                            <a class="btn-secondary" href="{{ route('web.catalog.product-types.index') }}">View</a>
                            <a class="icon-action catalogue-add-button catalogue-icon-add" href="{{ route('web.catalog.product-types.create') }}" title="Add product type" aria-label="Add product type">
                                <x-icons.plus />
                            </a>
                        </div>
                    @elseif ($item['label'] === 'Fuel types')
                        <div class="catalogue-card-actions">
                            <a class="btn-secondary" href="{{ route('web.catalog.fuel-types.index') }}">View</a>
                            <a class="icon-action catalogue-add-button catalogue-icon-add" href="{{ route('web.catalog.fuel-types.create') }}" title="Add fuel type" aria-label="Add fuel type">
                                <x-icons.plus />
                            </a>
                        </div>
                    @elseif ($item['label'] === 'Car models')
                        <div class="catalogue-card-actions">
                            <a class="btn-secondary" href="{{ route('web.catalog.car-models.index') }}">View</a>
                            <a class="icon-action catalogue-add-button catalogue-icon-add" href="{{ route('web.catalog.car-models.create') }}" title="Add car model" aria-label="Add car model">
                                <x-icons.plus />
                            </a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.catalog.products.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search products..." aria-label="Search sellable products">

                <div class="async-picker filter-async-picker" data-product-type-picker data-endpoint="{{ route('web.catalog.product-type-options') }}">
                    <input type="hidden" name="product_type_id" value="{{ $selectedProductType['id'] ?? ($filters['product_type_id'] ?? '') }}" data-product-type-value>
                    <input class="form-control" type="search" value="{{ $selectedProductType['label'] ?? '' }}" placeholder="All product types" autocomplete="off" aria-label="Filter by product type" data-product-type-search>
                    <div class="async-picker-list" data-product-type-results hidden></div>
                </div>

                <select name="brand_id" aria-label="Filter by brand">
                    <option value="">All brands</option>
                    @foreach ($brandOptions as $brand)
                        <option value="{{ $brand['id'] }}" @selected((string) ($filters['brand_id'] ?? '') === (string) $brand['id'])>
                            {{ $brand['label'] }}
                        </option>
                    @endforeach
                </select>

                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>

                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.products.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><x-sort-link field="name" label="Product" /></th>
                        <th><x-sort-link field="type" label="Type" /></th>
                        <th><x-sort-link field="brand" label="Brand" /></th>
                        <th><x-sort-link field="compatibility" label="Compatible" /></th>
                        <th><x-sort-link field="price" label="Price" /></th>
                        <th><x-sort-link field="stock" label="Stock" /></th>
                        <th><x-sort-link field="status" label="Status" /></th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr class="product-row-main">
                            <td>
                                <strong class="catalogue-part-title">
                                    {{ $product['name'] }}
                                    @if ($product['code'])
                                        <span>({{ $product['code'] }})</span>
                                    @endif
                                </strong>
                            </td>
                            <td>{{ $product['type'] }}</td>
                            <td>{{ $product['brand'] }}</td>
                            <td>{{ $product['compatible_label'] }}</td>
                            <td>{{ $product['price'] }}</td>
                            <td>{{ $product['stock'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $product['is_active']])>
                                    {{ $product['status'] }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions catalogue-row-actions">
                                    <a class="icon-action icon-edit" href="{{ route('web.catalog.products.edit', $product['id']) }}" title="Edit product" aria-label="Edit product">
                                        <x-icons.pencil />
                                    </a>
                                    @if ($product['is_active'] && ! $product['has_stock'])
                                        <form method="POST" action="{{ route('web.catalog.products.destroy', $product['id']) }}">
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
                        <tr class="product-row-stock">
                            <td colspan="8">
                                <span class="branch-stock-pills branch-stock-full">
                                    @foreach ($product['branch_stock'] as $branch)
                                        <span @class(['branch-stock-pill', 'empty' => $branch['qty'] === 0])>
                                            <span>{{ $branch['site'] }}</span>
                                            <strong>{{ $branch['qty'] }}</strong>
                                        </span>
                                    @endforeach
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">No parts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $products->links() }}
        </div>
    </section>
@endsection
