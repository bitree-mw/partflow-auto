@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/reports.css')
@endpush

@push('scripts')
    @vite('resources/js/reports.js')
@endpush

@section('content')
    @php
        $reportFilters = [
            'date_from' => request('date_from', $dateFrom),
            'date_to' => request('date_to', $dateTo),
            'site_id' => $selectedSiteId,
        ];
        $filteredReportParams = array_filter($reportFilters, fn ($value) => filled($value));
    @endphp

    <section class="report-filter-panel" data-report-filter-panel>
        <form method="GET" action="{{ route('web.reports.index') }}">
            <div>
                <span class="eyebrow">Report centre</span>
                <h2>Choose reporting period</h2>
                <p>Preview records in the system or download every matching row for {{ $selectedBranchName }}.</p>
            </div>

            <label>
                Period
                <select class="form-control" name="period" data-report-period>
                    <option value="today" @selected(request('period') === 'today')>Today</option>
                    <option value="week" @selected(request('period') === 'week')>This week</option>
                    <option value="month" @selected(request('period', 'month') === 'month')>This month</option>
                    <option value="year" @selected(request('period') === 'year')>This year</option>
                    <option value="custom" @selected(request('period') === 'custom')>Custom range</option>
                </select>
            </label>

            <label>
                From
                <input class="form-control" type="date" name="date_from" value="{{ request('date_from', $dateFrom) }}" data-report-date-from>
            </label>

            <label>
                To
                <input class="form-control" type="date" name="date_to" value="{{ request('date_to', $dateTo) }}" data-report-date-to>
            </label>

            <label>
                Branch
                <select class="form-control searchable-input" name="site_id">
                    <option value="">All branches</option>
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch['id'] }}" @selected((string) $selectedSiteId === (string) $branch['id'])>
                            {{ $branch['name'] }}
                        </option>
                    @endforeach
                </select>
            </label>

            <button class="btn" type="submit">Apply filters</button>
        </form>
    </section>

    <section class="report-grid" aria-label="Available reports">
        @foreach ($reportCards as $index => $report)
            <article class="report-card">
                <div>
                    <span class="report-card-number" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="eyebrow">{{ $report['status'] }}</span>
                    <h2>{{ $report['name'] }}</h2>
                    <p>{{ $report['detail'] }}</p>
                </div>
                <div class="report-card-actions">
                    <a
                        class="btn-secondary"
                        href="{{ route('web.reports.view', array_merge($filteredReportParams, ['report_type' => $report['type']])) }}"
                    >View report</a>
                    @feature('csv_exports')
                        <a
                            class="btn-secondary"
                            href="{{ route('web.reports.export', array_merge($filteredReportParams, ['report_type' => $report['type']])) }}"
                        >Download full CSV</a>
                    @endfeature
                </div>
            </article>
        @endforeach
    </section>
@endsection
