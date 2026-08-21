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
    @php
        $engineSizeValue = old('engine_size', $carModel->engine_size);
        $engineSizeValue = $engineSizeValue ? preg_replace('/[^0-9.]/', '', $engineSizeValue) : '';
        $engineSizeValue = $engineSizeValue !== '' && (float) $engineSizeValue > 0 && (float) $engineSizeValue < 100
            ? rtrim(rtrim(number_format((float) $engineSizeValue * 1000, 1, '.', ''), '0'), '.')
            : $engineSizeValue;
    @endphp

    <form
        class="form-panel catalog-form"
        method="POST"
        action="{{ route('web.catalog.car-models.update', $carModel) }}"
        data-track-unsaved-changes
        data-confirm-title="Save car model changes?"
        data-confirm="Save the changes made to this car model?"
        data-confirm-label="Save changes"
    >
        @csrf
        @method('PUT')

        <section class="form-section">
            <span class="eyebrow">Vehicle identity</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="car_make_id">Make</label>
                    <select class="form-control" id="car_make_id" name="car_make_id" data-car-make-select data-searchable-select required>
                        <option value="">Select make</option>
                        @foreach ($carMakes as $make)
                            <option value="{{ $make['id'] }}" @selected((string) old('car_make_id', $carModel->car_make_id) === (string) $make['id'])>
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
                            <option value="{{ $model['id'] }}" data-car-make-id="{{ $model['car_make_id'] }}" data-year="{{ $model['year'] ?? '' }}" @selected((string) old('vehicle_model_id', $carModel->vehicle_model_id) === (string) $model['id'])>
                                {{ $model['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="vehicle_model_id" />
                </div>

                <div class="form-field">
                    <label for="year">Year</label>
                    <input class="form-control" id="year" name="year" type="number" value="{{ old('year', $carModel->year) }}" data-model-year-input required>
                    <x-form-error name="year" />
                </div>

                <div class="form-field">
                    <label for="engine_size">Engine size (cc)</label>
                    <input class="form-control" id="engine_size" name="engine_size" type="number" min="0" max="20000" step="0.1" inputmode="decimal" value="{{ $engineSizeValue }}" placeholder="1800">
                    <x-form-error name="engine_size" />
                </div>

                <div class="form-field">
                    <label for="variant_name">Variant identifier</label>
                    <input class="form-control" id="variant_name" name="variant_name" value="{{ old('variant_name', $carModel->variant_name) }}" placeholder="C200, C220D, NZE, TDCi" required>
                    <x-form-error name="variant_name" />
                </div>

                <div class="form-field">
                    <label for="country_of_origin">Country of origin</label>
                    <input class="form-control searchable-input" id="country_of_origin" name="country_of_origin" value="{{ old('country_of_origin', $carModel->country_of_origin) }}" list="car-model-origin-countries" placeholder="Search country">
                    <x-form-error name="country_of_origin" />
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Notes</span>
            <div class="form-field full">
                <label for="notes">Fitment notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $carModel->notes) }}</textarea>
                <x-form-error name="notes" />
            </div>
        </section>

        <input type="hidden" name="is_active" value="0">
        <label class="checkbox-field">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $carModel->is_active))>
            Active
        </label>

        <div class="form-actions">
            <button class="btn" type="submit">Update car model</button>
        </div>
    </form>
@endsection
