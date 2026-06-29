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
            <form class="filter-form" method="GET" action="{{ route('web.catalog.part-types.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search part types..." aria-label="Search part types">

                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>

                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.part-types.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><x-sort-link field="name" label="Part type" /></th>
                        <th><x-sort-link field="code" label="Code" /></th>
                        <th><x-sort-link field="products" label="Products" /></th>
                        <th><x-sort-link field="status" label="Status" /></th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partTypes as $partType)
                        <tr>
                            <td><strong>{{ $partType['name'] }}</strong></td>
                            <td>{{ $partType['code'] }}</td>
                            <td>{{ $partType['products'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $partType['is_active']])>
                                    {{ $partType['status'] }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn-secondary" href="{{ route('web.catalog.part-types.edit', $partType['id']) }}">Edit</a>
                                    @if ($partType['is_active'] && $partType['products'] === 0)
                                        <form method="POST" action="{{ route('web.catalog.part-types.destroy', $partType['id']) }}">
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
                            <td colspan="5" class="empty-state">No part types found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $partTypes->links() }}
        </div>
    </section>
@endsection
