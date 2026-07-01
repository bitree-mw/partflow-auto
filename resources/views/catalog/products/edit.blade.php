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
    <a class="btn-secondary" href="{{ route('web.catalog.products.index') }}">Back to catalogue</a>
@endsection

@section('content')
    @php
        $existingCompatibilityIds = $product->compatibilities->pluck('car_model_id')->map(fn ($id) => (string) $id)->values()->all();
        $selectedCompatibleCarModels = old('compatible_car_model_ids', $existingCompatibilityIds ?: ['']);
        $compatibilityNotes = old('compatibility_notes', $product->compatibilities->first()?->notes);
    @endphp

    <x-country-datalist id="part-origin-countries" :countries="$countries" />

    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.products.update', $product) }}">
        @csrf
        @method('PUT')

        <section class="form-section">
            <span class="eyebrow">Fitment</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="car_model_id">Main car model</label>
                    <select class="form-control" id="car_model_id" name="car_model_id" data-searchable-select required>
                        <option value="">Select vehicle fitment</option>
                        @foreach ($carModels as $carModel)
                            <option value="{{ $carModel['id'] }}" @selected((string) old('car_model_id', $product->car_model_id) === (string) $carModel['id'])>
                                {{ $carModel['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="car_model_id" />
                </div>

                <div class="form-field">
                    <label for="part_type_id">Part type</label>
                    <select class="form-control" id="part_type_id" name="part_type_id" data-searchable-select required>
                        <option value="">Select part type</option>
                        @foreach ($partTypes as $partType)
                            <option value="{{ $partType['id'] }}" @selected((string) old('part_type_id', $product->part_type_id) === (string) $partType['id'])>
                                {{ $partType['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="part_type_id" />
                </div>

                <div class="form-field">
                    <label for="fuel_type_id">Fuel type</label>
                    <select class="form-control" id="fuel_type_id" name="fuel_type_id">
                        <option value="">Universal or not set</option>
                        @foreach ($fuelTypes as $fuelType)
                            <option value="{{ $fuelType['id'] }}" @selected((string) old('fuel_type_id', $product->fuel_type_id) === (string) $fuelType['id'])>
                                {{ $fuelType['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="fuel_type_id" />
                </div>

                <div class="form-field">
                    <label for="part_country_of_origin">Part origin</label>
                    <input class="form-control searchable-input" id="part_country_of_origin" name="part_country_of_origin" value="{{ old('part_country_of_origin', $product->part_country_of_origin) }}" list="part-origin-countries" placeholder="Search country">
                    <x-form-error name="part_country_of_origin" />
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Product details</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="product_name">Product name</label>
                    <input class="form-control" id="product_name" name="product_name" value="{{ old('product_name', $product->product_name) }}">
                    <x-form-error name="product_name" />
                </div>

                <div class="form-field">
                    <label for="product_code">Product code</label>
                    <input class="form-control" id="product_code" name="product_code" value="{{ old('product_code', $product->product_code) }}" placeholder="BOS-TYCO14BP">
                    <x-form-error name="product_code" />
                </div>

                <div class="form-field">
                    <label for="brand_id">Brand</label>
                    <select class="form-control" id="brand_id" name="brand_id" data-searchable-select>
                        <option value="">No brand selected</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand['id'] }}" @selected((string) old('brand_id', $product->brand_id) === (string) $brand['id'])>
                                {{ $brand['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="brand_id" />
                </div>

                <div class="form-field">
                    <label for="tax_profile_id">Tax profile</label>
                    <select class="form-control" id="tax_profile_id" name="tax_profile_id">
                        <option value="">Use system default</option>
                        @foreach ($taxProfiles as $taxProfile)
                            <option value="{{ $taxProfile['id'] }}" @selected((string) old('tax_profile_id', $product->tax_profile_id) === (string) $taxProfile['id'])>
                                {{ $taxProfile['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <x-form-error name="tax_profile_id" />
                </div>

                <div class="form-field">
                    <label for="default_selling_price">Selling price</label>
                    <input class="form-control" id="default_selling_price" name="default_selling_price" type="number" min="0" step="0.01" value="{{ old('default_selling_price', $product->default_selling_price) }}">
                    <x-form-error name="default_selling_price" />
                </div>

                <div class="form-field">
                    <label for="default_low_stock_level">Low stock level</label>
                    <input class="form-control" id="default_low_stock_level" name="default_low_stock_level" type="number" min="0" value="{{ old('default_low_stock_level', $product->default_low_stock_level) }}">
                    <x-form-error name="default_low_stock_level" />
                </div>

                <div class="form-field">
                    <label for="pack_size">Pack size</label>
                    <input class="form-control" id="pack_size" name="pack_size" type="number" min="0.01" step="0.01" value="{{ old('pack_size', $product->pack_size) }}">
                    <x-form-error name="pack_size" />
                </div>

                <div class="form-field full">
                    <label for="pos_description">POS description</label>
                    <textarea class="form-control" id="pos_description" name="pos_description" rows="3">{{ old('pos_description', $product->pos_description) }}</textarea>
                    <x-form-error name="pos_description" />
                </div>

                <input type="hidden" name="is_active" value="0">
                <label class="checkbox-field full">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                    Active
                </label>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Compatibility</span>
            <div class="compatibility-panel">
                <div>
                    <strong>Other compatible car variants</strong>
                    <p>Add any extra vehicles that can use this same part. Main car model already covers the primary fitment.</p>
                </div>

                <div class="compatibility-variant-list" data-compatibility-list>
                    @foreach ($selectedCompatibleCarModels as $selectedCompatibleCarModel)
                        <div class="compatibility-variant-row">
                            <select class="form-control" name="compatible_car_model_ids[]" data-searchable-select>
                                <option value="">Select compatible vehicle</option>
                                @foreach ($carModels as $carModel)
                                    <option value="{{ $carModel['id'] }}" @selected((string) $selectedCompatibleCarModel === (string) $carModel['id'])>
                                        {{ $carModel['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn-secondary" type="button" data-remove-compatibility-variant>Remove</button>
                        </div>
                    @endforeach
                </div>
                <x-form-error name="compatible_car_model_ids" />

                <button class="btn-secondary compatibility-add-button" type="button" data-add-compatibility-variant>Add variant</button>

                <div class="compatibility-grid">
                    <textarea class="form-control" name="compatibility_notes" rows="3" placeholder="Fitment notes, exclusions, engine remarks, or trim differences">{{ $compatibilityNotes }}</textarea>
                    <x-form-error name="compatibility_notes" />
                </div>
            </div>
        </section>

        <div class="form-actions">
            <button class="btn" type="submit">Update part</button>
        </div>
    </form>
@endsection
