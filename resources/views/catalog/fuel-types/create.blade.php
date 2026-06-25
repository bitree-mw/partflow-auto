@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@push('scripts')
    @vite('resources/js/catalog.js')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.fuel-types.index') }}">Back to fuel types</a>
@endsection

@section('content')
    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.fuel-types.store') }}">
        @csrf

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Fuel type name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="Petrol" required>
                <x-form-error name="name" />
            </div>

            <div class="form-field">
                <label for="code">Fuel type code</label>
                <input class="form-control" id="code" name="code" value="{{ old('code') }}" placeholder="P">
                <x-form-error name="code" />
            </div>

            <div class="form-field full">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description') }}</textarea>
                <x-form-error name="description" />
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Save fuel type</button>
        </div>
    </form>
@endsection
