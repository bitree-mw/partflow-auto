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
                    </tr>
                </thead>
                <tbody>
                    @foreach ($alerts as $alert)
                        <tr>
                            <td><strong>{{ $alert['type'] }}</strong></td>
                            <td>{{ $alert['item'] }}</td>
                            <td>{{ $alert['branch'] }}</td>
                            <td>{{ $alert['detail'] }}</td>
                            <td><span @class(['status-pill', $alert['priority_tone'] ?? 'neutral'])>{{ $alert['priority'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
