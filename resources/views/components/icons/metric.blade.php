@props(['name' => 'chart'])

{{-- Line icons for dashboard metric cards; size and stroke are inline so no stylesheet rebuild is needed. --}}
<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('inventory')
            <path d="M21 8 12 3 3 8l9 5 9-5Z" />
            <path d="M3 8v8l9 5 9-5V8" />
            <path d="M12 13v8" />
            @break
        @case('low-stock')
            <path d="M10.3 3.9 2.4 17.5A2 2 0 0 0 4.1 20.5h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
            @break
        @case('orders')
            <rect x="6" y="4" width="12" height="17" rx="2" />
            <path d="M9 4V3h6v1" />
            <path d="M9 10h6" />
            <path d="M9 14h6" />
            <path d="M9 18h3" />
            @break
        @case('sales')
            <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z" />
            <path d="M9 8h6" />
            <path d="M9 12h6" />
            <path d="M9 16h4" />
            @break
        @case('profit')
            <path d="M4 17l5-5 4 4 7-8" />
            <path d="M15 8h5v5" />
            @break
        @case('expenses')
            <path d="M4 7h15a2 2 0 0 1 2 2v9H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h12" />
            <path d="M16 13h5" />
            @break
        @case('debt')
            <circle cx="9" cy="8" r="4" />
            <path d="M3 21v-2a4 4 0 0 1 4-4h4" />
            <path d="M17 14v7" />
            <path d="M14 18l3 3 3-3" />
            @break
        @case('out-of-stock')
            <path d="M21 8 12 3 3 8l9 5 9-5Z" />
            <path d="M3 8v8l9 5" />
            <path d="M16 16l5 5" />
            <path d="M21 16l-5 5" />
            @break
        @default
            <path d="M5 20V10" />
            <path d="M12 20V4" />
            <path d="M19 20v-7" />
    @endswitch
</svg>
