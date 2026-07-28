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
    <datalist id="vehicle-make-options">
        @foreach ($carMakeOptions as $make)
            <option value="{{ $make['name'] }}"></option>
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
            <button type="button" data-settings-tab="vehicle-library">
                <strong>Vehicle library</strong>
                <small>Car makes and model dropdowns</small>
            </button>
            <button type="button" data-settings-tab="payment-accounts">
                <strong>Payment accounts</strong>
                <small>Cash, bank, and mobile money</small>
            </button>
            <button type="button" data-settings-tab="document-numbering">
                <strong>Document numbering</strong>
                <small>Sales, purchases, transfers, stock counts</small>
            </button>
            <button type="button" data-settings-tab="user-management">
                <strong>User management</strong>
                <small>Admins, roles, site access, and status</small>
            </button>
        </aside>

        <form
            class="settings-content"
            method="POST"
            action="{{ route('web.settings.update') }}"
            data-settings-content
            data-settings-initial-panel="{{ old('settings_panel', session('settings_panel', 'company-profile')) }}"
            data-settings-error-dialog="{{ old('settings_action') }}"
        >
            @csrf
            <input type="hidden" name="settings_panel" value="{{ old('settings_panel', session('settings_panel', 'company-profile')) }}" data-settings-active-panel>

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
                        <x-form-error name="business_name" />
                    </div>

                    <div class="form-field">
                        <label for="legal_name">Legal name</label>
                        <input class="form-control" id="legal_name" name="legal_name" value="{{ $settings['legal_name'] }}">
                        <x-form-error name="legal_name" />
                    </div>

                    <div class="form-field">
                        <label for="registration_number">Registration number</label>
                        <input class="form-control" id="registration_number" name="registration_number" value="{{ $settings['registration_number'] }}">
                        <x-form-error name="registration_number" />
                    </div>

                    <div class="form-field">
                        <label for="base_country">Base country</label>
                        <input class="form-control searchable-input" id="base_country" name="base_country" list="settings-countries" value="{{ $settings['base_country'] }}">
                        <x-form-error name="base_country" />
                    </div>

                    <div class="form-field">
                        <label for="base_currency">Base currency</label>
                        <input class="form-control searchable-input" id="base_currency" name="base_currency" list="currency-options" value="{{ $settings['base_currency'] }}">
                        <x-form-error name="base_currency" />
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
                        <x-form-error name="default_branch" />
                    </div>

                    <div class="form-field">
                        <label for="stock_costing_method">Stock costing method</label>
                        <input class="form-control searchable-input" id="stock_costing_method" name="stock_costing_method" list="costing-method-options" value="{{ $settings['stock_costing_method'] }}">
                        <x-form-error name="stock_costing_method" />
                    </div>

                    <div class="form-field">
                        <label for="low_stock_policy">Low stock policy</label>
                        <input class="form-control" id="low_stock_policy" name="low_stock_policy" value="{{ $settings['low_stock_policy'] }}">
                        <x-form-error name="low_stock_policy" />
                    </div>
                </div>
            </section>

            <section class="settings-panel" id="vehicle-library" data-settings-panel="vehicle-library" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Vehicle library</span>
                    <h2>Car makes and models</h2>
                    <p>Search makes here, then open a make to add or manage the models linked to it.</p>
                </header>

                <div class="settings-add-row">
                    <div class="filter-form">
                        <input type="search" placeholder="Search makes..." aria-label="Search car makes" data-settings-list-search="vehicle-make-list">
                    </div>
                    <button class="btn-secondary" type="button" data-open-settings-dialog="car-make">Add make</button>
                </div>

                <div class="vehicle-library-grid single">
                    <section>
                        <header>
                            <strong>Car makes</strong>
                        </header>
                        <div class="site-list" data-settings-list="vehicle-make-list">
                            @forelse ($carMakes as $make)
                                <article data-settings-list-item="{{ strtolower($make['name'].' '.$make['code']) }}">
                                    <div>
                                        <strong>
                                            <a class="table-link" href="{{ route('web.settings.vehicle-makes.show', $make['id']) }}">{{ $make['name'] }}</a>
                                        </strong>
                                        <span>{{ $make['code'] }} - {{ $make['models'] }} models</span>
                                    </div>
                                    <em>{{ $make['status'] }}</em>
                                    <div class="settings-row-actions">
                                        <button
                                            class="icon-action icon-edit"
                                            type="button"
                                            title="Edit make"
                                            aria-label="Edit make"
                                            data-open-settings-dialog="edit-car-make"
                                            data-settings-fill
                                            data-edit-make-id="{{ $make['id'] }}"
                                            data-edit-make-name="{{ $make['name'] }}"
                                            data-edit-make-code="{{ $make['code'] }}"
                                            data-edit-make-description="{{ $make['description'] }}"
                                        >
                                            <x-icons.pencil />
                                        </button>
                                        @if ($make['is_active'])
                                            <button
                                                class="icon-action icon-danger"
                                                type="submit"
                                                name="settings_action"
                                                value="deactivate_car_make"
                                                formaction="{{ route('web.settings.update', ['car_make_id' => $make['id']]) }}"
                                                title="Make inactive"
                                                aria-label="Make inactive"
                                                data-confirm-title="Deactivate car make?"
                                                data-confirm="Deactivate &quot;{{ $make['name'] }}&quot;? It will no longer be available when adding vehicle models."
                                                data-confirm-label="Deactivate"
                                            >
                                                <x-icons.trash />
                                            </button>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <article>
                                    <div>
                                        <strong>No makes added</strong>
                                        <span>Add a make to start loading model dropdowns.</span>
                                    </div>
                                </article>
                            @endforelse
                        </div>
                        <div class="settings-pagination">
                            {{ $carMakes->links() }}
                        </div>
                    </section>
                </div>

                <dialog class="settings-dialog" data-settings-dialog="car-make" aria-labelledby="settings-car-make-title">
                    <div class="settings-dialog-card">
                        <header>
                            <span class="eyebrow">New make</span>
                            <h3 id="settings-car-make-title">Add car make</h3>
                        </header>
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="make_name">Make name</label>
                                <input class="form-control" id="make_name" name="make_name" value="{{ old('make_name') }}" placeholder="Toyota">
                                <x-form-error name="make_name" />
                            </div>
                            <div class="form-field">
                                <label for="make_code">Make code</label>
                                <input class="form-control" id="make_code" name="make_code" value="{{ old('make_code') }}" placeholder="TY">
                                <x-form-error name="make_code" />
                            </div>
                            <div class="form-field full">
                                <label for="make_description">Description</label>
                                <textarea class="form-control" id="make_description" name="make_description" rows="3">{{ old('make_description') }}</textarea>
                                <x-form-error name="make_description" />
                            </div>
                        </div>
                        <div class="settings-dialog-actions">
                            <button class="btn-secondary" type="button" data-close-settings-dialog>Cancel</button>
                            <button class="btn" type="submit" name="settings_action" value="create_car_make">Save make</button>
                        </div>
                    </div>
                </dialog>

                <dialog class="settings-dialog" data-settings-dialog="edit-car-make" aria-labelledby="settings-edit-car-make-title">
                    <div class="settings-dialog-card">
                        <header>
                            <span class="eyebrow">Edit make</span>
                            <h3 id="settings-edit-car-make-title">Update car make</h3>
                        </header>
                        <input type="hidden" name="edit_make_id" data-settings-field="editMakeId">
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="edit_make_name">Make name</label>
                                <input class="form-control" id="edit_make_name" name="edit_make_name" data-settings-field="editMakeName" placeholder="Toyota">
                                <x-form-error name="edit_make_name" />
                            </div>
                            <div class="form-field">
                                <label for="edit_make_code">Make code</label>
                                <input class="form-control" id="edit_make_code" name="edit_make_code" data-settings-field="editMakeCode" placeholder="TY">
                                <x-form-error name="edit_make_code" />
                            </div>
                            <div class="form-field full">
                                <label for="edit_make_description">Description</label>
                                <textarea class="form-control" id="edit_make_description" name="edit_make_description" data-settings-field="editMakeDescription" rows="3"></textarea>
                                <x-form-error name="edit_make_description" />
                            </div>
                        </div>
                        <div class="settings-dialog-actions">
                            <button class="btn-secondary" type="button" data-close-settings-dialog>Cancel</button>
                            <button class="btn" type="submit" name="settings_action" value="update_car_make">Update make</button>
                        </div>
                    </div>
                </dialog>

            </section>

            <section class="settings-panel" id="payment-accounts" data-settings-panel="payment-accounts" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Payment accounts</span>
                    <h2>Cash, bank, mobile money, and card accounts</h2>
                    <p>Manage the accounts used by payments, purchases, sales, and expense workflows.</p>
                </header>
                <div class="settings-add-row">
                    <div class="filter-form">
                        <input type="search" placeholder="Search accounts..." aria-label="Search payment accounts" data-settings-list-search="payment-account-list">
                    </div>
                    <a class="btn" href="{{ route('web.payment-accounts.create') }}">Create account</a>
                </div>
                <div class="site-list" data-settings-list="payment-account-list">
                    @forelse ($paymentAccounts as $account)
                        <article data-settings-list-item="{{ strtolower($account->account_name.' '.$account->account_holder_name.' '.$account->account_type) }}">
                            <div>
                                <strong>{{ $account->account_name }}</strong>
                                <span>{{ $accountTypes[$account->account_type] ?? $account->account_type }} - {{ $account->account_holder_name ?: 'No holder recorded' }}</span>
                            </div>
                            <em>{{ $account->is_active ? 'Active' : 'Inactive' }}</em>
                            <div class="settings-row-actions">
                                <a class="btn-secondary" href="{{ route('web.payment-accounts.show', $account) }}">View</a>
                                <a class="icon-action icon-edit" href="{{ route('web.payment-accounts.edit', $account) }}" title="Edit payment account" aria-label="Edit payment account">
                                    <x-icons.pencil />
                                </a>
                            </div>
                        </article>
                    @empty
                        <article>
                            <div>
                                <strong>No payment accounts found</strong>
                                <span>Create an account before recording payments.</span>
                            </div>
                        </article>
                    @endforelse
                </div>
                <div class="settings-pagination">
                    {{ $paymentAccounts->links() }}
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

                <div class="settings-add-row">
                    <button class="btn-secondary" type="button" data-open-settings-dialog="series">Add document series</button>
                </div>

                <dialog class="settings-dialog" data-settings-dialog="series" aria-labelledby="settings-series-title">
                    <div class="settings-dialog-card">
                        <header>
                            <span class="eyebrow">New series</span>
                            <h3 id="settings-series-title">Add document numbering</h3>
                        </header>
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="series_name">Document name</label>
                                <input class="form-control" id="series_name" name="series_name" value="{{ old('series_name') }}" placeholder="Supplier returns">
                                <x-form-error name="series_name" />
                            </div>
                            <div class="form-field">
                                <label for="series_prefix">Prefix</label>
                                <input class="form-control" id="series_prefix" name="series_prefix" value="{{ old('series_prefix') }}" placeholder="SRN">
                                <x-form-error name="series_prefix" />
                            </div>
                            <div class="form-field full">
                                <label for="series_next_number">Next number</label>
                                <input class="form-control" id="series_next_number" name="series_next_number" value="{{ old('series_next_number', 1001) }}" inputmode="numeric" placeholder="1001">
                                <x-form-error name="series_next_number" />
                            </div>
                        </div>
                        <div class="settings-dialog-actions">
                            <button class="btn-secondary" type="button" data-close-settings-dialog>Cancel</button>
                            <button class="btn" type="submit" name="settings_action" value="create_document_series">Save series</button>
                        </div>
                    </div>
                </dialog>
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

                    <div class="settings-add-row">
                        <button class="btn-secondary" type="button" data-open-settings-dialog="user">Add user</button>
                    </div>
                </div>

                <dialog class="settings-dialog" data-settings-dialog="user" aria-labelledby="settings-user-title">
                    <div class="settings-dialog-card">
                        <header>
                            <span class="eyebrow">New user</span>
                            <h3 id="settings-user-title">Add team member</h3>
                        </header>
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="user_name">Full name</label>
                                <input class="form-control" id="user_name" name="user_name" value="{{ old('user_name') }}" placeholder="Branch Manager">
                                <x-form-error name="user_name" />
                            </div>
                            <div class="form-field">
                                <label for="user_email">Email</label>
                                <input class="form-control" id="user_email" name="user_email" type="email" value="{{ old('user_email') }}" placeholder="manager@partflow.test">
                                <x-form-error name="user_email" />
                            </div>
                            <div class="form-field">
                                <label for="user_role">Role</label>
                                <input class="form-control searchable-input" id="user_role" name="user_role" value="{{ old('user_role') }}" list="role-options" placeholder="Search role">
                                <x-form-error name="user_role" />
                            </div>
                            <div class="form-field">
                                <label for="user_site">Site access</label>
                                <input class="form-control searchable-input" id="user_site" name="user_site" value="{{ old('user_site') }}" list="branch-options" placeholder="Search site">
                                <x-form-error name="user_site" />
                            </div>
                            <div class="form-field full">
                                <label for="user_password">Temporary password</label>
                                <input class="form-control" id="user_password" name="user_password" type="password" autocomplete="new-password" placeholder="Leave blank to auto-generate">
                                <x-form-error name="user_password" />
                            </div>
                        </div>
                        <div class="settings-dialog-actions">
                            <button class="btn-secondary" type="button" data-close-settings-dialog>Cancel</button>
                            <button class="btn" type="submit" name="settings_action" value="create_user">Save user</button>
                        </div>
                    </div>
                </dialog>
            </section>

            <div class="settings-save-bar">
                <button class="btn-secondary" type="button" data-settings-prev>Previous</button>
                <span data-settings-progress>Step 1</span>
                <button class="btn-secondary" type="button" data-settings-next>Next</button>
                <button class="btn" type="submit" name="settings_action" value="save_settings">Save settings</button>
            </div>
        </form>
    </section>
@endsection
