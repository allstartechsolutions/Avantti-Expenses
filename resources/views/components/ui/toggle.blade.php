@props([
    'label' => null,        // text beside the switch; falls back to on/off labels
    'onLabel' => null,      // shown when the switch is on
    'offLabel' => null,     // shown when the switch is off
    'description' => null,  // helper line under the switch
    'checked' => false,     // current value, for choosing on/off label text
    'disabled' => false,
])

{{--
    The switch used across the app (catalog, payment batches, module access).
    Written once here so every screen gets the same control instead of the
    class string being copied again.

    Usage: <x-ui.toggle wire:model="is_internal" :checked="$is_internal"
                        onLabel="Internal only" offLabel="Everyone" />

    Two ways to drive it, and `:checked` is required by both:

    - `wire:model` — a bound property; Livewire keeps the input in step.
    - `wire:click="toggle(...)"` — a method call; the input shows `:checked`
      as rendered by the server. Give the surrounding element a `wire:key`
      that includes the state, so a flip rebuilds the input rather than
      relying on the morph to change a checkbox's `checked` in place.

    The click handlers are forwarded on purpose: this control used to pass
    only `wire:model`, so a `wire:click` on it never fired and the switch
    never showed its state — which is how the System Settings notification
    switches sat broken until 2 Sep 2026.
--}}
<div>
    <label @class(['relative inline-flex items-center', 'cursor-pointer' => ! $disabled, 'opacity-60 cursor-not-allowed' => $disabled])>
        <input
            type="checkbox"
            @disabled($disabled)
            @checked($checked)
            {{ $attributes->whereStartsWith(['wire:model', 'x-model', 'name', 'id', '@change', 'x-on:change', 'wire:click', '@click', 'x-on:click']) }}
            class="sr-only peer">
        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#3F5189]/20 dark:peer-focus:ring-[#3F5189]/40 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-[#3F5189]"></div>

        @if($label || $onLabel || $offLabel)
            <span class="ms-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                {{ $label ?? ($checked ? $onLabel : $offLabel) }}
            </span>
        @endif
    </label>

    @if($description)
        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
    @endif
</div>
