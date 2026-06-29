@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Site management</a>
    <a class="btn" href="{{ route('web.catalog.sites.stock-takes.create') }}">New stock take</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.catalog.sites.stock-takes.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search stock take or part..." aria-label="Search stock takes">
                <select name="site_id" aria-label="Filter by site">
                    <option value="">All sites</option>
                    @foreach ($siteOptions as $site)
                        <option value="{{ $site['id'] }}" @selected((string) ($filters['site_id'] ?? '') === (string) $site['id'])>{{ $site['label'] }}</option>
                    @endforeach
                </select>
                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.sites.stock-takes.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Stock take</th>
                        <th>Date</th>
                        <th>Site</th>
                        <th>Lines</th>
                        <th>Variance lines</th>
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
                            <td>{{ $document['items'] }}</td>
                            <td>{{ $document['variance_count'] }}</td>
                            <td><span class="status-pill">{{ $document['status'] }}</span></td>
                            <td><a class="btn-secondary" href="{{ route('web.catalog.sites.stock-takes.show', $document['id']) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">No stock takes found.</td>
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
