@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    @if (auth()->user()?->hasPermission('catalogue.view'))
        <a class="btn-secondary" href="{{ route('web.catalog.products.index') }}">Parts catalogue</a>
    @endif
    @if (auth()->user()?->hasPermission('sales.create'))
        <a class="btn" href="{{ route('web.pos') }}" target="_blank" rel="noopener">New sale</a>
    @endif
@endsection

@section('content')
    <section class="catalogue-hub">
        <div class="catalogue-summary site-summary">
            @foreach ($summary as $item)
                <article>
                    <span>{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                    <p>{{ $item['detail'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="dashboard-grid secondary site-workflows">
        <article class="data-panel site-workflow-panel">
            <header class="settings-header">
                <span class="eyebrow">Warehouse movement</span>
                <h2>Transfer stock</h2>
                <p>Move multiple parts from a source site to a destination site. The system checks available quantities before stock is moved.</p>
            </header>
            <div class="site-workflow-actions">
                @if (auth()->user()?->hasPermission('stock.transfer'))
                    <a class="btn" href="{{ route('web.catalog.sites.transfers.create') }}">New transfer</a>
                @endif
                <a class="btn-secondary" href="{{ route('web.catalog.sites.transfers.index') }}">View transfers</a>
            </div>
        </article>

        <article class="data-panel site-workflow-panel">
            <header class="settings-header">
                <span class="eyebrow">Stock control</span>
                <h2>Stock take</h2>
                <p>Count multiple catalogue parts at one site, compare against system stock, and review the variance report after posting.</p>
            </header>
            <div class="site-workflow-actions">
                @if (auth()->user()?->hasPermission('stock.adjust'))
                    <a class="btn" href="{{ route('web.catalog.sites.stock-takes.create') }}">New stock take</a>
                @endif
                <a class="btn-secondary" href="{{ route('web.catalog.sites.stock-takes.index') }}">View stock takes</a>
            </div>
        </article>
    </section>

    <section class="data-panel">
        <div class="panel-toolbar">
            <form class="filter-form" method="GET" action="{{ route('web.catalog.sites.index') }}">
                <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search sites..." aria-label="Search sites">
                <select name="type" aria-label="Filter by type">
                    <option value="">All types</option>
                    <option value="branch" @selected(($filters['type'] ?? '') === 'branch')>Branch</option>
                    <option value="warehouse" @selected(($filters['type'] ?? '') === 'warehouse')>Warehouse</option>
                    <option value="shop" @selected(($filters['type'] ?? '') === 'shop')>Shop</option>
                </select>
                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
                <button class="btn-secondary" type="submit">Filter</button>
                <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Reset</a>
                <x-page-size-controls />
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Stock lines</th>
                        <th>Units</th>
                        <th>Documents</th>
                        <th>Status</th>
                        @if ($canManageSites)
                            <th style="text-align: right;">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sites as $site)
                        <tr>
                            <td><strong>{{ $site['name'] }}</strong><br>{{ $site['code'] }}</td>
                            <td>{{ $site['type'] }}</td>
                            <td>{{ $site['location'] }}</td>
                            <td>{{ $site['stock_items'] }}</td>
                            <td>{{ $site['stock_on_hand'] }}</td>
                            <td>{{ $site['documents'] }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => ! $site['is_active']])>{{ $site['status'] }}</span>
                            </td>
                            @if ($canManageSites)
                                <td>
                                    <div class="row-actions">
                                        <a class="icon-action icon-edit" href="{{ route('web.catalog.sites.edit', $site['id']) }}" title="Edit site" aria-label="Edit {{ $site['name'] }}">
                                            <x-icons.pencil />
                                        </a>
                                        <form method="POST" action="{{ route('web.catalog.sites.status', $site['id']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $site['is_active'] ? 0 : 1 }}">
                                            @if ($site['is_active'])
                                                <button
                                                    class="btn-secondary"
                                                    type="submit"
                                                    data-confirm-title="Deactivate site?"
                                                    data-confirm="Deactivate &quot;{{ $site['name'] }}&quot;? This is only allowed once all of its stock is gone."
                                                    data-confirm-label="Deactivate"
                                                >Deactivate</button>
                                            @else
                                                <button class="btn-secondary" type="submit">Activate</button>
                                            @endif
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManageSites ? 8 : 7 }}" class="empty-state">No sites found.</td>
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
