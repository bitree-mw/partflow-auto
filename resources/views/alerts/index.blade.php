@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/alerts.css')
@endpush

@push('scripts')
    @vite('resources/js/alerts.js')
@endpush

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Active alerts</strong>
            <div class="table-tools">
                <input class="table-search" type="search" data-table-search placeholder="Search alerts..." aria-label="Search alerts table">
                <x-page-size-controls />
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Alert</th>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Detail</th>
                        <th>Priority</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alerts as $alert)
                        <tr>
                            <td><strong>{{ $alert['type'] }}</strong></td>
                            <td>
                                @if (! empty($alert['review_url']))
                                    <a class="table-link" href="{{ $alert['review_url'] }}">{{ $alert['item'] }}</a>
                                @else
                                    {{ $alert['item'] }}
                                @endif
                            </td>
                            <td>{{ $alert['branch'] }}</td>
                            <td>{{ $alert['detail'] }}</td>
                            <td><span @class(['status-pill', $alert['priority_tone'] ?? 'neutral'])>{{ $alert['priority'] }}</span></td>
                            <td>
                                <div class="row-actions">
                                    @if (! empty($alert['review_url']))
                                        <a class="btn-secondary" href="{{ $alert['review_url'] }}">Review</a>
                                    @endif
                                    @if (! empty($alert['purchase_url']))
                                        <a class="btn" href="{{ $alert['purchase_url'] }}">Purchase item</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">No active alerts right now.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
