@extends('layouts.suadmin')

@section('actions')
    <a class="btn" href="{{ route('suadmin.sites.create') }}">Add site</a>
@endsection

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('suadmin.sites.index') }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, code or location..." aria-label="Search sites">
                <select name="type" aria-label="Filter by type">
                    <option value="">All types</option>
                    @foreach ($siteTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('suadmin.sites.index') }}">Reset</a>
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sites as $site)
                        <tr>
                            <td><strong>{{ $site->name }}</strong><br><span class="suadmin-muted">{{ $site->code }}</span></td>
                            <td>{{ $siteTypes[$site->type] ?? $site->type }}</td>
                            <td>
                                {{ $site->location ?: 'Not set' }}
                                @if ($site->address)
                                    <br><span class="suadmin-muted">{{ $site->address }}</span>
                                @endif
                            </td>
                            <td>{{ $site->phone ?: '—' }}</td>
                            <td>
                                {{ number_format((int) $site->stock_on_hand) }} units
                                <br><span class="suadmin-muted">{{ $site->stock_lines_count }} {{ \Illuminate\Support\Str::plural('part', $site->stock_lines_count) }} holding stock</span>
                            </td>
                            <td><span @class(['status-pill', 'inactive' => ! $site->is_active])>{{ $site->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn-secondary" href="{{ route('suadmin.sites.edit', $site) }}">Edit</a>
                                    <form method="POST" action="{{ route('suadmin.sites.status', $site) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $site->is_active ? 0 : 1 }}">
                                        <button class="btn-secondary" type="submit">{{ $site->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('suadmin.sites.destroy', $site) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            class="btn-secondary"
                                            type="submit"
                                            data-confirm-title="Delete site?"
                                            data-confirm="Delete &quot;{{ $site->name }}&quot;? It disappears from all site lists. Past sales, purchases and stock history are kept. Only allowed when the site has no stock and no open documents."
                                            data-confirm-label="Delete site"
                                        >Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state">No sites found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $sites->links() }}
        </div>
    </section>
@endsection
