@props([
    'label' => null,
    'placeholder' => null,
    'hint' => null,             // steady help text under the field
    'results' => [],            // [['id' => 1, 'label' => 'Code Name', 'meta' => 'secondary line'], ...]
    'selectedId' => null,       // the id currently linked, or null
    'selectedLabel' => null,    // what that id is called, for the input and the read-back line
    'select' => null,           // Livewire method called with the chosen id
    'clear' => null,            // Livewire method that unlinks
    'search' => '',             // the current search text, for the empty / unpicked states
    'minChars' => 2,
    'error' => null,            // validation key to render under the field
    'empty' => null,            // message when the search found nothing
    'unavailable' => null,      // message when there is nothing to search at all — replaces the field
    'total' => null,            // how many records are searchable, shown as reassurance
])

@php
    // The Livewire property the input writes to. The wire:model attribute is
    // forwarded verbatim so the caller keeps control of the debounce.
    $model = $attributes->wire('model')->value();
    $uid = 'search-select-'.\Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', (string) $model));

    $results = collect($results)->values();
    $typed = trim((string) $search) !== '';
    $searched = mb_strlen(trim((string) $search)) >= (int) $minChars;

    // Typed something, nothing linked, and the server came back with nothing.
    $showEmpty = $searched && ! $selectedId && $results->isEmpty();
@endphp

{{--
    Type-to-search picker over a set too large for a <select>.

    The search runs on the server (the caller's `wire:model.live.debounce`), so
    thousands of rows never reach the browser; the keyboard layer is Alpine's,
    lifted from the header search. Arrow keys browse, Enter takes the
    highlighted row, Escape closes, and the row that is linked is stated in
    words underneath — an id chosen by mistake is otherwise invisible.
--}}
<div>
    @if($label)
        <label for="{{ $uid }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
        </label>
    @endif

    @if($unavailable)
        <div class="mt-1 px-3 py-2 border border-dashed border-slate-300 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-900/40">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $unavailable }}</p>
        </div>
    @else
        <div class="relative"
             x-data="{
                 open: false,
                 active: -1,
                 items() {
                     return this.$refs.list ? Array.from(this.$refs.list.querySelectorAll('[data-option]')) : [];
                 },
                 move(step) {
                     const items = this.items();
                     if (! items.length) { return; }
                     this.open = true;
                     this.active = (this.active + step + items.length) % items.length;
                     items[this.active].scrollIntoView({ block: 'nearest' });
                 },
                 choose() {
                     const items = this.items();

                     // Enter takes the row the user pointed at. With nothing
                     // highlighted it takes a result only when there is exactly
                     // one — otherwise typing a whole vendor name and pressing
                     // Enter would link whichever row happened to sort first.
                     const target = this.active >= 0
                         ? items[this.active]
                         : (items.length === 1 ? items[0] : null);

                     if (target) { target.click(); }

                     this.close();
                 },
                 close() {
                     this.open = false;
                     this.active = -1;
                 },
             }"
             @click.away="close()"
             @keydown.escape.stop="close()">

            <input id="{{ $uid }}"
                   x-ref="input"
                   type="text"
                   autocomplete="off"
                   spellcheck="false"
                   role="combobox"
                   aria-autocomplete="list"
                   aria-controls="{{ $uid }}-list"
                   :aria-expanded="open ? 'true' : 'false'"
                   {{ $attributes->whereStartsWith('wire:model') }}
                   @focus="open = {{ $results->isNotEmpty() ? 'true' : 'false' }}"
                   @input="open = true; active = -1"
                   @keydown.arrow-down.prevent="move(1)"
                   @keydown.arrow-up.prevent="move(-1)"
                   @keydown.enter="if (open) { $event.preventDefault(); choose(); }"
                   placeholder="{{ $placeholder ?? __('Type to search...') }}"
                   @class([
                       'mt-1 w-full pl-3 pr-20 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white',
                       'border-slate-300 dark:border-slate-600' => ! $error || ! $errors->has($error),
                       'border-red-500' => $error && $errors->has($error),
                   ])>

            <div class="absolute inset-y-0 right-0 mt-1 flex items-center gap-1 pr-2">
                {{-- The debounced request, while it is in flight --}}
                @if($model)
                    <span wire:loading.delay.shortest wire:target="{{ $model }}" class="flex items-center">
                        <svg class="w-4 h-4 animate-spin text-[#3F5189] dark:text-slate-300" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                @endif

                @if($selectedId)
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                @endif

                @if($typed && $clear)
                    <button type="button"
                            wire:click="{{ $clear }}"
                            @click="$refs.input.focus(); close()"
                            aria-label="{{ __('Clear search') }}"
                            class="p-0.5 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Results --}}
            <div x-show="open"
                 x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute left-0 right-0 top-full z-50 mt-1 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 overflow-hidden">

                @if($results->isNotEmpty())
                    <ul x-ref="list" id="{{ $uid }}-list" role="listbox" class="max-h-64 overflow-y-auto py-1">
                        @foreach($results as $index => $row)
                            <li>
                                <button type="button"
                                        data-option
                                        role="option"
                                        aria-selected="{{ (string) $row['id'] === (string) $selectedId ? 'true' : 'false' }}"
                                        wire:click="{{ $select }}({{ $row['id'] }})"
                                        @click="close()"
                                        @mouseenter="active = {{ $index }}"
                                        :class="active === {{ $index }} ? 'bg-slate-100 dark:bg-slate-700' : ''"
                                        class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                    <span class="block text-sm font-medium text-slate-800 dark:text-white truncate">
                                        {{ $row['label'] }}
                                    </span>
                                    @if(! empty($row['meta']))
                                        <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">
                                            {{ $row['meta'] }}
                                        </span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="px-4 py-2 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ __('↑↓ to browse · ↵ to choose · Esc to close') }}
                        </span>
                    </div>
                @elseif($showEmpty)
                    <div class="px-4 py-4">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ __('Nothing matches ":term".', ['term' => trim((string) $search)]) }}
                        </p>
                        @if($empty)
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $empty }}</p>
                        @endif
                    </div>
                @else
                    <div class="px-4 py-4">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ trans_choice('Type at least :count character to search.|Type at least :count characters to search.', (int) $minChars, ['count' => (int) $minChars]) }}
                        </p>
                        @if($total !== null)
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                                {{ trans_choice(':count record can be searched.|:count records can be searched.', (int) $total, ['count' => number_format((int) $total, 0, ',', '.')]) }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- What is actually linked, in words. The input holding a name is not
             proof that an id went with it — a half-typed search looks the same. --}}
        @if($selectedId)
            <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400 truncate">
                {{ __('Linked: :name', ['name' => $selectedLabel]) }}
            </p>
        @elseif($typed)
            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                {{ __('Choose one from the list — nothing is linked yet.') }}
            </p>
        @elseif($hint)
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
        @endif
    @endif

    @if($error)
        @error($error)
            <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    @endif
</div>
