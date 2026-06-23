@props(['id' => 'countries-list', 'countries' => config('countries')])

<datalist id="{{ $id }}">
    @foreach ($countries as $country)
        <option value="{{ $country }}"></option>
    @endforeach
</datalist>
