@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.fuel-types.index') }}">Back to fuel types</a>
@endsection

@section('content')
    <form
        class="form-panel catalog-form"
        method="POST"
        action="{{ route('web.catalog.fuel-types.update', $fuelType) }}"
        data-confirm-title="Save fuel type changes?"
        data-confirm="Save the changes made to this fuel type?"
        data-confirm-label="Save changes"
    >
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Fuel type name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $fuelType->name) }}" required>
                <x-form-error name="name" />
            </div>

            <div class="form-field">
                <label for="code">Fuel type code</label>
                <input class="form-control" id="code" name="code" value="{{ old('code', $fuelType->code) }}">
                <x-form-error name="code" />
            </div>

            <div class="form-field full">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $fuelType->description) }}</textarea>
                <x-form-error name="description" />
            </div>

            <input type="hidden" name="is_active" value="0">
            <label class="checkbox-field full">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $fuelType->is_active))>
                Active
            </label>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Update fuel type</button>
        </div>
    </form>
@endsection
