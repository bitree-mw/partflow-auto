@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('header_actions')
    <a class="btn-secondary" href="{{ $mode === 'customers' ? route('web.customers.index') : route('web.suppliers.index') }}">Back to {{ $mode }}</a>
@endsection

@section('content')
    <form class="form-panel" method="POST" action="{{ $action }}">
        @csrf

        <div class="form-grid">
            <div class="form-field">
                <label for="code">Account code</label>
                <input class="form-control" id="code" name="code" placeholder="{{ $mode === 'customers' ? 'CUS-004' : 'SUP-004' }}">
            </div>

            <div class="form-field">
                <label for="name">{{ $mode === 'customers' ? 'Customer' : 'Supplier' }} name</label>
                <input class="form-control" id="name" name="name" placeholder="{{ $mode === 'customers' ? 'Garage or customer name' : 'Supplier company name' }}">
            </div>

            <div class="form-field">
                <label for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" placeholder="+265 ...">
            </div>

            <div class="form-field">
                <label for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" placeholder="name@example.com">
            </div>

            <div class="form-field">
                <label for="tax_number">Tax number</label>
                <input class="form-control" id="tax_number" name="tax_number" placeholder="Tax registration">
            </div>

            <div class="form-field">
                <label for="credit_limit">Credit limit</label>
                <input class="form-control" id="credit_limit" name="credit_limit" type="number" placeholder="500000">
            </div>

            <div class="form-field full">
                <label for="address">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3" placeholder="Physical address"></textarea>
            </div>

            <div class="form-field full">
                <label for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="{{ $mode === 'customers' ? 'Payment habits, credit terms, vehicle fleet notes' : 'Lead time, supplier terms, return process notes' }}"></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn-secondary" href="{{ $mode === 'customers' ? route('web.customers.index') : route('web.suppliers.index') }}">Cancel</a>
            <button class="btn" type="submit">Save {{ $mode === 'customers' ? 'customer' : 'supplier' }}</button>
        </div>
    </form>
@endsection
