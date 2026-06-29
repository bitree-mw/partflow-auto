@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Site management</a>
    <a class="btn" href="{{ route('web.catalog.sites.transfers.create') }}">New transfer</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.catalog.sites.transfers.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search transfer or part..." aria-label="Search transfers">
                <select name="site_id" aria-label="Filter by site">
                    <option value="">All sites</option>
                    @foreach ($siteOptions as $site)
                        <option value="{{ $site['id'] }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site['id'])>{{ $site['label'] }}</option>
                    @endforeach
                </select>
                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.sites.transfers.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transfer</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Lines</th>
                        <th>Units</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td><strong>{{ $document['number'] }}</strong></td>
                            <td>{{ $document['date'] }}</td>
                            <td>{{ $document['source'] }}</td>
                            <td>{{ $document['destination'] }}</td>
                            <td>{{ $document['items'] }}</td>
                            <td>{{ $document['units'] }}</td>
                            <td><span class="status-pill">{{ $document['status'] }}</span></td>
                            <td><a class="btn-secondary" href="{{ route('web.catalog.sites.transfers.show', $document['id']) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">No transfers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $documents->links() }}
        </div>
    </section>
@endsection
