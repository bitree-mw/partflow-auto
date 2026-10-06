@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/reports.css')
@endpush

@section('content')
    @php
        $downloadParams = array_filter([
            ...$reportFilters,
            'report_type' => $report['type'],
        ], fn ($value) => filled($value));
        $perPageParams = array_filter([
            ...request()->except(['page', 'per_page']),
            'report_type' => $report['type'],
        ], fn ($value) => filled($value));
    @endphp

    <section class="data-panel report-view-panel" data-report-table>
        <div class="panel-toolbar report-view-toolbar">
            <div>
                <span class="eyebrow">Report table</span>
                <h2>{{ $report['title'] }}</h2>
                <p>
                    {{ $selectedBranchName }} · {{ $reportFilters['date_from'] ?? 'Any date' }} to {{ $reportFilters['date_to'] ?? 'Any date' }}
                </p>
                <p>
                    Showing {{ $report['rows']->firstItem() ?? 0 }}–{{ $report['rows']->lastItem() ?? 0 }} of {{ $report['rows']->total() }} rows.
                </p>
            </div>
            <div class="report-view-actions">
                <a class="btn-secondary" href="{{ route('web.reports.index') }}">Report centre</a>
                @feature('csv_exports')
                    <a class="btn-secondary" href="{{ route('web.reports.export', $downloadParams) }}">Download full CSV</a>
                @endfeature
            </div>
        </div>

        <form class="report-page-size" method="GET" action="{{ route('web.reports.view') }}" data-ignore-unsaved-changes>
            @foreach ($perPageParams as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label for="report_per_page">Rows per page</label>
            <select class="form-control" id="report_per_page" name="per_page">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                @endforeach
            </select>
            <button class="btn-secondary" type="submit">Apply</button>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach ($report['columns'] as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            @foreach ($report['columns'] as $column)
                                <td>{{ data_get($row, $column['key']) ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(count($report['columns']), 1) }}" class="empty-state">No records match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($report['rows']->hasPages())
            <div class="pagination-wrap" data-report-pagination>
                {{ $report['rows']->links() }}
            </div>
        @endif
    </section>
@endsection
