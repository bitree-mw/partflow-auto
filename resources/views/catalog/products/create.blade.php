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
    <x-country-datalist id="part-origin-countries" :countries="$countries" />

    <datalist id="car-model-options">
        @foreach ($carModels as $carModel)
            <option value="{{ $carModel['make'] }} {{ $carModel['model'] }} {{ $carModel['engine'] }} {{ $carModel['variant'] }} ({{ $carModel['year'] }})"></option>
        @endforeach
    </datalist>
    <datalist id="part-type-options">
        @foreach ($partTypes as $partType)
            <option value="{{ $partType }}"></option>
        @endforeach
    </datalist>
    <datalist id="fuel-type-options">
        @foreach ($fuelTypes as $fuelType)
            <option value="{{ $fuelType }}"></option>
        @endforeach
    </datalist>
    <datalist id="brand-options">
        @foreach ($brands as $brand)
            <option value="{{ $brand }}"></option>
        @endforeach
    </datalist>
    <datalist id="tax-profile-options">
        @foreach ($taxProfiles as $taxProfile)
            <option value="{{ $taxProfile }}"></option>
        @endforeach
    </datalist>

    <form class="form-panel catalog-form" method="POST" action="{{ route('web.catalog.products.store') }}">
        @csrf

        <section class="form-section">
            <span class="eyebrow">Fitment</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="car_model">Main car model</label>
                    <input class="form-control searchable-input" id="car_model" name="car_model" list="car-model-options" placeholder="Search make, model, engine, or year">
                </div>

                <div class="form-field">
                    <label for="part_type">Part type</label>
                    <input class="form-control searchable-input" id="part_type" name="part_type" list="part-type-options" placeholder="Search part type">
                </div>

                <div class="form-field">
                    <label for="fuel_type">Fuel type</label>
                    <input class="form-control searchable-input" id="fuel_type" name="fuel_type" list="fuel-type-options" placeholder="Search fuel type">
                </div>

                <div class="form-field">
                    <label for="part_country_of_origin">Part origin</label>
                    <input class="form-control searchable-input" id="part_country_of_origin" name="part_country_of_origin" list="part-origin-countries" placeholder="Search country">
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">Product details</span>
            <div class="form-grid">
                <div class="form-field">
                    <label for="product_name">Product name</label>
                    <input class="form-control" id="product_name" name="product_name" placeholder="Toyota Corolla Brake Pads Front">
                </div>

                <div class="form-field">
                    <label for="product_code">Product code</label>
                    <input class="form-control" id="product_code" name="product_code" placeholder="Auto generated if blank">
                </div>

                <div class="form-field">
                    <label for="brand">Brand</label>
                    <input class="form-control searchable-input" id="brand" name="brand" list="brand-options" placeholder="Search brand">
                </div>

                <div class="form-field">
                    <label for="tax_profile">Tax profile</label>
                    <input class="form-control searchable-input" id="tax_profile" name="tax_profile" list="tax-profile-options" placeholder="Search tax profile">
                </div>

                <div class="form-field">
                    <label for="default_purchase_price">Purchase price</label>
                    <input class="form-control" id="default_purchase_price" name="default_purchase_price" type="number" placeholder="25000">
                </div>

                <div class="form-field">
                    <label for="default_selling_price">Selling price</label>
                    <input class="form-control" id="default_selling_price" name="default_selling_price" type="number" placeholder="32500">
                </div>

                <div class="form-field">
                    <label for="default_low_stock_level">Low stock level</label>
                    <input class="form-control" id="default_low_stock_level" name="default_low_stock_level" type="number" placeholder="5">
                </div>

                <div class="form-field">
                    <label for="pack_size">Pack size</label>
                    <input class="form-control" id="pack_size" name="pack_size" type="number" placeholder="1">
                </div>

                <div class="form-field full">
                    <label for="pos_description">POS description</label>
                    <textarea class="form-control" id="pos_description" name="pos_description" rows="3" placeholder="Short cashier-friendly description"></textarea>
                </div>
            </div>
        </section>

        <section class="form-section">
            <span class="eyebrow">References and compatibility</span>
            <div class="catalog-reference-grid">
                <label>
                    Barcode
                    <input class="form-control" name="barcode" placeholder="Scan or enter barcode">
                </label>
                <label>
                    OEM number
                    <input class="form-control" name="oem_number" placeholder="OEM reference">
                </label>
                <label>
                    Supplier code
                    <input class="form-control" name="supplier_code" placeholder="Supplier code">
                </label>
            </div>

            <div class="compatibility-panel">
                <div>
                    <strong>Other compatible car variants</strong>
                    <p>Add any extra vehicles that can use this same part. Main car model already covers the primary fitment.</p>
                </div>

                <div class="compatibility-variant-list" data-compatibility-list>
                    <div class="compatibility-variant-row">
                        <input class="form-control searchable-input" name="compatible_variants[]" list="car-model-options" placeholder="Search compatible car variant">
                        <button class="btn-secondary" type="button" data-remove-compatibility-variant>Remove</button>
                    </div>
                </div>

                <button class="btn-secondary compatibility-add-button" type="button" data-add-compatibility-variant>Add variant</button>

                <div class="compatibility-grid">
                    <textarea class="form-control" name="compatibility_notes" rows="3" placeholder="Fitment notes, exclusions, engine remarks, or trim differences"></textarea>
                </div>
            </div>
        </section>

        <div class="form-actions">
            <button class="btn" type="submit">Save part</button>
        </div>
    </form>
@endsection
