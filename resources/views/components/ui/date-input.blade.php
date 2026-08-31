@props([
    'placeholder' => null,
    'readonly' => false,
    'disabled' => false,
    'id' => null,
])

@php
    // The Livewire property this writes to. It holds `Y-m-d` throughout — the
    // display format never reaches the server.
    $model = $attributes->wire('model')->value();
    $live = $attributes->wire('model')->hasModifier('live');

    $isBR = config('app.country') === 'BR';
    $hint = $isBR ? __('dd/mm/aaaa') : __('mm/dd/yyyy');

    // Derived from the property, not random: a fresh id on every render is a
    // changed attribute for Livewire's morph to chase on every keystroke.
    $uid = $id ?? 'date-input-'.\Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', (string) $model));

    $classes = $attributes->get('class') ?: 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';

    // The class and the state flags are rendered explicitly below, so they are
    // taken out of the bag rather than being written twice.
    $forwarded = $attributes
        ->whereDoesntStartWith('wire:model')
        ->except(['class', 'id', 'disabled', 'readonly', 'placeholder']);
@endphp

{{--
    A date field that reads the way this install writes dates.

    `<input type="date">` shows whatever the *browser's* locale says, which has
    nothing to do with `app.country`: a Brazilian company whose staff run an
    en-US browser were typing into `mm/dd/yyyy` all day. So the box people read
    and type into is a text field in this install's order, and the native
    control is kept alongside it — hidden, and opened by the calendar button —
    purely for its picker. The value moving to and from Livewire is always
    `Y-m-d`, exactly as before.
--}}
<div class="relative"
     @if($live)
         x-data="dateInput(@entangle($model).live, @js($isBR))"
     @else
         x-data="dateInput(@entangle($model), @js($isBR))"
     @endif>

    <input type="text"
           id="{{ $uid }}"
           x-model="display"
           x-on:input="onInput($event)"
           x-on:blur="commit()"
           x-on:keydown.enter="commit()"
           inputmode="numeric"
           autocomplete="off"
           placeholder="{{ $placeholder ?? $hint }}"
           @if($readonly) readonly @endif
           @if($disabled) disabled @endif
           class="{{ $classes }} pr-10"
           {{ $forwarded }}>

    @unless($readonly || $disabled)
        <button type="button"
                x-on:click="openPicker()"
                tabindex="-1"
                aria-label="{{ __('Open the calendar') }}"
                title="{{ __('Open the calendar') }}"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </button>

        {{-- Kept for its picker only: never read, never shown, and out of the
             tab order, so its own locale never reaches the reader. --}}
        <input type="date"
               x-ref="native"
               x-on:change="takeFromPicker($event)"
               tabindex="-1"
               aria-hidden="true"
               class="pointer-events-none absolute bottom-0 right-8 h-0 w-0 opacity-0">
    @endunless
</div>
