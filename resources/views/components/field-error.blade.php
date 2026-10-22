@props(['name'])
@error($name)
    <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
@enderror
