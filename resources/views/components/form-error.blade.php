@props(['name'])

@error($name)
    <p class="form-error">{{ $message }}</p>
@enderror
