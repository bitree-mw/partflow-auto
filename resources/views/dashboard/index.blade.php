@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/dashboard.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.products.create') }}">Add part</a>
    <a class="btn" href="{{ route('web.pos') }}">New sale</a>
@endsection

@section('content')
    @php
        $metrics = collect($metrics);
        $currentSales = collect($currentSales);
        $mostSoldParts = collect($mostSoldParts);
        $salesTrend = collect($salesTrend);
        $branchPerformance = collect($branchPerformance);
        $branchSalesMix = collect($branchSalesMix);
        $branchOptions = collect($branchOptions);
        $lossRisks = collect($lossRisks);
        $stockAlerts = collect($stockAlerts);
        $primaryMetric = $metrics->first() ?? ['value' => 'MWK 0', 'change' => 'No sales today', 'trend' => 'neutral'];
        $lossMetric = $metrics->get(3) ?? ['value' => 'MWK 0'];
        $peakPart = $mostSoldParts->first() ?? ['part' => 'No sales yet', 'units' => 0];
        $pieStops = [];
        $pieCursor = 0;
        foreach ($branchSalesMix as $index => $slice) {
            $nextCursor = $index === $branchSalesMix->count() - 1 ? 100 : min(100, $pieCursor + $slice['share']);
            $pieStops[] = "{$slice['color']} {$pieCursor}% {$nextCursor}%";
            $pieCursor = $nextCursor;
        }
        $pieGradient = $pieStops ? 'conic-gradient('.implode(', ', $pieStops).')' : 'conic-gradient(#e5e7eb 0 100%)';
    @endphp

    <section class="dashboard-hero">
        <div>
            <span class="eyebrow">Live business pulse</span>
            <h2>Good day, {{ $greetingName }}.</h2>
            <p>Sales, profit, branch stock, low-stock warnings, and payment movement update from {{ $selectedBranchName }}.</p>
        </div>
        <div class="dashboard-hero-actions">
            <form class="branch-switcher" method="GET" action="{{ route('web.dashboard') }}">
                <label for="dashboard_site_id">Branch</label>
                <select id="dashboard_site_id" name="site_id" onchange="this.form.submit()">
                    <option value="">All branches</option>
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch['id'] }}" @selected((string) $selectedBranchId === (string) $branch['id'])>
                            {{ $branch['name'] }}
                        </option>
                    @endforeach
                </select>
            </form>
            <a class="btn-secondary" href="{{ route('web.reports.index') }}">Open reports</a>
        </div>
    </section>

    <section class="dashboard-metrics" aria-label="Business summary">
        @foreach ($metrics as $metric)
            <article class="metric-card {{ $metric['tone'] }}">
                <span>{{ $metric['label'] }}</span>
                <strong>{{ $metric['value'] }}</strong>
                <em @class(['metric-trend', $metric['trend'] ?? 'neutral'])>{{ $metric['change'] }}</em>
            </article>
        @endforeach
    </section>

    <section class="dashboard-grid secondary chart-row">
        <article class="insight-panel chart-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Sales analytics</span>
                    <h2>Weekly sales trend</h2>
                    <p>Daily sales movement for the active operating period.</p>
                </div>
            </header>

            <div class="bar-chart" aria-label="Weekly sales bar chart">
                @foreach ($salesTrend as $point)
                    <div class="bar-column">
                        <strong>{{ $point['value'] }}</strong>
                        <span style="--bar-height: {{ $point['height'] }}%;"></span>
                        <em>{{ $point['label'] }}</em>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="insight-panel chart-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Parts performance</span>
                    <h2>Top part velocity</h2>
                    <p>Most sold parts by unit movement.</p>
                </div>
            </header>

            <div class="horizontal-chart" aria-label="Top parts horizontal bar chart">
                @foreach ($mostSoldParts as $part)
                    <div>
                        <span>{{ $part['part'] }}</span>
                        <strong>{{ $part['units'] }} units</strong>
                        <em><b style="width: {{ $part['share'] }}%;"></b></em>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="dashboard-grid primary">
        <article class="insight-panel revenue-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Sales rhythm</span>
                    <h2>Revenue movement</h2>
                    <p>Most sold parts and branch contribution for today.</p>
                </div>
                <div class="segment-control" aria-label="Report range">
                    <button type="button" class="active">Today</button>
                    <button type="button">7 days</button>
                    <button type="button">30 days</button>
                </div>
            </header>

            <div class="revenue-summary">
                <div>
                    <span>Revenue</span>
                    <strong>{{ $primaryMetric['value'] }}</strong>
                    <em @class(['metric-trend', $primaryMetric['trend'] ?? 'neutral'])>{{ $primaryMetric['change'] }}</em>
                </div>
                <div>
                    <span>Peak part</span>
                    <strong>{{ $peakPart['part'] }}</strong>
                    <em>{{ $peakPart['units'] }} sold</em>
                </div>
                <div>
                    <span>Average sale</span>
                    <strong>{{ $averageSale }}</strong>
                    <em>Across active tills</em>
                </div>
            </div>

            <div class="top-parts-grid">
                @foreach ($mostSoldParts as $part)
                    <article>
                        <div>
                            <strong>{{ $part['part'] }}</strong>
                            <span>{{ $part['code'] }}</span>
                        </div>
                        <b>{{ $part['sales'] }}</b>
                        <em>{{ $part['profit'] }} profit</em>
                    </article>
                @endforeach
            </div>
        </article>

        <article class="money-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Money movement</span>
                    <h2>Where the money went</h2>
                    <p>Today</p>
                </div>
            </header>

            <div class="money-cards">
                <article>
                    <span>Incoming</span>
                    <strong>{{ $primaryMetric['value'] }}</strong>
                </article>
                <article class="loss">
                    <span>Loss exposure</span>
                    <strong>{{ $lossMetric['value'] }}</strong>
                </article>
            </div>
            <p class="money-note">Review returns, discount leakage, and stock variance before closing the day.</p>
            <a class="btn-secondary" href="{{ route('web.reports.index') }}">View payment report</a>
        </article>
    </section>

    <section class="dashboard-grid secondary">
        <article class="insight-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Live trading</span>
                    <h2>Current Sales</h2>
                </div>
            </header>

            <div class="stacked-list">
                @foreach ($currentSales->take(5) as $sale)
                    <div class="stacked-row">
                        <div>
                            <strong>{{ $sale['invoice'] }}</strong>
                            <span>{{ $sale['branch'] }} &middot; {{ $sale['customer'] }}</span>
                        </div>
                        <div>
                            <strong>{{ $sale['amount'] }}</strong>
                            <span>
                                {{ $sale['profit'] }} profit &middot;
                                <b @class(['inline-status', $sale['payment_tone'] ?? 'neutral'])>{{ $sale['status'] }}</b>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="insight-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Stock control</span>
                    <h2>Low Stock Alerts</h2>
                </div>
            </header>

            <div class="stacked-list">
                @foreach ($stockAlerts->take(5) as $alert)
                    <a href="{{ route('web.alerts.index') }}" @class(['stacked-row', 'warning', $alert['priority_tone'] ?? 'neutral'])>
                        <div>
                            <strong>{{ $alert['part'] }}</strong>
                            <span>{{ $alert['branch'] }}</span>
                        </div>
                        <div>
                            <strong>{{ $alert['available'] }}</strong>
                            <span>Recommended {{ $alert['recommended'] }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </article>
    </section>

    <section class="dashboard-grid secondary">
        <article class="insight-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Loss performance</span>
                    <h2>Risk Signals</h2>
                </div>
            </header>

            <div class="risk-list">
                @foreach ($lossRisks as $risk)
                    <div class="risk-item">
                        <span>{{ $risk['label'] }}</span>
                        <strong>{{ $risk['value'] }}</strong>
                        <p>{{ $risk['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="insight-panel">
            <header class="insight-header">
                <div>
                    <span class="eyebrow">Branch mix</span>
                    <h2>Sales Distribution</h2>
                    <p>Share of today sales for {{ $selectedBranchName }}.</p>
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
    </section>

    <section class="insight-panel branch-panel">
        <header class="insight-header">
            <div>
                <span class="eyebrow">Branches</span>
                <h2>Sales And Profit By Branch</h2>
                <p>Comparing today across all branches. Selected branch is highlighted.</p>
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
@endsection
