@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/catalog.css')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Back to sites</a>
@endsection

@section('content')
    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.sites.store') }}">
        @csrf

        <section class="form-section">
            <span class="eyebrow">Stock location</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="name">Site name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="Kanengo Warehouse" required>
                    <x-form-error name="name" />
                </div>

                <div class="form-field">
                    <label for="code">Site code</label>
                    <input class="form-control" id="code" name="code" value="{{ old('code') }}" placeholder="Auto generated if blank">
                    <x-form-error name="code" />
                </div>

                <div class="form-field">
                    <label for="type">Site type</label>
                    <select class="form-control" id="type" name="type" required>
                        <option value="">Select type</option>
                        @foreach ($siteTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-form-error name="type" />
                </div>

                <div class="form-field">
                    <label for="location">Location</label>
                    <input class="form-control" id="location" name="location" value="{{ old('location') }}" placeholder="Lilongwe">
                    <x-form-error name="location" />
                </div>

                <div class="form-field">
                    <label for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+265...">
                    <x-form-error name="phone" />
                </div>

                <div class="form-field full">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3">{{ old('address') }}</textarea>
                    <x-form-error name="address" />
                </div>
            </div>
        </section>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('web.catalog.sites.index') }}">Cancel</a>
            <button class="btn" type="submit">Save site</button>
        </div>
    </form>
@endsection
