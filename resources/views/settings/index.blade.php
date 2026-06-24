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
    <datalist id="branch-options">
        <option value="All sites"></option>
        @foreach ($sites as $site)
            <option value="{{ $site['name'] }}"></option>
        @endforeach
    </datalist>
    <datalist id="role-options">
        @foreach ($roles as $role)
            <option value="{{ $role }}"></option>
        @endforeach
    </datalist>

    <section class="settings-console">
        <aside class="settings-nav-panel" aria-label="Settings sections">
            <span class="eyebrow">Setup path</span>
            <button type="button" class="active" data-settings-tab="company-profile">
                <strong>Company profile</strong>
                <small>Identity, country, currency</small>
            </button>
            <button type="button" data-settings-tab="operating-defaults">
                <strong>Operating defaults</strong>
                <small>Branch, costing, stock policy</small>
            </button>
            <button type="button" data-settings-tab="company-sites">
                <strong>Company sites</strong>
                <small>Shops, branches, and warehouses</small>
            </button>
            <button type="button" data-settings-tab="document-numbering">
                <strong>Document numbering</strong>
                <small>Sales, purchases, transfers, stock counts</small>
            </button>
            <button type="button" data-settings-tab="user-management">
                <strong>User management</strong>
                <small>Admins, roles, site access, and status</small>
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
                    <h2>Stock and branch rules</h2>
                    <p>Control the defaults used when products, purchases, stock documents, and POS sales are created.</p>
                </header>

                <div class="form-grid settings-section">
                    <div class="form-field">
                        <label for="default_branch">Default branch</label>
                        <input class="form-control searchable-input" id="default_branch" name="default_branch" list="branch-options" value="{{ $settings['default_branch'] }}">
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

            <section class="settings-panel" id="document-numbering" data-settings-panel="document-numbering" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Document numbering</span>
                    <h2>Operational document series</h2>
                    <p>Prepare the numbering prefixes used by POS sales, purchases, transfers, and stock documents.</p>
                </header>

                <div class="document-series-list">
                    @foreach ($documentSeries as $series)
                        <article>
                            <div>
                                <strong>{{ $series['document'] }}</strong>
                                <span>Prefix {{ $series['prefix'] }}</span>
                            </div>
                            <em>Next {{ $series['next_number'] }}</em>
                        </article>
                    @endforeach
                </div>

                <div class="admin-setup-card account-create-card">
                    <span class="eyebrow">New series</span>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="series_name">Document name</label>
                            <input class="form-control" id="series_name" name="series_name" placeholder="Supplier returns">
                        </div>
                        <div class="form-field">
                            <label for="series_prefix">Prefix</label>
                            <input class="form-control" id="series_prefix" name="series_prefix" placeholder="SRN">
                        </div>
                        <div class="form-field full">
                            <label for="series_next_number">Next number</label>
                            <input class="form-control" id="series_next_number" name="series_next_number" placeholder="1001">
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings-panel" id="user-management" data-settings-panel="user-management" hidden>
                <header class="settings-header">
                    <span class="eyebrow">User management</span>
                    <h2>People, access, and branch scope</h2>
                    <p>Add team members, assign roles, and control which site they operate from before API permissions are connected.</p>
                </header>

                <div class="user-management-grid">
                    <section class="user-list" aria-label="Current users">
                        @foreach ($users as $user)
                            <article>
                                <div>
                                    <strong>{{ $user['name'] }}</strong>
                                    <span>{{ $user['email'] }}</span>
                                </div>
                                <div>
                                    <span>{{ $user['role'] }}</span>
                                    <em>{{ $user['site'] }} - {{ $user['status'] }}</em>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    <section class="user-create-card" aria-label="Invite user">
                        <span class="eyebrow">New user</span>
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="user_name">Full name</label>
                                <input class="form-control" id="user_name" name="user_name" placeholder="Branch Manager">
                            </div>
                            <div class="form-field">
                                <label for="user_email">Email</label>
                                <input class="form-control" id="user_email" name="user_email" type="email" placeholder="manager@partflow.test">
                            </div>
                            <div class="form-field">
                                <label for="user_role">Role</label>
                                <input class="form-control searchable-input" id="user_role" name="user_role" list="role-options" placeholder="Search role">
                            </div>
                            <div class="form-field">
                                <label for="user_site">Site access</label>
                                <input class="form-control searchable-input" id="user_site" name="user_site" list="branch-options" placeholder="Search site">
                            </div>
                        </div>
                    </section>
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
                <span data-settings-progress>Step 1 of 6</span>
                <button class="btn-secondary" type="button" data-settings-next>Next</button>
                <button class="btn" type="submit">Save settings</button>
            </div>
        </form>
    </section>
@endsection
