@php
    $navigation = [
        ['label' => 'POS', 'href' => url('/pos'), 'active' => request()->is('pos')],
        ['label' => 'Payment Accounts', 'href' => route('web.payment-accounts.index'), 'active' => request()->routeIs('web.payment-accounts.*')],
    ];
@endphp

<aside class="app-sidebar" aria-label="Primary navigation">
    <a class="brand-mark" href="{{ url('/pos') }}">
        <span>PF</span>
        <strong>PartFlow Auto</strong>
    </a>

    <nav class="sidebar-nav">
        @foreach ($navigation as $item)
            <a href="{{ $item['href'] }}" @class(['active' => $item['active']])>
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</aside>
