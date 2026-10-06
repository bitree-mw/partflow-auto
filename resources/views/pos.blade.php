@extends('layouts.pos', [
    'title' => 'Point of sale',
])

@section('content')
    <section class="pos-hero">
        <div>
            <div class="pos-hero-context">
                <span class="eyebrow">Fast parts entry</span>
                <span class="pos-branch-heading">
                    <small>Selling from</small>
                    <strong data-pos-branch-name>{{ $currentBranch }}</strong>
                </span>
            </div>
            <h2>Find, fit, sell.</h2>
            <p>{{ $cashier }} · Search by product, code, barcode, OEM{{ $fitmentSearch ? ', or vehicle' : '' }} and confirm branch availability before checkout.</p>
        </div>
    </section>

    <section class="pos-board" aria-label="Point of sale workspace">
        <section class="product-browser" aria-label="Parts browser">
            <div class="pos-toolbar">
                <label class="pos-search-field" for="part-search">
                    <span aria-hidden="true"></span>
                    <input id="part-search" type="search" value="" placeholder="{{ $fitmentSearch ? 'Search product, code, barcode, vehicle, or OEM' : 'Search product, code, barcode, or OEM' }}" autocomplete="off" aria-label="{{ $fitmentSearch ? 'Search by product, code, barcode, vehicle, or OEM' : 'Search by product, code, barcode, or OEM' }}">
                </label>

                {{-- Kept in the DOM but hidden without vehicle fitment search: pos.js expects these elements. --}}
                <div class="app-combobox pos-filter-picker" data-pos-vehicle-picker @unless ($fitmentSearch) hidden style="display: none" @endunless>
                    <input type="hidden" data-pos-vehicle-id value="">
                    <button class="app-combobox-input app-combobox-trigger" type="button" data-pos-vehicle-trigger aria-label="Filter products by vehicle" aria-haspopup="listbox" aria-controls="pos-vehicle-options" aria-expanded="false">All vehicles</button>
                    <div class="app-combobox-list" data-pos-vehicle-panel hidden>
                        <input class="app-combobox-search" type="search" data-pos-vehicle-search placeholder="Search vehicles..." autocomplete="off" aria-label="Search vehicles">
                        <div class="app-combobox-options" id="pos-vehicle-options" data-pos-vehicle-options role="listbox"></div>
                    </div>
                </div>
                <div class="app-combobox pos-filter-picker" data-pos-product-type-picker>
                    <input type="hidden" data-pos-product-type-filter value="">
                    <button class="app-combobox-input app-combobox-trigger" type="button" data-pos-product-type-trigger aria-label="Filter products by product type" aria-haspopup="listbox" aria-controls="pos-product-type-options" aria-expanded="false">All product types</button>
                    <div class="app-combobox-list" data-pos-product-type-panel hidden>
                        <input class="app-combobox-search" type="search" data-pos-product-type-search placeholder="Search product types..." autocomplete="off" aria-label="Search product types">
                        <div class="app-combobox-options" id="pos-product-type-options" data-pos-product-type-options role="listbox"></div>
                    </div>
                </div>
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
                                <strong class="part-name">{{ $product['product_name'] }}</strong>
                                <span class="part-code">({{ $product['product_code'] }})</span>
                            </div>
                            <button class="part-add-button" type="button" data-card-add="{{ $index }}" aria-label="Add {{ $product['product_name'] }} to cart">+</button>
                        </div>
                        <div class="part-card-meta">
                            <span class="branch-total-pill current">{{ $product['current_branch_name'] }} {{ $product['current_branch_stock']['available'] }}</span>
                            <span class="branch-total-pill" title="{{ $product['branch_stock_tooltip'] }}">Other {{ $product['other_available'] }}</span>
                            @if ($fitmentSearch)
                                <span class="compatibility-pill" title="{{ $product['compatible_cars_tooltip'] }}">
                                    {{ $product['compatible_cars_count'] > 0 ? 'Fits '.$product['compatible_cars_count'] : 'No fitment' }}
                                </span>
                            @endif
                        </div>
                        <span class="part-price">{{ $product['selling_price_display'] }}</span>
                    </article>
                @empty
                    <div class="empty-state">No stocked parts are available for POS yet.</div>
                @endforelse
            </div>

            <footer class="product-browser-footer">
                <span data-result-count>{{ count($products) }} matches</span>
                <span>{{ $fitmentSearch ? 'Search includes OEM, barcode, and compatible car models' : 'Search includes OEM and barcode' }}</span>
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
                <div class="checkout-form-grid" data-pos-checkout-row="customer-payment">
                    <label>
                        Customer
                        <select name="contact_id">
                            <option value="">Walk-in customer</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer['id'] }}">{{ $customer['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Payment account
                        <select name="payment_account_id">
                            <option value="">No payment account</option>
                            @foreach ($paymentAccounts as $paymentAccount)
                                <option value="{{ $paymentAccount['id'] }}" @selected((int) $paymentAccount['id'] === $defaultPaymentAccountId)>{{ $paymentAccount['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="checkout-form-grid" data-pos-checkout-row="date-amount">
                    <label>
                        Sale date and time
                        <input type="datetime-local" name="document_date" value="{{ old('document_date') }}" data-pos-document-date data-no-future-date>
                    </label>
                    <label>
                        Amount received
                        <input type="text" name="amount_paid" value="0" data-pos-amount-paid>
                    </label>
                    <span class="amount-balance-hint">
                        <span>Still owed</span>
                        <strong data-total-due>{{ $saleTotals['total'] }}</strong>
                    </span>
                </div>
                @if ($requiresFullPayment)
                    <p class="amount-balance-hint">Sales must be paid in full. Enter an amount received equal to the total.</p>
                @endif

                <div class="checkout-discount-panel">
                    <label for="pos_discount_amount">
                        Discount amount ({{ $appSystem['currency'] ?? 'MWK' }})
                        <input id="pos_discount_amount" type="number" name="discount_amount" value="0" min="0" step="0.01" inputmode="decimal" data-pos-discount-amount aria-describedby="pos-discount-feedback">
                    </label>
                    <div class="checkout-discount-feedback" id="pos-discount-feedback" aria-live="polite" data-pos-discount-feedback>
                        <span>Removed <strong data-discount-percentage>0.00%</strong></span>
                        <span>Allowed <strong data-discount-limit>{{ number_format((float) ($posEndpoints['maximumDiscountPercentage'] ?? 20), 2) }}%</strong></span>
                        <span>Sale total <strong data-sale-total>{{ $saleTotals['total'] }}</strong></span>
                    </div>
                </div>
                <span data-subtotal hidden>{{ $saleTotals['subtotal'] }}</span>
            </form>

            <div class="checkout-actions">
                <button class="pay-button" type="button" data-complete-sale>
                    <span>Complete sale</span>
                    <strong data-complete-sale-total>(MWK 0)</strong>
                </button>
            </div>
        </aside>
    </section>

    <script id="pos-products-data" type="application/json">@json($products)</script>
    <script id="pos-current-branch" type="application/json">@json($currentBranch)</script>
    <script id="pos-endpoints-data" type="application/json">@json($posEndpoints)</script>
@endsection
