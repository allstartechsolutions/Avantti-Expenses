@props(['alt' => null])

{{--
    The square mark: the company's own icon when one was uploaded on the
    Company Information screen, the product's otherwise. A dark-mode twin is
    optional — a navy logo is invisible on the dark sidebar — and when one
    exists both are rendered, with the theme choosing which is shown.
--}}
@php
    $label = $alt ?? App\Services\Branding::name();
    $darkIcon = App\Services\Branding::darkIconUrl();
@endphp

<img
    src="{{ App\Services\Branding::iconUrl() }}"
    alt="{{ $label }}"
    {{ $attributes->class(['object-contain select-none', 'dark:hidden' => (bool) $darkIcon]) }}
/>

@if($darkIcon)
    <img
        src="{{ $darkIcon }}"
        alt="{{ $label }}"
        {{ $attributes->class(['object-contain select-none hidden dark:block']) }}
    />
@endif
