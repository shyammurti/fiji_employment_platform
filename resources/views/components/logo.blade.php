{{-- Platform emblem. Decorative next to the site name, so empty alt text by default. --}}
@props(['size' => 44, 'alt' => ''])
<img src="{{ asset('images/logo.png') }}" width="{{ $size }}" height="{{ $size }}" alt="{{ $alt }}" {{ $attributes->merge(['class' => 'shrink-0 select-none']) }} style="width: {{ $size }}px; height: {{ $size }}px" decoding="async">
