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
    <a class="btn-secondary" href="{{ route('web.catalog.brands.index') }}">Back to brands</a>
@endsection

@section('content')
    <x-country-datalist id="brand-countries" :countries="$countries" />

    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.brands.store') }}">
        @csrf

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Brand name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="Denso" required>
                <x-form-error name="name" />
            </div>

            <div class="form-field">
                <label for="code">Brand code</label>
                <input class="form-control" id="code" name="code" value="{{ old('code') }}" placeholder="DNS">
                <x-form-error name="code" />
            </div>

            <div class="form-field full">
                <label for="country">Country</label>
                <input class="form-control searchable-input" id="country" name="country" value="{{ old('country') }}" list="brand-countries" placeholder="Search country">
                <x-form-error name="country" />
            </div>

            <div class="form-field full">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description') }}</textarea>
                <x-form-error name="description" />
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Save brand</button>
        </div>
    </form>
@endsection
