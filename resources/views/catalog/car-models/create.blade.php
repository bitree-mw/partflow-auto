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
    <a class="btn-secondary" href="{{ route('web.catalog.car-models.index') }}">Back to car models</a>
@endsection

@section('content')
    <x-country-datalist id="car-model-origin-countries" :countries="$countries" />
    <datalist id="car-fuel-type-options">
        <option value="Petrol"></option>
        <option value="Diesel"></option>
        <option value="Hybrid"></option>
        <option value="Electric"></option>
        <option value="Plug-in Hybrid"></option>
        <option value="Universal"></option>
    </datalist>

    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.car-models.store') }}">
        @csrf

        <section class="form-section">
            <span class="eyebrow">Vehicle identity</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="make">Make</label>
                    <input class="form-control" id="make" name="make" placeholder="Toyota">
                </div>

                <div class="form-field">
                    <label for="model">Model</label>
                    <input class="form-control" id="model" name="model" placeholder="Demio">
                </div>

                <div class="form-field">
                    <label for="year">Year</label>
                    <input class="form-control" id="year" name="year" type="number" placeholder="2014">
                </div>

                <div class="form-field">
                    <label for="engine_size">Engine size</label>
                    <input class="form-control" id="engine_size" name="engine_size" placeholder="1.3L">
                </div>

                <div class="form-field">
                    <label for="variant_name">Variant</label>
                    <input class="form-control" id="variant_name" name="variant_name" placeholder="Hatchback">
                </div>

                <div class="form-field">
                    <label for="country_of_origin">Country of origin</label>
                    <input class="form-control searchable-input" id="country_of_origin" name="country_of_origin" list="car-model-origin-countries" placeholder="Search country">
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Fuel type setup</span>
            <div class="fuel-type-panel">
                <div class="form-field">
                    <label for="fuel_type">Fuel type for this model</label>
                    <input class="form-control searchable-input" id="fuel_type" name="fuel_type" list="car-fuel-type-options" placeholder="Search or type fuel type">
                </div>
                <div class="form-field">
                    <label for="new_fuel_type">Add new fuel type</label>
                    <input class="form-control" id="new_fuel_type" name="new_fuel_type" placeholder="Hydrogen, LPG, CNG">
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Notes</span>
            <div class="form-field full">
                <label for="notes">Fitment notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
            </div>
        </section>

        <div class="form-actions">
            <button class="btn" type="submit">Save car model</button>
        </div>
    </form>
@endsection
