@props(['system' => []])

@if (! empty($system['company_logo_url']))
    <img class="company-logo-image" src="{{ $system['company_logo_url'] }}" alt="{{ $system['business_name'] ?? 'Company' }} logo">
@else
    <x-brand-icon />
@endif
