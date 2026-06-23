@props(['label' => 'Rows per page'])

<div class="page-size-controls" aria-label="{{ $label }}">
    <span>{{ $label }}</span>
    <a class="active" href="#">10</a>
    <a href="#">25</a>
    <a href="#">50</a>
</div>
