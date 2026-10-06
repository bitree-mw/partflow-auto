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

    <datalist id="vehicle-make-options">
        @foreach ($carMakeOptions as $make)
            <option value="{{ $make['name'] }}"></option>
        @endforeach
    </datalist>

    <section class="settings-console">
        <aside class="settings-nav-panel" aria-label="Settings sections" role="tablist">
            <span class="eyebrow">Setup path</span>
            <button id="settings-tab-company-profile" type="button" class="active" role="tab" aria-selected="true" aria-controls="settings-panel-company-profile" data-settings-tab="company-profile">
                <strong>Business information</strong>
                <small>Identity, currency, stock email</small>
            </button>
            <button id="settings-tab-operating-defaults" type="button" role="tab" aria-selected="false" aria-controls="settings-panel-operating-defaults" data-settings-tab="operating-defaults">
                <strong>Operating defaults</strong>
                <small>Branch, costing, stock and discount policy</small>
            </button>
            <button id="settings-tab-vehicle-library" type="button" role="tab" aria-selected="false" aria-controls="settings-panel-vehicle-library" data-settings-tab="vehicle-library">
                <strong>Vehicle library</strong>
                <small>Car makes and model dropdowns</small>
            </button>
            <button id="settings-tab-payment-accounts" type="button" role="tab" aria-selected="false" aria-controls="settings-panel-payment-accounts" data-settings-tab="payment-accounts">
                <strong>Payment accounts</strong>
                <small>Cash, bank, and mobile money</small>
            </button>
            <button id="settings-tab-user-management" type="button" role="tab" aria-selected="false" aria-controls="settings-panel-user-management" data-settings-tab="user-management">
                <strong>User management</strong>
                <small>Admins, roles, site access, and status</small>
            </button>
        </aside>

        <form
            class="settings-content"
            method="POST"
            action="{{ route('web.settings.update') }}"
            enctype="multipart/form-data"
            data-settings-content
            data-track-unsaved-changes
            data-settings-initial-panel="{{ old('settings_panel', session('settings_panel', 'company-profile')) }}"
            data-settings-error-dialog="{{ old('settings_action') }}"
        >
            @csrf
            <input type="hidden" name="settings_panel" value="{{ old('settings_panel', session('settings_panel', 'company-profile')) }}" data-settings-active-panel>

            <section class="settings-panel active" id="settings-panel-company-profile" role="tabpanel" aria-labelledby="settings-tab-company-profile" data-settings-panel="company-profile">
                <header class="settings-header">
                    <span class="eyebrow">Business information</span>
                    <h2>Business identity</h2>
                    <p>Set the company identity used on invoices, receipts, purchases, reports, and audit records.</p>
                </header>

                <div class="form-grid settings-section">
                    <div class="form-field full">
                        <span id="current-package-label">Your package</span>
                        <p>
                            <span class="status-pill success" aria-labelledby="current-package-label">{{ $package['name'] }}</span>
                            MK{{ number_format($package['monthly_price']) }} per month. Contact your PartFlow provider to change package.
                        </p>
                        <small>Included: {{ implode(' · ', $package['highlights']) }}</small>
                    </div>

                    @if ($supportContact)
                        <div class="form-field full">
                            <span>24/7 support</span>
                            <p>
                                @if ($supportContact['support_phone'])
                                    Phone: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $supportContact['support_phone']) }}">{{ $supportContact['support_phone'] }}</a><br>
                                @endif
                                @if ($supportContact['support_whatsapp'])
                                    WhatsApp: <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $supportContact['support_whatsapp']) }}" target="_blank" rel="noopener">{{ $supportContact['support_whatsapp'] }}</a><br>
                                @endif
                                @if ($supportContact['support_email'])
                                    Email: <a href="mailto:{{ $supportContact['support_email'] }}">{{ $supportContact['support_email'] }}</a><br>
                                @endif
                                @if ($supportContact['support_hours'])
                                    <small>{{ $supportContact['support_hours'] }}</small>
                                @endif
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Hidden rather than removed when the package excludes branding: settings.js expects these inputs. --}}
                <div class="branding-settings-card" @unless ($packageFeatures['custom_branding']) hidden style="display: none" @endunless>
                    <div class="branding-logo-control">
                        <div class="branding-logo-preview" data-logo-preview>
                            <x-company-logo :system="$settings" />
                        </div>
                        <div class="branding-logo-fields">
                            <span class="branding-logo-label">Company logo</span>
                            <input class="branding-logo-file-input" id="company_logo" name="company_logo" type="file" accept="image/png,image/jpeg,image/webp" data-logo-input>
                            <div class="branding-logo-picker-row">
                                <label class="btn-secondary branding-logo-button" for="company_logo">Choose logo</label>
                                <span class="branding-logo-file-name" data-logo-file-name>No file selected</span>
                            </div>
                            <small>PNG, JPG, or WebP up to 2 MB. A wide or square logo works best.</small>
                            <x-form-error name="company_logo" />
                        </div>
                    </div>

                    <fieldset class="branding-color-controls">
                        <legend>Application colors</legend>
                        <p>Primary controls navigation, secondary controls actions, and tertiary controls the page background.</p>
                        <div class="branding-color-grid">
                            @foreach ([
                                'primary_color' => ['Primary', '#0a1630'],
                                'secondary_color' => ['Secondary', '#f47a2a'],
                                'tertiary_color' => ['Tertiary', '#f5f6f8'],
                            ] as $colorKey => [$colorLabel, $defaultColor])
                                <label class="branding-color-field" for="{{ $colorKey }}">
                                    <span>{{ $colorLabel }}</span>
                                    <span class="branding-color-input">
                                        <input
                                            id="{{ $colorKey }}"
                                            name="{{ $colorKey }}"
                                            type="color"
                                            value="{{ old($colorKey, $settings[$colorKey] ?? $defaultColor) }}"
                                            data-theme-color="{{ $colorKey }}"
                                        >
                                        <output for="{{ $colorKey }}" data-theme-color-output="{{ $colorKey }}">{{ old($colorKey, $settings[$colorKey] ?? $defaultColor) }}</output>
                                    </span>
                                    <x-form-error :name="$colorKey" />
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <div class="form-grid settings-section">
                    <div class="form-field">
                        <label for="business_name">Company name</label>
                        <input class="form-control" id="business_name" name="business_name" value="{{ old('business_name', $settings['business_name']) }}" required>
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

                    @if ($packageFeatures['low_stock_emails'])
                    <div class="form-field full">
                        <label for="low_stock_notification_email">Low-stock notification email</label>
                        <input
                            class="form-control"
                            id="low_stock_notification_email"
                            name="low_stock_notification_email"
                            type="email"
                            value="{{ old('low_stock_notification_email', $settings['low_stock_notification_email']) }}"
                            autocomplete="email"
                            placeholder="inventory@example.com"
                            aria-describedby="low-stock-notification-help"
                        >
                        <small id="low-stock-notification-help">Receives one email when a part at a branch enters low-stock or out-of-stock status. Leave blank to disable emails.</small>
                        <x-form-error name="low_stock_notification_email" />
                    </div>
                    @endif
                </div>
            </section>

            <section class="settings-panel" id="settings-panel-operating-defaults" role="tabpanel" aria-labelledby="settings-tab-operating-defaults" data-settings-panel="operating-defaults" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Operating defaults</span>
                    <h2>Stock and branch rules</h2>
                    <p>Control the defaults used when products, purchases, stock documents, and POS sales are created.</p>
                </header>

                <div class="form-grid settings-section">
                    <div class="form-field">
                        <label for="default_branch">Default branch</label>
                        <select class="form-control" id="default_branch" name="default_branch">
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption }}" @selected(old('default_branch', $settings['default_branch']) === $branchOption)>{{ $branchOption }}</option>
                            @endforeach
                        </select>
                        <x-form-error name="default_branch" />
                    </div>

                    <div class="form-field">
                        <label for="stock_costing_method">Stock costing method</label>
                        <select class="form-control" id="stock_costing_method" name="stock_costing_method">
                            @foreach ($costingMethods as $costingMethod)
                                <option value="{{ $costingMethod }}" @selected(old('stock_costing_method', $settings['stock_costing_method']) === $costingMethod)>{{ $costingMethod }}</option>
                            @endforeach
                        </select>
                        <x-form-error name="stock_costing_method" />
                    </div>

                    <div class="form-field">
                        <label for="low_stock_policy">Low stock policy</label>
                        <input class="form-control" id="low_stock_policy" name="low_stock_policy" value="{{ $settings['low_stock_policy'] }}">
                        <x-form-error name="low_stock_policy" />
                    </div>

                    <div class="form-field">
                        <label for="maximum_discount_percentage">Maximum sales discount</label>
                        <input class="form-control" id="maximum_discount_percentage" name="maximum_discount_percentage" type="number" min="0" max="100" step="0.01" value="{{ old('maximum_discount_percentage', $settings['maximum_discount_percentage']) }}" aria-describedby="maximum-discount-help">
                        <small id="maximum-discount-help">Percentage cap for sales agents. A product's minimum selling price may impose a stricter limit.</small>
                        <x-form-error name="maximum_discount_percentage" />
                    </div>
                </div>
            </section>

            <section class="settings-panel" id="settings-panel-vehicle-library" role="tabpanel" aria-labelledby="settings-tab-vehicle-library" data-settings-panel="vehicle-library" hidden>
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
                            <button
                                class="btn"
                                type="submit"
                                name="settings_action"
                                value="update_car_make"
                                data-confirm-title="Save car make changes?"
                                data-confirm="Save the changes made to this car make?"
                                data-confirm-label="Save changes"
                            >Update make</button>
                        </div>
                    </div>
                </dialog>

            </section>

            <section class="settings-panel" id="settings-panel-payment-accounts" role="tabpanel" aria-labelledby="settings-tab-payment-accounts" data-settings-panel="payment-accounts" hidden>
                <header class="settings-header">
                    <span class="eyebrow">Payment accounts</span>
                    <h2>Cash, bank, mobile money, and card accounts</h2>
                    <p>Manage the accounts used by payments, purchases, sales, and expense workflows.</p>
                </header>
                <div class="settings-add-row">
                    <div class="filter-form">
                        <input type="search" placeholder="Search accounts..." aria-label="Search payment accounts" data-settings-list-search="payment-account-list">
                    </div>
                    <a class="btn" href="{{ route('web.payment-accounts.create', ['return_to' => route('web.settings.index').'#payment-accounts']) }}">Create account</a>
                </div>
                <div class="form-grid settings-section">
                    <div class="form-field">
                        <label for="default_pos_payment_account_id">Default payment account for the POS</label>
                        <select class="form-control" id="default_pos_payment_account_id" name="default_pos_payment_account_id" aria-describedby="default-pos-account-help">
                            <option value="">None (cashier chooses each sale)</option>
                            @foreach ($posPaymentAccountOptions as $accountOption)
                                <option value="{{ $accountOption->id }}" @selected((string) old('default_pos_payment_account_id', $settings['default_pos_payment_account_id']) === (string) $accountOption->id)>
                                    {{ $accountOption->account_name }} ({{ $accountTypes[$accountOption->account_type] ?? $accountOption->account_type }})
                                </option>
                            @endforeach
                        </select>
                        <small id="default-pos-account-help">Pre-selected at checkout. Cashiers can still pick another account. Click Save settings to apply.</small>
                        <x-form-error name="default_pos_payment_account_id" />
                    </div>
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
                                <a class="btn-secondary" href="{{ route('web.payment-accounts.show', [$account, 'return_to' => route('web.settings.index').'#payment-accounts']) }}">View</a>
                                <a class="icon-action icon-edit" href="{{ route('web.payment-accounts.edit', [$account, 'return_to' => route('web.settings.index').'#payment-accounts']) }}" title="Edit payment account" aria-label="Edit payment account">
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

            <section class="settings-panel" id="settings-panel-user-management" role="tabpanel" aria-labelledby="settings-tab-user-management" data-settings-panel="user-management" hidden>
                <header class="settings-header">
                    <span class="eyebrow">User management</span>
                    <h2>People, access, and branch scope</h2>
                    <p>Add team members, assign roles, and control which site they operate from before API permissions are connected.</p>
                </header>

                <div class="user-management-grid">
                    <section class="user-list" aria-label="Current users">
                        @foreach ($users as $user)
                            <article @class(['inactive' => ! $user['is_active']])>
                                <div>
                                    <strong>{{ $user['name'] }}</strong>
                                    <span>{{ $user['username'] ? '@'.$user['username'].' · ' : '' }}{{ $user['email'] }}</span>
                                </div>
                                <div class="user-status">
                                    <span>{{ $user['role'] ?: 'User' }}</span>
                                    <em>{{ $user['site'] }} - {{ $user['status'] }}</em>
                                </div>
                                <div class="settings-row-actions">
                                    <button
                                        class="icon-action icon-edit"
                                        type="button"
                                        title="Edit user"
                                        aria-label="Edit {{ $user['name'] }}"
                                        data-open-settings-dialog="edit-user"
                                        data-settings-fill
                                        data-edit-user-id="{{ $user['id'] }}"
                                        data-edit-user-name="{{ $user['name'] }}"
                                        data-edit-user-username="{{ $user['username'] }}"
                                        data-edit-user-email="{{ $user['email'] }}"
                                        data-edit-user-role="{{ $user['role'] }}"
                                        data-edit-user-site="{{ $user['site'] }}"
                                        data-edit-user-is-active="{{ $user['is_active'] ? '1' : '0' }}"
                                    >
                                        <x-icons.pencil />
                                    </button>
                                    @if ($user['is_active'] && (int) $user['id'] !== (int) auth()->id())
                                        <button
                                            class="icon-action icon-danger"
                                            type="submit"
                                            name="settings_action"
                                            value="deactivate_user"
                                            formaction="{{ route('web.settings.update', ['user_id' => $user['id']]) }}"
                                            title="Deactivate user"
                                            aria-label="Deactivate {{ $user['name'] }}"
                                            data-confirm-title="Deactivate user?"
                                            data-confirm="Deactivate &quot;{{ $user['name'] }}&quot; and revoke their API access?"
                                            data-confirm-label="Deactivate"
                                        >
                                            <x-icons.trash />
                                        </button>
                                    @endif
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
                                <label for="user_username">Username</label>
                                <input class="form-control" id="user_username" name="user_username" value="{{ old('user_username') }}" autocomplete="username" placeholder="branchmanager">
                                <x-form-error name="user_username" />
                            </div>
                            <div class="form-field">
                                <label for="user_email">Email</label>
                                <input class="form-control" id="user_email" name="user_email" type="email" value="{{ old('user_email') }}" placeholder="manager@partflow.test">
                                <x-form-error name="user_email" />
                            </div>
                            <div class="form-field">
                                <label for="user_role">Role</label>
                                <select class="form-control" id="user_role" name="user_role">
                                    <option value="">No role selected</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected(old('user_role') === $role)>{{ $role }}</option>
                                    @endforeach
                                </select>
                                <x-form-error name="user_role" />
                            </div>
                            <div class="form-field" @unless ($packageFeatures['branch_access']) hidden style="display: none" @endunless>
                                <label for="user_site">Site access</label>
                                <select class="form-control" id="user_site" name="user_site">
                                    <option value="All sites" @selected(old('user_site', 'All sites') === 'All sites')>All sites</option>
                                    @foreach ($sites as $site)
                                        <option value="{{ $site['name'] }}" @selected(old('user_site') === $site['name'])>{{ $site['name'] }}</option>
                                    @endforeach
                                </select>
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

                <dialog class="settings-dialog" data-settings-dialog="edit-user" aria-labelledby="settings-edit-user-title">
                    <div class="settings-dialog-card">
                        <header>
                            <span class="eyebrow">Edit user</span>
                            <h3 id="settings-edit-user-title">Update team member</h3>
                        </header>
                        <input type="hidden" name="edit_user_id" value="{{ old('edit_user_id') }}" data-settings-field="editUserId">
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="edit_user_name">Full name</label>
                                <input class="form-control" id="edit_user_name" name="edit_user_name" value="{{ old('edit_user_name') }}" data-settings-field="editUserName">
                                <x-form-error name="edit_user_name" />
                            </div>
                            <div class="form-field">
                                <label for="edit_user_username">Username</label>
                                <input class="form-control" id="edit_user_username" name="edit_user_username" value="{{ old('edit_user_username') }}" autocomplete="username" data-settings-field="editUserUsername">
                                <x-form-error name="edit_user_username" />
                            </div>
                            <div class="form-field">
                                <label for="edit_user_email">Email</label>
                                <input class="form-control" id="edit_user_email" name="edit_user_email" type="email" value="{{ old('edit_user_email') }}" data-settings-field="editUserEmail">
                                <x-form-error name="edit_user_email" />
                            </div>
                            <div class="form-field">
                                <label for="edit_user_role">Role</label>
                                <select class="form-control" id="edit_user_role" name="edit_user_role" data-settings-field="editUserRole">
                                    <option value="">No role selected</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected(old('edit_user_role') === $role)>{{ $role }}</option>
                                    @endforeach
                                </select>
                                <x-form-error name="edit_user_role" />
                            </div>
                            <div class="form-field" @unless ($packageFeatures['branch_access']) hidden style="display: none" @endunless>
                                <label for="edit_user_site">Site access</label>
                                <select class="form-control" id="edit_user_site" name="edit_user_site" data-settings-field="editUserSite">
                                    <option value="All sites" @selected(old('edit_user_site', 'All sites') === 'All sites')>All sites</option>
                                    @foreach ($sites as $site)
                                        <option value="{{ $site['name'] }}" @selected(old('edit_user_site') === $site['name'])>{{ $site['name'] }}</option>
                                    @endforeach
                                </select>
                                <x-form-error name="edit_user_site" />
                            </div>
                            <div class="form-field full">
                                <label for="edit_user_password">New password optional</label>
                                <input class="form-control" id="edit_user_password" name="edit_user_password" type="password" autocomplete="new-password" placeholder="Leave blank to keep the current password">
                                <x-form-error name="edit_user_password" />
                            </div>
                            <label class="checkbox-row form-field full" for="edit_user_is_active">
                                <input id="edit_user_is_active" type="checkbox" name="edit_user_is_active" value="1" @checked(old('edit_user_is_active')) data-settings-field="editUserIsActive">
                                <span>Active account</span>
                            </label>
                        </div>
                        <div class="settings-dialog-actions">
                            <button class="btn-secondary" type="button" data-close-settings-dialog>Cancel</button>
                            <button
                                class="btn"
                                type="submit"
                                name="settings_action"
                                value="update_user"
                                data-confirm-title="Save user changes?"
                                data-confirm="Save this user's role, site access, account status, and profile changes?"
                                data-confirm-label="Save changes"
                            >Update user</button>
                        </div>
                    </div>
                </dialog>
            </section>

            <div class="settings-save-bar">
                <button class="btn-secondary" type="button" data-settings-prev>Previous</button>
                <span data-settings-progress>Step 1</span>
                <button class="btn-secondary" type="button" data-settings-next>Next</button>
                <button
                    class="btn"
                    type="submit"
                    name="settings_action"
                    value="save_settings"
                    formaction="{{ route('web.settings.business-information.update') }}"
                    data-confirm-title="Save settings changes?"
                    data-confirm="Save the changes made to these settings?"
                    data-confirm-label="Save changes"
                >Save settings</button>
            </div>
        </form>
    </section>
@endsection
