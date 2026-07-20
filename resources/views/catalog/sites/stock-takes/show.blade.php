@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.sites.stock-takes.index') }}">All stock takes</a>
    <a class="btn" href="{{ route('web.catalog.sites.stock-takes.create') }}">New stock take</a>
@endsection

@section('content')
    <section class="data-panel site-document-summary">
        <article>
            <span>Stock take</span>
            <strong>{{ $document->document_number }}</strong>
        </article>
        <article>
            <span>Date</span>
            <strong>{{ $document->document_date?->format('M j, Y') }}</strong>
        </article>
        <article>
            <span>Site</span>
            <strong>{{ $document->sourceSite?->name ?? 'Not set' }}</strong>
        </article>
        <article>
            <span>Variance lines</span>
            <strong>{{ $varianceCount }}</strong>
        </article>
    </section>

    <section class="data-panel">
        <header class="settings-header site-document-report-header">
            <span class="eyebrow">Variance report</span>
            <h2>{{ $varianceCount > 0 ? 'Variance found' : 'No variance found' }}</h2>
            <p>System quantities were captured before the count adjustment was posted.</p>
        </header>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th>Code</th>
                        <th>System</th>
                        <th>Counted</th>
                        <th>Variance</th>
                        <th>Adjustment reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($document->items as $item)
                        <tr>
                            <td><strong>{{ $item->product?->product_name }}</strong></td>
                            <td>{{ $item->product?->product_code }}</td>
                            <td>{{ $item->system_quantity }}</td>
                            <td>{{ $item->counted_quantity }}</td>
                            <td>
                                <span @class(['status-pill', 'inactive' => $item->variance_quantity < 0])>
                                    {{ $item->variance_quantity > 0 ? '+' : '' }}{{ $item->variance_quantity }}
                                </span>
                            </td>
                            <td>{{ $item->variance_quantity !== 0 ? $item->notes : 'No adjustment' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
