@props([
    'suggestions' => [],       // existing tag names offered as you type
    'placeholder' => null,
    'max' => 15,               // matches the server-side cap in ManagesDocuments
])

@php
    // The Livewire property this input writes to, as a comma-separated string.
    $model = $attributes->wire('model')->value();
    $names = collect($suggestions)->map(fn ($tag) => is_string($tag) ? $tag : $tag->name)->values();
@endphp

{{--
    Tag entry: chips for what is set, a field to add more. Enter, Tab or a
    comma commits the tag; Backspace on an empty field takes the last one back.
--}}
<div
    x-data="tagInput(@entangle($model), @js($names), {{ (int) $max }})"
    x-id="['tag-input']"
    class="w-full"
>
    <div
        @click="$refs.draft.focus()"
        :class="focused ? 'border-[#3F5189] ring-2 ring-[#3F5189]/20' : 'border-slate-300 dark:border-slate-600'"
        class="flex flex-wrap items-center gap-1.5 w-full min-h-[42px] px-2 py-1.5 border rounded-lg bg-white dark:bg-slate-700 cursor-text transition"
    >
        <template x-for="tag in tags" :key="tag">
            <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-full text-xs font-medium bg-[#3F5189]/10 dark:bg-[#3F5189]/30 text-[#3F5189] dark:text-[#8B9DD6]">
                <span x-text="tag"></span>
                <button
                    type="button"
                    @click.stop="remove(tag)"
                    class="rounded-full p-0.5 hover:bg-[#3F5189]/20 dark:hover:bg-[#3F5189]/40"
                    :aria-label="`{{ __('Remove tag') }} ${tag}`"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </span>
        </template>

        <input
            type="text"
            x-ref="draft"
            x-model="draft"
            @keydown="onKeydown($event)"
            @focus="focused = true"
            @blur="onBlur()"
            :disabled="full"
            :placeholder="tags.length ? '' : @js($placeholder ?? __('Type a tag and press Enter'))"
            class="flex-1 min-w-[8rem] border-0 bg-transparent p-0.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-0 disabled:cursor-not-allowed"
        >
    </div>

    {{-- Tags already used elsewhere in the system, so the same word is not typed two ways --}}
    <div x-show="focused && available.length" x-cloak class="mt-1.5 flex flex-wrap gap-1.5">
        <template x-for="tag in available" :key="tag">
            <button
                type="button"
                @mousedown.prevent="add(tag)"
                class="px-2 py-0.5 rounded-full text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600"
                x-text="tag"
            ></button>
        </template>
    </div>

    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
        <span x-show="! full">{{ __('Press Enter or Tab to add a tag.') }}</span>
        <span x-show="full" x-cloak>{{ __('Up to :count tags.', ['count' => (int) $max]) }}</span>
    </p>
</div>
