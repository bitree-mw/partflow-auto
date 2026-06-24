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
    <section class="report-filter-panel" data-report-filter-panel>
        <form method="GET" action="{{ route('web.reports.index') }}">
            <div>
                <span class="eyebrow">Date range first</span>
                <h2>Choose reporting period</h2>
                <p>CSV downloads use this date range and export full transaction fields, not summary cards.</p>
            </div>

            <label>
                From
                <input class="form-control" type="date" name="date_from" value="{{ request('date_from', $dateFrom) }}">
            </label>

            <label>
                To
                <input class="form-control" type="date" name="date_to" value="{{ request('date_to', $dateTo) }}">
            </label>

            <label>
                Report type
                <select class="form-control searchable-input" name="report_type">
                    @foreach ($reportCards as $report)
                        <option value="{{ $report['type'] }}" @selected(request('report_type') === $report['type'])>{{ $report['name'] }}</option>
                    @endforeach
                </select>
            </label>

            <button class="btn" type="submit">Apply filters</button>
        </form>
    </section>

    <section class="profit-loss-panel">
        <header>
            <div>
                <span class="eyebrow">Profit and loss accounts</span>
                <h2>P&L movement accounts</h2>
                <p>These accounts shape the profit and loss report before journal/accounting integration is connected.</p>
            </div>
            <a
                class="btn-secondary"
                href="{{ url('/api/reports/export') }}?report_type=profit-and-loss&date_from={{ request('date_from', $dateFrom) }}&date_to={{ request('date_to', $dateTo) }}"
            >Download P&L CSV</a>
        </header>

        <div class="profit-loss-grid">
            @foreach ($profitLossAccounts as $account)
                <article>
                    <span>{{ $account['type'] }}</span>
                    <strong>{{ $account['name'] }}</strong>
                    <em>{{ $account['movement'] }}</em>
                </article>
            @endforeach
        </div>
    </section>

    <section class="report-grid">
        @foreach ($reportCards as $report)
            <article class="report-card">
                <div>
                    <span class="eyebrow">{{ $report['status'] }}</span>
                    <h2>{{ $report['name'] }}</h2>
                    <p>{{ $report['detail'] }}</p>
                </div>
                <a
                    class="btn-secondary"
                    href="{{ url('/api/reports/export') }}?report_type={{ $report['type'] }}&date_from={{ request('date_from', $dateFrom) }}&date_to={{ request('date_to', $dateTo) }}"
                >Download CSV</a>
            </article>
        @endforeach
    </section>
@endsection
