@csrf

@php
    $selectedType = old('account_type', $paymentAccount->account_type ?: 'cash');
@endphp

<div class="payment-account-builder" data-payment-account-builder>
    <div class="form-field">
        <label for="account_name">Account name</label>
        <input
            class="form-control"
            id="account_name"
            name="account_name"
            type="text"
            value="{{ old('account_name', $paymentAccount->account_name) }}"
            required
        >
        <x-form-error name="account_name" />
    </div>

    <fieldset class="payment-type-options">
        <legend>Account type</legend>
        @foreach ($accountTypes as $value => $label)
            <label @class(['payment-type-card', 'active' => $selectedType === $value])>
                <input
                    type="radio"
                    name="account_type"
                    value="{{ $value }}"
                    data-payment-type
                    @checked($selectedType === $value)
                    required
                >
                <span>{{ $label }}</span>
                <small>
                    @switch($value)
                        @case('cash')
                            Till, drawer, petty cash, or cash box.
                            @break
                        @case('mobile_money')
                            Airtel Money, TNM Mpamba, or wallet.
                            @break
                        @case('bank')
                            Bank account used for settlements.
                            @break
                        @default
                            Card terminal or processor account.
                    @endswitch
                </small>
            </label>
        @endforeach
        <x-form-error name="account_type" />
    </fieldset>

    <div class="payment-account-layout">
        <div class="form-grid payment-account-fields">
            <div class="form-field" data-account-field="bank_name">
                <label for="bank_name" data-account-label="bank_name">Bank name</label>
                <input
                    class="form-control"
                    id="bank_name"
                    name="bank_name"
                    type="text"
                    value="{{ old('bank_name', $paymentAccount->bank_name) }}"
                    data-account-input="bank_name"
                >
                <small data-account-help="bank_name">Bank, provider, processor, or cash location.</small>
                <x-form-error name="bank_name" />
            </div>

            <div class="form-field" data-account-field="account_number">
                <label for="account_number" data-account-label="account_number">Account number</label>
                <input
                    class="form-control"
                    id="account_number"
                    name="account_number"
                    type="text"
                    value="{{ old('account_number', $paymentAccount->account_number) }}"
                    data-account-input="account_number"
                >
                <small data-account-help="account_number">Account number, drawer code, or merchant ID.</small>
                <x-form-error name="account_number" />
            </div>

            <div class="form-field" data-account-field="mobile_number">
                <label for="mobile_number" data-account-label="mobile_number">Mobile number</label>
                <input
                    class="form-control"
                    id="mobile_number"
                    name="mobile_number"
                    type="text"
                    value="{{ old('mobile_number', $paymentAccount->mobile_number) }}"
                    data-account-input="mobile_number"
                >
                <small data-account-help="mobile_number">Wallet number for mobile money accounts.</small>
                <x-form-error name="mobile_number" />
            </div>

            <div class="form-field" data-account-field="account_holder_name">
                <label for="account_holder_name" data-account-label="account_holder_name">Account holder</label>
                <input
                    class="form-control"
                    id="account_holder_name"
                    name="account_holder_name"
                    type="text"
                    value="{{ old('account_holder_name', $paymentAccount->account_holder_name) }}"
                    data-account-input="account_holder_name"
                >
                <small data-account-help="account_holder_name">Registered owner, custodian, or settlement holder.</small>
                <x-form-error name="account_holder_name" />
            </div>
        </div>

        <aside class="payment-preview-card" aria-label="Payment account preview">
            <span class="eyebrow">Preview</span>
            <strong data-payment-preview-name>{{ old('account_name', $paymentAccount->account_name ?: 'Account name') }}</strong>
            <p data-payment-preview-type>{{ $accountTypes[$selectedType] ?? 'Cash' }}</p>
            <dl>
                <div data-payment-preview-row="bank_name">
                    <dt data-payment-preview-label="bank_name">Location</dt>
                    <dd data-payment-preview-value="bank_name">Not set</dd>
                </div>
                <div data-payment-preview-row="account_number">
                    <dt data-payment-preview-label="account_number">Till code</dt>
                    <dd data-payment-preview-value="account_number">Not set</dd>
                </div>
                <div data-payment-preview-row="mobile_number">
                    <dt data-payment-preview-label="mobile_number">Mobile number</dt>
                    <dd data-payment-preview-value="mobile_number">Not set</dd>
                </div>
                <div data-payment-preview-row="account_holder_name">
                    <dt data-payment-preview-label="account_holder_name">Custodian</dt>
                    <dd data-payment-preview-value="account_holder_name">Not set</dd>
                </div>
            </dl>
        </aside>
    </div>

    <div class="form-field full">
        <label class="checkbox-field themed-checkbox">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                @checked(old('is_active', $paymentAccount->is_active ?? true))
            >
            Active account
        </label>
        <x-form-error name="is_active" />
    </div>
</div>

<div class="form-actions">
    <a class="btn-secondary" href="{{ route('web.payment-accounts.index') }}">Cancel</a>
    <button class="btn" type="submit">{{ $submitLabel }}</button>
</div>
