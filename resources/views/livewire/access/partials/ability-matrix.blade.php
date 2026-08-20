{{--
    The ability matrix, shared by the role editor, the template editor and the
    member editor on the Team tab. Expects:

      $sections  — from HasAbilityMatrix::buildMatrix()
      $readOnly  — bool
      $search    — the current filter term, for the empty state
--}}
@php
    $matrixCard = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
    $matrixField = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <input type="text" wire:model.live.debounce.200ms="matrixSearch"
               class="{{ $matrixField }} md:max-w-sm" placeholder="{{ __('Filter areas…') }}">
        <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>{{ __('Sensitive') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-slate-400"></span>{{ __('Not enforced yet') }}
            </span>
        </div>
    </div>

    @foreach($sections as $section)
        <div class="{{ $matrixCard }} overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $section['name'] }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $section['hint'] }}</p>
                </div>
                @unless($readOnly)
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" wire:click="toggleSection('{{ $section['key'] }}', true)"
                                class="text-xs font-medium text-[#3F5189] dark:text-[#8fa0d8] hover:underline">{{ __('Grant all') }}</button>
                        <span class="text-slate-300 dark:text-slate-600">|</span>
                        <button type="button" wire:click="toggleSection('{{ $section['key'] }}', false)"
                                class="text-xs font-medium text-slate-500 dark:text-slate-400 hover:underline">{{ __('Clear') }}</button>
                    </div>
                @endunless
            </div>

            @forelse($section['areas'] as $area)
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50 last:border-b-0 grid grid-cols-1 lg:grid-cols-4 gap-3">
                    <div class="lg:col-span-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $area['name'] }}</span>
                            @unless($area['enforced'])
                                <span class="w-2 h-2 rounded-full bg-slate-400" title="{{ __('Not enforced yet — this module has not been converted') }}"></span>
                            @endunless
                        </div>
                        @unless($readOnly)
                            <div class="mt-1 flex items-center gap-2">
                                <button type="button" wire:click="toggleArea('{{ $area['key'] }}', true)"
                                        class="text-xs text-[#3F5189] dark:text-[#8fa0d8] hover:underline">{{ __('All') }}</button>
                                <span class="text-slate-300 dark:text-slate-600 text-xs">|</span>
                                <button type="button" wire:click="toggleArea('{{ $area['key'] }}', false)"
                                        class="text-xs text-slate-500 dark:text-slate-400 hover:underline">{{ __('None') }}</button>
                            </div>
                        @endunless
                    </div>

                    <div class="lg:col-span-3 flex flex-wrap gap-x-5 gap-y-2">
                        @foreach($area['actions'] as $action)
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="granted.{{ $area['key'] }}.{{ $action['key'] }}"
                                       class="h-4 w-4 rounded border-slate-300 text-[#3F5189] focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700"
                                       @disabled($readOnly)>
                                <span class="text-sm text-slate-700 dark:text-slate-300 inline-flex items-center gap-1.5">
                                    {{ __($action['name']) }}
                                    @if($action['sensitive'])
                                        <span class="w-2 h-2 rounded-full bg-amber-500" title="{{ __('Sensitive — grant with care') }}"></span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Nothing matches ":term".', ['term' => $search]) }}
                </div>
            @endforelse
        </div>
    @endforeach
</div>
