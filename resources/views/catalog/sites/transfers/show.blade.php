@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.sites.transfers.index') }}">All transfers</a>
    <a class="btn" href="{{ route('web.catalog.sites.transfers.create') }}">New transfer</a>
@endsection

@section('content')
    <section class="data-panel site-document-summary">
        <article>
            <span>Transfer number</span>
            <strong>{{ $document->document_number }}</strong>
        </article>
        <article>
            <span>Date</span>
            <strong>{{ $document->document_date?->format('M j, Y') }}</strong>
        </article>
        <article>
            <span>From</span>
            <strong>{{ $document->sourceSite?->name ?? 'Not set' }}</strong>
        </article>
        <article>
            <span>To</span>
            <strong>{{ $document->destinationSite?->name ?? 'Not set' }}</strong>
        </article>
    </section>

    <section class="data-panel">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th>Code</th>
                        <th>Quantity moved</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($document->items as $item)
                        <tr>
                            <td><strong>{{ $item->product?->product_name }}</strong></td>
                            <td>{{ $item->product?->product_code }}</td>
                            <td>{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
