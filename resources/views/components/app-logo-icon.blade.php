@props(['alt' => null])

<img
    src="{{ config('app.logo_url') }}"
    alt="{{ $alt ?? config('app.name') }}"
    {{ $attributes->class(['object-contain select-none']) }}
/>
