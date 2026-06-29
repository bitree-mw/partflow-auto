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
    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.part-types.update', $partType) }}">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Part type name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $partType->name) }}" required>
                <x-form-error name="name" />
            </div>

            <div class="form-field">
                <label for="code">Part type code</label>
                <input class="form-control" id="code" name="code" value="{{ old('code', $partType->code) }}" required>
                <x-form-error name="code" />
            </div>

            <div class="form-field full">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $partType->description) }}</textarea>
                <x-form-error name="description" />
            </div>

            <input type="hidden" name="is_active" value="0">
            <label class="checkbox-field full">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $partType->is_active))>
                Active
            </label>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Update part type</button>
        </div>
    </form>
@endsection
