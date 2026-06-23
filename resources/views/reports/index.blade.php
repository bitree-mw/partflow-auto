@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/operations.css')
@endpush

@section('content')
    <section class="report-grid">
        @foreach ($reportCards as $report)
            <article class="report-card">
                <div>
                    <span class="eyebrow">{{ $report['status'] }}</span>
                    <h2>{{ $report['name'] }}</h2>
                    <p>{{ $report['detail'] }}</p>
                </div>
                <a class="btn-secondary" href="#">Open</a>
            </article>
        @endforeach
    </section>
@endsection
