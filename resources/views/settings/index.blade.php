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
    <a class="btn-secondary" href="{{ route('web.payment-accounts.index') }}">Payment accounts</a>
@endsection

@section('content')
    <x-country-datalist id="settings-countries" :countries="$countries" />

    <datalist id="currency-options">
        @foreach ($currencies as $currency)
            <option value="{{ $currency }}"></option>
        @endforeach
    </datalist>
    <datalist id="site-type-options">
        @foreach ($siteTypes as $siteType)
            <option value="{{ $siteType }}"></option>
        @endforeach
    </datalist>
    <datalist id="costing-method-options">
        @foreach ($costingMethods as $costingMethod)
            <option value="{{ $costingMethod }}"></option>
        @endforeach
    </datalist>
    <datalist id="tax-profile-options">
        @foreach ($taxProfiles as $taxProfile)
            <option value="{{ $taxProfile }}"></option>
        @endforeach
    </datalist>
    <datalist id="branch-options">
        @foreach ($sites as $site)
            <option value="{{ $site['name'] }}"></option>
        @endforeach
    </datalist>

    <section class="settings-console">
        <aside class="settings-nav-panel" aria-label="Settings sections">
            <span class="eyebrow">Setup path</span>
            <button type="button" class="active" data-settings-tab="company-profile">
                <strong>Company profile</strong>
                <small>Identity, tax, country, currency</small>
            </button>
            <button type="button" data-settings-tab="operating-defaults">
                <strong>Operating defaults</strong>
                <small>Branch, tax profile, costing, stock policy</small>
            </button>
            <button type="button" data-settings-tab="company-sites">
                <strong>Company sites</strong>
                <small>Shops, branches, and warehouses</small>
            </button>
            <button type="button" data-settings-tab="module-coverage">
                <strong>Module coverage</strong>
                <small>What has been configured so far</small>
            </button>
        </aside>

        <form class="settings-content" method="POST" action="{{ route('web.settings.update') }}">
            @csrf

            <section class="settings-panel active" id="company-profile" data-settings-panel="company-profile">
                <header class="settings-header">
                    <span class="eyebrow">Company profile</span>
                    <h2>Business identity</h2>
                    <p>Set the company identity used on invoices, receipts, purchases, reports, and audit records.</p>
                </header>

                <div class="form-grid settings-section">
                    <div class="form-field">
                        <label for="business_name">Trading name</label>
                        <input class="form-control" id="business_name" name="business_name" value="{{ $settings['business_name'] }}">
                    </div>

                    <div class="form-field">
                        <label for="legal_name">Legal name</label>
                        <input class="form-control" id="legal_name" name="legal_name" value="{{ $settings['legal_name'] }}">
                    </div>

                    <div class="form-field">
                        <label for="registration_number">Registration number</label>
                        <input class="form-control" id="registration_number" name="registration_number" value="{{ $settings['registration_number'] }}">
                    </div>

                    <div class="form-field">
                        <label for="tax_number">Tax number</label>
                        <input class="form-control" id="tax_number" name="tax_number" value="{{ $settings['tax_number'] }}">
                    </div>

                    <div class="form-field">
                        <label for="base_country">Base country</label>
                        <input class="form-control searchable-input" id="base_country" name="base_country" list="settings-countries" value="{{ $settings['base_country'] }}">
                    </div>

                    <div class="form-field">
                        <label for="base_currency">Base currency</label>
                        <input class="form-control searchable-input" id="base_currency" name="base_currency" list="currency-options" value="{{ $settings['base_currency'] }}">
                    </div>
                </div>
            </section>

            <section class="settings-panel" id="operating-defaults" data-settings-panel="operating-defaults" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Operating defaults</span>
                    <h2>Stock, tax, and branch rules</h2>
                    <p>Control the defaults used when products, stock documents, and POS sales are created.</p>
                </header>

                <div class="form-grid settings-section">
                    <div class="form-field">
                        <label for="default_branch">Default branch</label>
                        <input class="form-control searchable-input" id="default_branch" name="default_branch" list="branch-options" value="{{ $settings['default_branch'] }}">
                    </div>

                    <div class="form-field">
                        <label for="default_tax_profile">Default tax profile</label>
                        <input class="form-control searchable-input" id="default_tax_profile" name="default_tax_profile" list="tax-profile-options" value="{{ $settings['default_tax_profile'] }}">
                    </div>

                    <div class="form-field">
                        <label for="stock_costing_method">Stock costing method</label>
                        <input class="form-control searchable-input" id="stock_costing_method" name="stock_costing_method" list="costing-method-options" value="{{ $settings['stock_costing_method'] }}">
                    </div>

                    <div class="form-field">
                        <label for="low_stock_policy">Low stock policy</label>
                        <input class="form-control" id="low_stock_policy" name="low_stock_policy" value="{{ $settings['low_stock_policy'] }}">
                    </div>
                </div>
            </section>

            <section class="settings-panel" id="company-sites" data-settings-panel="company-sites" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Sites</span>
                    <h2>Branches and stock locations</h2>
                    <p>Add shops, branches, or warehouses that hold stock, accept transfers, and run tills.</p>
                </header>

                <div class="site-list">
                    @foreach ($sites as $site)
                        <article>
                            <div>
                                <strong>{{ $site['name'] }}</strong>
                                <span>{{ $site['type'] }} - {{ $site['city'] }}, {{ $site['country'] }}</span>
                            </div>
                            <em>{{ $site['status'] }}</em>
                        </article>
                    @endforeach
                </div>

                <div class="site-create-actions">
                    <button class="btn-secondary" type="button" data-add-site>Type in a new site</button>
                </div>

                <div class="site-create-card" data-site-form hidden>
                    <span class="eyebrow">New site details</span>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="site_name">Site name</label>
                            <input class="form-control" id="site_name" name="site_name" placeholder="Kanengo Warehouse">
                        </div>
                        <div class="form-field">
                            <label for="site_type">Site type</label>
                            <input class="form-control searchable-input" id="site_type" name="site_type" list="site-type-options" placeholder="Search site type">
                        </div>
                        <div class="form-field">
                            <label for="site_city">City</label>
                            <input class="form-control" id="site_city" name="site_city" placeholder="Lilongwe">
                        </div>
                        <div class="form-field">
                            <label for="site_country">Country</label>
                            <input class="form-control searchable-input" id="site_country" name="site_country" list="settings-countries" placeholder="Search country">
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings-panel" id="module-coverage" data-settings-panel="module-coverage" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Coverage</span>
                    <h2>Configured admin modules</h2>
                    <p>Quickly see what the admin has already prepared before API wiring is connected.</p>
                </header>

                <div class="settings-groups">
                    @foreach ($settingGroups as $group)
                        <article>
                            <strong>{{ $group['name'] }}</strong>
                            <div>
                                @foreach ($group['items'] as $item)
                                    <span>{{ $item }}</span>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="settings-save-bar">
                <button class="btn-secondary" type="button" data-settings-prev>Previous</button>
                <span data-settings-progress>Step 1 of 4</span>
                <button class="btn-secondary" type="button" data-settings-next>Next</button>
                <button class="btn" type="submit">Save settings</button>
            </div>
        </form>
    </section>
@endsection
