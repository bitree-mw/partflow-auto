@props(['current'])

@php
    $steps = [
        'car-models' => [
            'number' => 1,
            'title' => 'Car Models',
            'description' => 'Start by defining make, model, engine, year, variant, and origin.',
            'href' => route('web.catalog.car-models.index'),
        ],
        'part-types' => [
            'number' => 2,
            'title' => 'Part Types',
            'description' => 'Then create reusable categories and product-code segments.',
            'href' => route('web.catalog.part-types.index'),
        ],
        'products' => [
            'number' => 3,
            'title' => 'Parts Catalogue',
            'description' => 'Finish by creating sellable parts with fitment, pricing, and references.',
            'href' => route('web.catalog.products.index'),
        ],
    ];

    $currentIndex = $steps[$current]['number'] ?? 1;
@endphp

<section class="catalog-workflow" aria-label="Catalogue setup workflow">
    @foreach ($steps as $key => $step)
        <a
            href="{{ $step['href'] }}"
            @class([
                'workflow-step',
                'active' => $key === $current,
                'complete' => $step['number'] < $currentIndex,
            ])
        >
            <span>{{ $step['number'] }}</span>
            <strong>{{ $step['title'] }}</strong>
            <p>{{ $step['description'] }}</p>
        </a>
    @endforeach
</section>
