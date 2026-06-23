@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/operations.css')
@endpush

@section('content')
    <section class="data-panel">
        <div class="panel-toolbar">
            <strong>Active alerts</strong>
            <x-page-size-controls />
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
