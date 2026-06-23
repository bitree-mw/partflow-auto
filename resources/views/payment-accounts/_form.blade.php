@csrf

<div class="form-grid">
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

    <div class="form-field">
        <label for="account_type">Account type</label>
        <select class="form-control" id="account_type" name="account_type" required>
            <option value="">Select type</option>
            @foreach ($accountTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('account_type', $paymentAccount->account_type) === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <x-form-error name="account_type" />
    </div>

    <div class="form-field">
        <label for="bank_name">Bank name</label>
        <input
            class="form-control"
            id="bank_name"
            name="bank_name"
            type="text"
            value="{{ old('bank_name', $paymentAccount->bank_name) }}"
        >
        <x-form-error name="bank_name" />
    </div>

    <div class="form-field">
        <label for="account_number">Account number</label>
        <input
            class="form-control"
            id="account_number"
            name="account_number"
            type="text"
            value="{{ old('account_number', $paymentAccount->account_number) }}"
        >
        <x-form-error name="account_number" />
    </div>

    <div class="form-field">
        <label for="mobile_number">Mobile number</label>
        <input
            class="form-control"
            id="mobile_number"
            name="mobile_number"
            type="text"
            value="{{ old('mobile_number', $paymentAccount->mobile_number) }}"
        >
        <x-form-error name="mobile_number" />
    </div>

    <div class="form-field">
        <label for="account_holder_name">Account holder</label>
        <input
            class="form-control"
            id="account_holder_name"
            name="account_holder_name"
            type="text"
            value="{{ old('account_holder_name', $paymentAccount->account_holder_name) }}"
        >
        <x-form-error name="account_holder_name" />
    </div>

    <div class="form-field full">
        <label class="checkbox-field">
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
