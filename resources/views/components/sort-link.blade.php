@props(['field', 'label'])

@php
    $currentSort = request('sort', 'status');
    $currentDirection = request('direction', 'desc');
    $active = $currentSort === $field;
    $nextDirection = $active && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = request()->query();
    unset($query['page']);
    $query['sort'] = $field;
    $query['direction'] = $nextDirection;
    $url = url()->current().($query ? '?'.http_build_query($query) : '');
@endphp

<a
    @class(['sort-link', 'active' => $active, 'desc' => $active && $currentDirection === 'desc'])
    href="{{ $url }}"
    aria-sort="{{ $active ? ($currentDirection === 'desc' ? 'descending' : 'ascending') : 'none' }}"
>
    <span>{{ $label }}</span>
    <span class="sort-indicator" aria-hidden="true"></span>
</a>
