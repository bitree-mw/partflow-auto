@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.part-types.index') }}">Back to part types</a>
@endsection

@section('content')
    <x-catalog-workflow current="part-types" />

    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.part-types.store') }}">
        @csrf

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Part type name</label>
                <input class="form-control" id="name" name="name" placeholder="Brake Pads">
            </div>

            <div class="form-field">
                <label for="code">Part type code</label>
                <input class="form-control" id="code" name="code" placeholder="BP">
            </div>

            <div class="form-field full">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Save part type</button>
        </div>
    </form>
@endsection
