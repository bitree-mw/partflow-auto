@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
    'kicker' => 'Tuesday operations',
])

@push('styles')
    @vite('resources/css/dashboard.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.products.create') }}">Add part</a>
    <a class="btn" href="{{ route('web.pos') }}">New sale</a>
@endsection

@section('content')
    <section class="dashboard-hero">
        <div>
            <span class="eyebrow">Live business pulse</span>
            <h2>Good day, System.</h2>
            <p>Sales, profit, branch stock, low-stock warnings, and payment movement update from your operating records.</p>
        </div>
        <a class="btn-secondary" href="#">Open reports</a>
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
                    <strong>{{ $metrics[0]['value'] }}</strong>
                    <em @class(['metric-trend', $metrics[0]['trend'] ?? 'neutral'])>{{ $metrics[0]['change'] }}</em>
                </div>
                <div>
                    <span>Peak part</span>
                    <strong>{{ $mostSoldParts[0]['part'] }}</strong>
                    <em>{{ $mostSoldParts[0]['units'] }} sold</em>
                </div>
                <div>
                    <span>Average sale</span>
                    <strong>MWK 94K</strong>
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
                    <strong>{{ $metrics[0]['value'] }}</strong>
                </article>
                <article class="loss">
                    <span>Loss exposure</span>
                    <strong>{{ $metrics[3]['value'] }}</strong>
                </article>
            </div>
            <p class="money-note">Review returns, discount leakage, and stock variance before closing the day.</p>
            <a class="btn-secondary" href="#">View payment report</a>
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
                @foreach ($currentSales as $sale)
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
                @foreach ($stockAlerts as $alert)
                    <div @class(['stacked-row', 'warning', $alert['priority_tone'] ?? 'neutral'])>
                        <div>
                            <strong>{{ $alert['part'] }}</strong>
                            <span>{{ $alert['branch'] }}</span>
                        </div>
                        <div>
                            <strong>{{ $alert['available'] }}</strong>
                            <span>Recommended {{ $alert['recommended'] }}</span>
                        </div>
                    </div>
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
                    <span class="eyebrow">Branch movement</span>
                    <h2>Stockouts By Branch</h2>
                </div>
            </header>

            <div class="branch-alert-list">
                @foreach ($branchPerformance as $branch)
                    <div>
                        <strong>{{ $branch['branch'] }}</strong>
                        <span>{{ $branch['stockouts'] }} stockouts</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="insight-panel branch-panel">
        <header class="insight-header">
            <div>
                <span class="eyebrow">Branches</span>
                <h2>Sales And Profit By Branch</h2>
            </div>
        </header>

        <div class="branch-performance">
            @foreach ($branchPerformance as $branch)
                <article>
                    <span>{{ $branch['branch'] }}</span>
                    <strong>{{ $branch['sales'] }}</strong>
                    <p @class(['margin-line', $branch['margin_tone'] ?? 'neutral'])>{{ $branch['profit'] }} profit &middot; {{ $branch['margin'] }} margin</p>
                    <em>{{ $branch['stockouts'] }} stockouts</em>
                </article>
            @endforeach
        </div>
    </section>
@endsection
