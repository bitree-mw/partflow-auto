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

    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.car-models.store') }}">
        @csrf

        <section class="form-section">
            <span class="eyebrow">Vehicle identity</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="car_make_id">Make</label>
                    <select class="form-control" id="car_make_id" name="car_make_id" data-car-make-select data-searchable-select required>
                        <option value="">Select make</option>
                        @foreach ($carMakes as $make)
                            <option value="{{ $make['id'] }}" @selected((string) old('car_make_id') === (string) $make['id'])>
                                {{ $make['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="car_make_id" />
                </div>

                <div class="form-field">
                    <label for="vehicle_model_id">Model</label>
                    <select class="form-control" id="vehicle_model_id" name="vehicle_model_id" data-vehicle-model-select data-searchable-select required>
                        <option value="">Select make first</option>
                        @foreach ($vehicleModels as $model)
                            <option value="{{ $model['id'] }}" data-car-make-id="{{ $model['car_make_id'] }}" data-year="{{ $model['year'] ?? '' }}" @selected((string) old('vehicle_model_id') === (string) $model['id'])>
                                {{ $model['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="vehicle_model_id" />
                </div>

                <div class="form-field">
                    <label for="year">Year</label>
                    <input class="form-control" id="year" name="year" type="number" value="{{ old('year') }}" placeholder="Select model first" data-model-year-input required>
                    <x-form-error name="year" />
                </div>

                <div class="form-field">
                    <label for="engine_size">Engine size (L)</label>
                    <input class="form-control" id="engine_size" name="engine_size" type="number" min="0" max="20" step="0.1" inputmode="decimal" value="{{ old('engine_size') }}" placeholder="1.3">
                    <x-form-error name="engine_size" />
                </div>

                <div class="form-field">
                    <label for="variant_name">Variant</label>
                    <input class="form-control" id="variant_name" name="variant_name" value="{{ old('variant_name') }}" placeholder="Hatchback">
                    <x-form-error name="variant_name" />
                </div>

                <div class="form-field">
                    <label for="country_of_origin">Country of origin</label>
                    <input class="form-control searchable-input" id="country_of_origin" name="country_of_origin" value="{{ old('country_of_origin') }}" list="car-model-origin-countries" placeholder="Search country">
                    <x-form-error name="country_of_origin" />
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Notes</span>
            <div class="form-field full">
                <label for="notes">Fitment notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>
                <x-form-error name="notes" />
            </div>
        </section>

        <div class="form-actions">
            <button class="btn" type="submit">Save car model</button>
        </div>
    </form>
@endsection
