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
    <datalist id="pos-product-type-options">
        @foreach ($productTypeFilters as $filter)
            <option value="{{ $filter }}"></option>
        @endforeach
    </datalist>

    <section class="pos-hero">
        <div>
            <span class="eyebrow">Fast parts entry</span>
            <h2>Find, fit, sell.</h2>
            <p>Search by product, code, barcode, OEM, or vehicle and confirm branch availability before checkout.</p>
        </div>

        <article class="activity-session">
            <span></span>
            <div>
                <strong>{{ $currentBranch }} activity</strong>
                <small>{{ $cashier }} - active session</small>
            </div>
            <small class="activity-session-site">Selected in header</small>
        </article>
    </section>

    <section class="pos-board" aria-label="Point of sale workspace">
        <section class="product-browser" aria-label="Parts browser">
            <div class="pos-toolbar">
                <label class="pos-search-field" for="part-search">
                    <span aria-hidden="true"></span>
                    <input id="part-search" type="search" value="" placeholder="Search product, code, barcode, vehicle, or OEM" autocomplete="off" aria-label="Search by product, code, barcode, vehicle, or OEM">
                </label>

                <input class="searchable-input" data-pos-filter data-pos-vehicle-filter list="pos-vehicle-options" value="" placeholder="All vehicles" aria-label="Filter products by vehicle">
                <input class="searchable-input" data-pos-filter data-pos-product-type-filter list="pos-product-type-options" value="" placeholder="All product types" aria-label="Filter products by product type">
            </div>

            <div class="quick-row" aria-label="Quick search chips" data-suggestions-row hidden>
                <span>Suggested</span>
                <span data-suggestions-list></span>
                <a href="#" data-pos-clear>Clear</a>
            </div>

            <div class="product-card-grid" aria-label="Matching parts">
                @forelse ($products as $index => $product)
                    <article class="part-card" data-product-index="{{ $index }}">
                        <div class="part-card-top">
                            <div class="part-card-heading">
                                <span class="product-type">{{ $product['product_type'] }}</span>
                                <span class="part-brand">{{ $product['brand'] }}</span>
                                <span class="part-code">({{ $product['product_code'] }})</span>
                            </div>
                            <button class="part-add-button" type="button" data-card-add="{{ $index }}" aria-label="Add {{ $product['product_name'] }} to cart">+</button>
                        </div>
                        <div class="part-card-meta">
                            <span class="branch-total-pill current">{{ $product['current_branch_name'] }} {{ $product['current_branch_stock']['available'] }}</span>
                            <span class="branch-total-pill" title="{{ $product['branch_stock_tooltip'] }}">Other {{ $product['other_available'] }}</span>
                            <span class="compatibility-pill" title="{{ $product['compatible_cars_tooltip'] }}">
                                {{ $product['compatible_cars_count'] > 0 ? 'Fits '.$product['compatible_cars_count'] : 'No fitment' }}
                            </span>
                        </div>
                        <span class="part-price">{{ $product['selling_price_display'] }}</span>
                    </article>
                @empty
                    <div class="empty-state">No stocked parts are available for POS yet.</div>
                @endforelse
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

            <div class="cart-list" aria-label="Sale items" data-cart-list>
                @foreach ($cartLines as $line)
                    <article class="cart-line">
                        <div>
                            <strong>{{ $line['code'] }}</strong>
                            <span>{{ $line['name'] }}</span>
                        </div>
                        <em>{{ $line['unit_price_display'] }}</em>
                    </article>
                @endforeach
            </div>

            <form class="checkout-form" method="POST" action="{{ route('web.pos.sales') }}" aria-label="Checkout details" data-pos-checkout-form>
                @csrf
                <input type="hidden" name="source_site_id" value="{{ $currentSiteId }}" data-pos-source-site-id>
                <input type="hidden" name="cart_payload" value="[]" data-cart-payload>
                <label>
                    Customer
                    <select name="contact_id">
                        <option value="">Walk-in customer</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer['id'] }}">{{ $customer['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="checkout-form-grid">
                    <label>
                        Sale date and time
                        <input type="datetime-local" name="document_date" value="{{ old('document_date') }}" data-pos-document-date>
                    </label>
                    <label>
                        Payment account
                        <select name="payment_account_id">
                            <option value="">No payment account</option>
                            @foreach ($paymentAccounts as $paymentAccount)
                                <option value="{{ $paymentAccount['id'] }}">{{ $paymentAccount['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Amount received
                        <input type="text" name="amount_paid" value="0" data-pos-amount-paid>
                    </label>
                </div>
            </form>

            <section class="totals-card" aria-label="Sale totals">
                <div class="total-due">
                    <span>Total due</span>
                    <strong data-total-due>{{ $saleTotals['total'] }}</strong>
                </div>
                <span data-subtotal hidden>{{ $saleTotals['subtotal'] }}</span>
            </section>

            <div class="checkout-actions">
                <button class="ghost-button" type="button">Hold</button>
                <button class="ghost-button" type="button">Discount</button>
                <button class="pay-button" type="button" data-complete-sale>Complete sale</button>
            </div>
        </aside>
    </section>

    <script id="pos-products-data" type="application/json">@json($products)</script>
    <script id="pos-current-branch" type="application/json">@json($currentBranch)</script>
    <script id="pos-endpoints-data" type="application/json">@json($posEndpoints)</script>
@endsection
