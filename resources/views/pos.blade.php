@extends('layouts.app', [
    'title' => 'Point of sale',
    'description' => 'Choose parts, confirm branch stock, collect payment, and issue a receipt from one screen.',
    'kicker' => 'Live service',
    'bodyClass' => 'pos-page',
])

@push('styles')
    @vite('resources/css/pos.css')
@endpush

@push('scripts')
    @vite('resources/js/pos.js')
@endpush

@section('header_actions')
    <a class="btn" href="{{ route('web.pos') }}">New sale</a>
@endsection

@section('content')
    <datalist id="pos-vehicle-options">
        @foreach ($vehicleFilters as $filter)
            <option value="{{ $filter }}"></option>
        @endforeach
    </datalist>
    <datalist id="pos-part-type-options">
        @foreach ($partTypeFilters as $filter)
            <option value="{{ $filter }}"></option>
        @endforeach
    </datalist>

    <section class="pos-hero">
        <div>
            <span class="eyebrow">Fast parts entry</span>
            <h2>Find, fit, sell.</h2>
            <p>Search by part, code, barcode, OEM, or vehicle and confirm branch availability before checkout.</p>
        </div>

        <article class="activity-session">
            <span></span>
            <div>
                <strong>{{ $currentBranch }} activity</strong>
                <small>{{ $cashier }} - not synced</small>
            </div>
            <button type="button">Start activity</button>
        </article>
    </section>

    <section class="pos-board" aria-label="Point of sale workspace">
        <section class="product-browser" aria-label="Parts browser">
            <div class="pos-toolbar">
                <label class="pos-search-field" for="part-search">
                    <span aria-hidden="true"></span>
                    <input id="part-search" type="search" value="brake pads" autocomplete="off" aria-label="Search by part, code, barcode, vehicle, or OEM">
                </label>

                <input class="searchable-input" data-pos-filter list="pos-vehicle-options" value="All vehicles" aria-label="Filter products by vehicle">
                <input class="searchable-input" data-pos-filter list="pos-part-type-options" value="All part types" aria-label="Filter products by part type">
            </div>

            <div class="quick-row" aria-label="Quick search chips">
                <span>Try</span>
                @foreach ($quickSearches as $search)
                    <button type="button">{{ $search }}</button>
                @endforeach
                <a href="#" data-pos-clear>Clear</a>
            </div>

            <div class="product-card-grid" aria-label="Matching parts">
                @foreach ($products as $index => $product)
                    <button class="part-card {{ $index === 0 ? 'selected' : '' }}" type="button" data-product-index="{{ $index }}">
                        <span class="part-type">{{ $product['part_type'] }}</span>
                        <span @class(['part-stock-pill', 'low' => $product['branch_stock'][0]['status'] === 'low', 'empty' => $product['branch_stock'][0]['available'] === 0])>
                            {{ $product['branch_stock'][0]['available'] > 0 ? 'Direct stock available' : 'Needs transfer' }}
                        </span>

                        <strong>{{ $product['product_name'] }}</strong>
                        <em>{{ $product['vehicle'] }}</em>

                        <span class="part-price">{{ $product['selling_price_display'] }}</span>
                        <span class="part-available">{{ $product['branch_stock'][0]['available'] }} available</span>
                    </button>
                @endforeach
            </div>

            <footer class="product-browser-footer">
                <span data-result-count>{{ count($products) }} matches</span>
                <span>Search includes OEM, barcode, and compatible car models</span>
            </footer>
        </section>

        <aside class="order-panel" aria-label="Current order">
            <header class="order-header">
                <div>
                    <span class="eyebrow">Current order</span>
                    <h2>Cart <small data-cart-count>{{ count($cartLines) }}</small></h2>
                </div>
                <a href="#" data-pos-clear-cart>Clear</a>
            </header>

            <section class="selected-part-card" aria-label="Selected part details">
                <span class="eyebrow">Selected part</span>
                <h3 data-selected-name>{{ $selectedProduct['product_name'] }}</h3>
                <p data-selected-description>{{ $selectedProduct['pos_description'] }}</p>

                <dl>
                    <div>
                        <dt>Code</dt>
                        <dd data-selected-code>{{ $selectedProduct['product_code'] }}</dd>
                    </div>
                    <div>
                        <dt>Vehicle</dt>
                        <dd data-selected-vehicle>{{ $selectedProduct['vehicle'] }}</dd>
                    </div>
                    <div>
                        <dt>OEM</dt>
                        <dd data-selected-oem>{{ $selectedProduct['oem_number'] }}</dd>
                    </div>
                    <div>
                        <dt>Network stock</dt>
                        <dd><span data-total-available>{{ $selectedProduct['total_available'] }}</span></dd>
                    </div>
                </dl>

                <div class="branch-mini-list" data-branch-stock>
                    @foreach ($selectedProduct['branch_stock'] as $branch)
                        <div @class([
                            'current' => $branch['branch'] === $currentBranch,
                            'low' => $branch['status'] === 'low',
                            'empty' => $branch['available'] === 0,
                        ])>
                            <span>{{ $branch['branch'] }}</span>
                            <strong>{{ $branch['available'] }}</strong>
                        </div>
                    @endforeach
                </div>

                <p data-selected-reference>
                    Barcode {{ $selectedProduct['barcode'] }}. {{ $selectedProduct['tax_profile'] }}. Origin {{ $selectedProduct['part_country_of_origin'] }}.
                </p>

                <div class="add-row">
                    <input type="number" min="1" value="1" data-pos-quantity aria-label="Quantity">
                    <input type="text" value="{{ $selectedProduct['selling_price_display'] }}" data-pos-unit-price readonly aria-label="Unit price">
                    <button type="button" data-add-to-cart>Add</button>
                </div>
            </section>

            <div class="cart-list" aria-label="Sale items" data-cart-list>
                @foreach ($cartLines as $line)
                    <article class="cart-line">
                        <div>
                            <strong>{{ $line['name'] }}</strong>
                            <span>{{ $line['code'] }} x {{ $line['quantity'] }} at {{ $line['unit_price_display'] }}</span>
                        </div>
                        <em>{{ $line['line_total_display'] }}</em>
                    </article>
                @endforeach
            </div>

            <form class="checkout-form" aria-label="Checkout details">
                <label>
                    Customer
                    <input type="text" value="Walk-in customer" autocomplete="off">
                </label>

                <div class="checkout-form-grid">
                    <label>
                        Payment method
                        <select>
                            @foreach ($paymentMethods as $paymentMethod)
                                <option>{{ $paymentMethod }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Amount received
                        <input type="text" value="{{ $saleTotals['total'] }}">
                    </label>
                </div>
            </form>

            <section class="totals-card" aria-label="Sale totals">
                <div>
                    <span>Subtotal</span>
                    <strong data-subtotal>{{ $saleTotals['subtotal'] }}</strong>
                </div>
                <div>
                    <span>Discount</span>
                    <strong>{{ $saleTotals['discount'] }}</strong>
                </div>
                <div>
                    <span>VAT</span>
                    <strong>{{ $saleTotals['tax'] }}</strong>
                </div>
                <div class="total-due">
                    <span>Total due</span>
                    <strong data-total-due>{{ $saleTotals['total'] }}</strong>
                </div>
            </section>

            <div class="checkout-actions">
                <button class="ghost-button" type="button">Hold</button>
                <button class="ghost-button" type="button">Discount</button>
                <button class="pay-button" type="button">Complete sale</button>
            </div>
        </aside>
    </section>

    <script id="pos-products-data" type="application/json">@json($products)</script>
    <script id="pos-current-branch" type="application/json">@json($currentBranch)</script>
@endsection
