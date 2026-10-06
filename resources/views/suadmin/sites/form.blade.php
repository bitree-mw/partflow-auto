@extends('layouts.suadmin')

@section('actions')
    <a class="btn-secondary" href="{{ route('suadmin.sites.index') }}">Back to sites</a>
@endsection

@section('content')
    <form class="form-panel" method="POST" action="{{ $site->exists ? route('suadmin.sites.update', $site) : route('suadmin.sites.store') }}">
        @csrf
        @if ($site->exists)
            @method('PUT')
        @endif

        <div class="form-grid">
            <div class="form-field">
                <label for="name">Site name</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $site->name) }}" placeholder="Kanengo Warehouse" required>
                <x-form-error name="name" />
            </div>

            <div class="form-field">
                <label for="code">Site code</label>
                <input class="form-control" id="code" name="code" value="{{ old('code', $site->code) }}" @if ($site->exists) required @else placeholder="Generated from the name if blank" @endif>
                <x-form-error name="code" />
            </div>

            <div class="form-field">
                <label for="type">Site type</label>
                <select class="form-control" id="type" name="type" required>
                    @foreach ($siteTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $site->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-form-error name="type" />
            </div>

            <div class="form-field">
                <label for="location">Location (town / city)</label>
                <input class="form-control" id="location" name="location" value="{{ old('location', $site->location) }}" placeholder="Lilongwe">
                <x-form-error name="location" />
            </div>

            <div class="form-field">
                <label for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" value="{{ old('phone', $site->phone) }}" placeholder="+265...">
                <x-form-error name="phone" />
            </div>

            <div class="form-field full">
                <label for="address">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3">{{ old('address', $site->address) }}</textarea>
                <x-form-error name="address" />
            </div>
        </div>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ route('suadmin.sites.index') }}">Cancel</a>
            <button class="btn" type="submit">{{ $site->exists ? 'Save changes' : 'Create site' }}</button>
        </div>
    </form>
@endsection
