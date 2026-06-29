@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@push('styles')
    @vite('resources/css/settings.css')
@endpush

@push('scripts')
    @vite('resources/js/settings.js')
@endpush

@section('header_actions')
    <a class="btn-secondary" href="{{ route('web.settings.index') }}#vehicle-library">Back to vehicle library</a>
@endsection

@section('content')
    <section class="settings-panel">
        <header class="settings-header">
            <span class="eyebrow">Vehicle make</span>
            <h2>{{ $carMake->name }} models</h2>
            <p>Add model names and year of make records used by vehicle identity dropdowns.</p>
        </header>

        <div class="settings-add-row">
            <form class="filter-form" method="GET" action="{{ route('web.settings.vehicle-makes.show', $carMake) }}">
                <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search models..." aria-label="Search models">
                <select name="is_active" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
                <button class="btn-secondary" type="submit">Search</button>
                <a class="btn-secondary" href="{{ route('web.settings.vehicle-makes.show', $carMake) }}">Reset</a>
            </form>
            <button class="btn" type="button" data-open-settings-dialog="vehicle-model">Add model</button>
        </div>

        <div class="site-list">
            @forelse ($models as $model)
                <article>
                    <div>
                        <strong>{{ $model['name'] }}</strong>
                        <span>{{ $model['code'] }} - Year {{ $model['year'] }} - {{ $model['body_style'] }} - {{ $model['linked_fitments'] }} fitments</span>
                    </div>
                    <em>{{ $model['status'] }}</em>
                    <div class="settings-row-actions">
                        <button
                            class="icon-action icon-edit"
                            type="button"
                            title="Edit model"
                            aria-label="Edit model"
                            data-open-settings-dialog="edit-vehicle-model"
                            data-settings-fill
                            data-vehicle-model-action="{{ route('web.settings.vehicle-models.update', $model['id']) }}"
                            data-vehicle-model-name="{{ $model['name'] }}"
                            data-vehicle-model-code="{{ $model['code'] }}"
                            data-vehicle-year="{{ $model['year'] === 'Not set' ? '' : $model['year'] }}"
                            data-vehicle-body-style="{{ $model['body_style'] === 'Not set' ? '' : $model['body_style'] }}"
                            data-vehicle-model-description="{{ $model['description'] }}"
                        >
                            <x-icons.pencil />
                        </button>
                        @if ($model['is_active'] && $model['linked_fitments'] === 0)
                            <form method="POST" action="{{ route('web.settings.vehicle-models.destroy', $model['id']) }}">
                                @csrf
                                @method('DELETE')
                                <button class="icon-action icon-danger" type="submit" title="Make inactive" aria-label="Make inactive">
                                    <x-icons.trash />
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <article>
                    <div>
                        <strong>No models found</strong>
                        <span>Add the first model for {{ $carMake->name }}.</span>
                    </div>
                </article>
            @endforelse
        </div>

        <div class="settings-pagination">
            {{ $models->links() }}
        </div>

        <dialog class="settings-dialog" data-settings-dialog="vehicle-model" aria-labelledby="settings-vehicle-model-title">
            <div class="settings-dialog-card">
                <header>
                    <span class="eyebrow">New model</span>
                    <h3 id="settings-vehicle-model-title">Add {{ $carMake->name }} model</h3>
                </header>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="vehicle_model_name">Model name</label>
                        <input class="form-control" id="vehicle_model_name" name="vehicle_model_name" form="add-model-form" value="{{ old('vehicle_model_name') }}" placeholder="Corolla">
                        <x-form-error name="vehicle_model_name" />
                    </div>
                    <div class="form-field">
                        <label for="vehicle_model_code">Model code</label>
                        <input class="form-control" id="vehicle_model_code" name="vehicle_model_code" form="add-model-form" value="{{ old('vehicle_model_code') }}" placeholder="CO">
                        <x-form-error name="vehicle_model_code" />
                    </div>
                    <div class="form-field">
                        <label for="vehicle_year">Year of make</label>
                        <input class="form-control" id="vehicle_year" name="vehicle_year" form="add-model-form" inputmode="numeric" value="{{ old('vehicle_year') }}" placeholder="2014">
                        <x-form-error name="vehicle_year" />
                    </div>
                    <div class="form-field">
                        <label for="vehicle_body_style">Body style</label>
                        <input class="form-control" id="vehicle_body_style" name="vehicle_body_style" form="add-model-form" value="{{ old('vehicle_body_style') }}" placeholder="Sedan">
                        <x-form-error name="vehicle_body_style" />
                    </div>
                    <div class="form-field full">
                        <label for="vehicle_model_description">Description</label>
                        <textarea class="form-control" id="vehicle_model_description" name="vehicle_model_description" form="add-model-form" rows="3">{{ old('vehicle_model_description') }}</textarea>
                        <x-form-error name="vehicle_model_description" />
                    </div>
                </div>
                <div class="settings-dialog-actions">
                    <button class="btn-secondary" type="button" data-close-settings-dialog>Cancel</button>
                    <button class="btn" type="submit" form="add-model-form">Save model</button>
                </div>
            </div>
        </dialog>

        <dialog class="settings-dialog" data-settings-dialog="edit-vehicle-model" aria-labelledby="settings-edit-vehicle-model-title">
            <div class="settings-dialog-card">
                <header>
                    <span class="eyebrow">Edit model</span>
                    <h3 id="settings-edit-vehicle-model-title">Update model</h3>
                </header>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="edit_vehicle_model_name">Model name</label>
                        <input class="form-control" id="edit_vehicle_model_name" name="vehicle_model_name" form="edit-model-form" data-settings-field="vehicleModelName" placeholder="Corolla">
                    </div>
                    <div class="form-field">
                        <label for="edit_vehicle_model_code">Model code</label>
                        <input class="form-control" id="edit_vehicle_model_code" name="vehicle_model_code" form="edit-model-form" data-settings-field="vehicleModelCode" placeholder="CO">
                    </div>
                    <div class="form-field">
                        <label for="edit_vehicle_year">Year of make</label>
                        <input class="form-control" id="edit_vehicle_year" name="vehicle_year" form="edit-model-form" data-settings-field="vehicleYear" inputmode="numeric" placeholder="2014">
                    </div>
                    <div class="form-field">
                        <label for="edit_vehicle_body_style">Body style</label>
                        <input class="form-control" id="edit_vehicle_body_style" name="vehicle_body_style" form="edit-model-form" data-settings-field="vehicleBodyStyle" placeholder="Sedan">
                    </div>
                    <div class="form-field full">
                        <label for="edit_vehicle_model_description">Description</label>
                        <textarea class="form-control" id="edit_vehicle_model_description" name="vehicle_model_description" form="edit-model-form" data-settings-field="vehicleModelDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="settings-dialog-actions">
                    <button class="btn-secondary" type="button" data-close-settings-dialog>Cancel</button>
                    <button class="btn" type="submit" form="edit-model-form">Update model</button>
                </div>
            </div>
        </dialog>
    </section>

    <form id="add-model-form" method="POST" action="{{ route('web.settings.vehicle-makes.models.store', $carMake) }}">
        @csrf
    </form>

    <form id="edit-model-form" method="POST" action="#" data-dynamic-action>
        @csrf
        @method('PUT')
    </form>
@endsection
