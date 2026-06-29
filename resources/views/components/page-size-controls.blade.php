@props(['label' => 'Rows per page', 'sizes' => [10, 25, 50]])

@php
    $current = (int) request('per_page', 10);
@endphp

<div class="page-size-controls" aria-label="{{ $label }}">
    <span>{{ $label }}</span>
    @foreach ($sizes as $size)
        @php
            $query = request()->query();
            unset($query['page']);
            $query['per_page'] = $size;
            $url = url()->current().($query ? '?'.http_build_query($query) : '');
        @endphp
        <a @class(['active' => $current === (int) $size]) href="{{ $url }}">{{ $size }}</a>
    @endforeach
</div>
