@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.product-types.index') }}">Back to product types</a>
@endsection

@section('content')
    <form
        class="form-panel catalog-form"
        method="POST"
        action="{{ route('web.catalog.product-types.update', $productType) }}"
        data-confirm-title="Save product type changes?"
        data-confirm="Save the changes made to this product type?"
        data-confirm-label="Save changes"
    >
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Product type name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $productType->name) }}" required>
                <x-form-error name="name" />
            </div>

            <div class="form-field">
                <label for="code">Product type code</label>
                <input class="form-control" id="code" name="code" value="{{ old('code', $productType->code) }}" required>
                <x-form-error name="code" />
            </div>

            <div class="form-field full">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $productType->description) }}</textarea>
                <x-form-error name="description" />
            </div>

            <input type="hidden" name="is_active" value="0">
            <label class="checkbox-field full">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $productType->is_active))>
                Active
            </label>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Update product type</button>
        </div>
    </form>
@endsection
