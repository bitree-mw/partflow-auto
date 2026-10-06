@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
    'bodyClass' => 'app-shell dashboard-page',
])

@push('styles')
    @vite('resources/css/dashboard.css')
@endpush

@push('scripts')
    @vite('resources/js/dashboard.js')
@endpush

@section('header_actions')
@endsection

@section('content')
    @php
        $metrics = collect($metrics);
        $salesTrend = collect($salesTrend);
        $branchPerformance = collect($branchPerformance);
        $branchSalesMix = collect($branchSalesMix);
        $inventoryValueComparison = collect($inventoryValueComparison);
        $balanceExposureComparison = collect($balanceExposureComparison);
        $branchOptions = collect($branchOptions);
        $priorityActions = collect($priorityActions);
        $pieStops = [];
        $pieCursor = 0;
        foreach ($branchSalesMix as $index => $slice) {
            $nextCursor = $index === $branchSalesMix->count() - 1 ? 100 : min(100, $pieCursor + $slice['share']);
            $pieStops[] = "{$slice['color']} {$pieCursor}% {$nextCursor}%";
            $pieCursor = $nextCursor;
        }
        $pieGradient = $pieStops ? 'conic-gradient('.implode(', ', $pieStops).')' : 'conic-gradient(#e5e7eb 0 100%)';
    @endphp

    <section class="dashboard-hero dashboard-prototype-hero">
        <div>
            <span class="eyebrow">{{ now()->format('l, j F') }}</span>
            <h2>Good day, {{ $greetingName }}</h2>
            <p>Here is what needs your attention at {{ $selectedBranchName }}.</p>
        </div>

        <div class="dashboard-hero-actions">
            @feature('multi_branch')
            <form class="branch-switcher" method="GET" action="{{ route('web.dashboard') }}">
                <label for="dashboard_site_id">Branch</label>
                <input type="hidden" name="revenue_period" value="{{ $revenuePeriod }}">
                <select id="dashboard_site_id" name="site_id" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch['id'] }}" @selected((string) $selectedBranchId === (string) $branch['id'])>
                            {{ $branch['name'] }}
                        </option>
                    @endforeach
                </select>
            </form>
            @endfeature

            @if (auth()->user()?->hasPermission('stock.adjust'))
                <a class="btn-secondary" href="{{ route('web.catalog.sites.stock-takes.create') }}">Stock count</a>
            @endif
            @if (auth()->user()?->hasPermission('catalogue.manage'))
                <a class="btn" href="{{ route('web.catalog.products.create') }}">Add new part</a>
            @endif
        </div>
    </section>

    <section
        class="dashboard-metrics"
        aria-label="Business summary"
        data-dashboard-live
        data-dashboard-live-url="{{ route('web.dashboard.live', request()->query()) }}"
    >
        @foreach ($metrics as $metric)
            <article
                class="metric-card {{ $metric['tone'] }}"
                @if ($metric['label'] === 'Today sales') data-dashboard-today-sales @endif
                @if ($metric['label'] === 'Today profit') data-dashboard-today-profit @endif
            >
                <header class="metric-card-heading">
                    <span class="metric-label">{{ $metric['label'] }}</span>
                    <span
                        @class(['metric-direction', $metric['trend'] ?? 'neutral'])
                        aria-hidden="true"
                    >
                        <x-icons.metric :name="$metric['icon'] ?? 'chart'" />
                    </span>
                </header>
                <strong data-dashboard-metric-value>{{ $metric['value'] }}</strong>
                <em @class(['metric-trend', $metric['trend'] ?? 'neutral'])>
                    <span aria-hidden="true">{{ ($metric['direction'] ?? 'flat') === 'down' ? '↓' : (($metric['direction'] ?? 'flat') === 'up' ? '↑' : '→') }}</span>
                    <span data-dashboard-metric-change>{{ $metric['change'] }}</span>
                </em>
            </article>
        @endforeach
    </section>

    <section class="dashboard-prototype-grid">
        <article class="insight-panel dashboard-performance-panel">
            <header class="insight-header">
                <div>
                    <h2>Revenue movement</h2>
                    <p>Sales revenue across the selected branches.</p>
                </div>
                <form class="dashboard-period-filter" method="GET" action="{{ route('web.dashboard') }}">
                    @if ($selectedBranchId)
                        <input type="hidden" name="site_id" value="{{ $selectedBranchId }}">
                    @endif
                    <label class="sr-only" for="revenue_period">Revenue period</label>
                    <select id="revenue_period" name="revenue_period" onchange="this.form.submit()">
                        @foreach ([7 => 'Last 7 days', 14 => 'Last 14 days', 30 => 'Last 30 days'] as $days => $label)
                            <option value="{{ $days }}" @selected($revenuePeriod === $days)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </header>

            <div class="revenue-chart-scroll">
                <div
                    class="bar-chart revenue-period-{{ $revenuePeriod }}"
                    style="--chart-columns: {{ max($salesTrend->count(), 1) }};"
                    aria-label="Revenue movement bar chart"
                    data-revenue-days="{{ $salesTrend->count() }}"
                >
                    @foreach ($salesTrend as $point)
                        <div class="bar-column" aria-label="{{ $point['label'] }} revenue {{ $point['value'] }}">
                            <strong @class(['chart-detail-muted' => ! $point['show_detail']])>{{ $point['value'] }}</strong>
                            <span style="--bar-height: {{ $point['height'] }}%;"></span>
                            <em @class(['chart-detail-muted' => ! $point['show_detail']])>{{ $point['label'] }}</em>
                        </div>
                    @endforeach
                </div>
            </div>

            <footer class="dashboard-chart-footer">
                <span><i></i> Sales revenue</span>
                <strong>{{ data_get($metrics->first(), 'change', 'Current operating period') }}</strong>
            </footer>
        </article>

        <article class="insight-panel attention-panel">
            <header class="insight-header">
                <div>
                    <h2>Priority actions</h2>
                    <p>Prioritised for today at {{ $selectedBranchName }}.</p>
                </div>
                <span class="attention-total">{{ $priorityActions->count() }} open</span>
            </header>

            <div class="attention-list" data-priority-action-list tabindex="0" aria-label="Priority actions">
                @forelse ($priorityActions as $task)
                    <article class="{{ $task['tone'] }}">
                        <span class="attention-mark" aria-hidden="true">{{ $task['mark'] }}</span>
                        <div>
                            <strong>{{ $task['title'] }}</strong>
                            <small>{{ $task['detail'] }}</small>
                        </div>
                        <a href="{{ route($task['route'], $task['route_parameters']) }}">{{ $task['action'] }}</a>
                    </article>
                @empty
                    <div class="dashboard-empty-state">No priority actions need attention right now.</div>
                @endforelse
            </div>
        </article>
    </section>

    {{-- One branch has nothing to distribute: the average-sale panel takes the full row instead. --}}
    <section class="dashboard-distribution-grid" @if ($singleBranch) style="grid-template-columns: 1fr;" @endif>
        <article class="insight-panel dashboard-side-metric">
            <div>
                <span class="eyebrow">Today’s sales</span>
                <h2>Average sale value</h2>
                <strong data-dashboard-average-sale>{{ $averageSale }}</strong>
            </div>
            <p>Average value per completed sale for {{ $selectedBranchName }} today.</p>
        </article>

        @unless ($singleBranch)
        <article class="insight-panel distribution-panel">
            <header class="insight-header">
                <div>
                    <h2>Sales distribution</h2>
                    <p>Share of today’s sales for {{ $selectedBranchName }}.</p>
                </div>
            </header>

            <div class="branch-pie-panel">
                <div class="branch-pie" style="background: {{ $pieGradient }};">
                    <span>{{ $branchSalesMix->isEmpty() ? 'No sales' : 'Sales' }}</span>
                </div>
                <div class="branch-pie-legend">
                    @forelse ($branchSalesMix as $slice)
                        <div @class(['selected' => $slice['selected']])>
                            <i style="background: {{ $slice['color'] }};"></i>
                            <span>{{ $slice['branch'] }}</span>
                            <strong>{{ $slice['share'] }}%</strong>
                        </div>
                    @empty
                        <p>No branch sales recorded for the selected view.</p>
                    @endforelse
                </div>
            </div>
        </article>
        @endunless
    </section>

    <section class="dashboard-analytics-grid" aria-label="Financial and inventory analytics">
        <article class="insight-panel analytics-comparison-panel">
            <header class="insight-header">
                <div>
                    <h2>Inventory value outlook</h2>
                    <p>Stock on hand valued at recorded purchase cost and current catalogue selling price.</p>
                </div>
            </header>

            <div class="analytics-bar-list">
                @foreach ($inventoryValueComparison as $row)
                    <article>
                        <div>
                            <span>{{ $row['label'] }}</span>
                            <strong>{{ $row['value'] }}</strong>
                        </div>
                        <div class="analytics-bar-track" aria-hidden="true">
                            <i class="{{ $row['tone'] }}" style="width: {{ $row['width'] }}%;"></i>
                        </div>
                    </article>
                @endforeach
            </div>

            <footer class="analytics-note">Purchase value uses the latest recorded purchase cost, falling back to the catalogue cost.</footer>
        </article>

        <article class="insight-panel analytics-comparison-panel">
            <header class="insight-header">
                <div>
                    @feature('customer_balances')
                        <h2>Debtors and creditors</h2>
                        <p>Recorded customer balances compared with unpaid supplier purchases.</p>
                    @else
                        <h2>Supplier balances</h2>
                        <p>Unpaid supplier purchases still to settle.</p>
                    @endfeature
                </div>
            </header>

            <div class="analytics-bar-list">
                @foreach ($balanceExposureComparison as $row)
                    <article>
                        <div>
                            <span>{{ $row['label'] }}</span>
                            <strong>{{ $row['value'] }}</strong>
                        </div>
                        <div class="analytics-bar-track" aria-hidden="true">
                            <i class="{{ $row['tone'] }}" style="width: {{ $row['width'] }}%;"></i>
                        </div>
                    </article>
                @endforeach
            </div>

            <footer class="analytics-note">Open recorded balances only; debtor aging and supplier due dates are not yet captured.</footer>
        </article>
    </section>

    @feature('multi_branch')
    <section class="insight-panel branch-panel">
        <header class="insight-header">
            <div>
                <h2>Sales and profit by branch</h2>
                <p>Comparing today across all branches. The selected branch is highlighted.</p>
            </div>
        </header>

        <div class="branch-comparison-chart">
            @foreach ($branchPerformance as $branch)
                <article @class(['selected' => $branch['selected']])>
                    <div class="branch-chart-label">
                        <strong>{{ $branch['branch'] }}</strong>
                        <span>{{ $branch['stockouts'] }} stockouts</span>
                    </div>
                    <div class="branch-chart-bars">
                        <div>
                            <span>Sales</span>
                            <em><b style="width: {{ $branch['sales_width'] }}%;"></b></em>
                            <strong>{{ $branch['sales'] }}</strong>
                        </div>
                        <div>
                            <span>Profit</span>
                            <em><b style="width: {{ $branch['profit_width'] }}%;"></b></em>
                            <strong>{{ $branch['profit'] }}</strong>
                        </div>
                    </div>
                    <p @class(['margin-line', $branch['margin_tone'] ?? 'neutral'])>{{ $branch['margin'] }} margin</p>
                </article>
            @endforeach
        </div>
    </section>
    @endfeature
@endsection
