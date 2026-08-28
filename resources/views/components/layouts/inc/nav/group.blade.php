@props(['group'])

{{-- A collapsible sidebar group. Rendered only when it has children — see
     App\Services\Navigation, which drops empty groups rather than showing a
     heading that opens onto nothing. --}}
<div class="mb-1" x-data="railFlyout"
     @mouseenter="rail && show()" @mouseleave="hide()"
     @focusin="rail && show()"
     @focusout="$el.contains($event.relatedTarget) || hide()"
     @keydown.escape="hide()" @click.outside="open = false"
     @rail-reposition.window="open && place()"
     x-effect="rail || hide()">
    <button @click="rail ? toggle() : toggleSubmenu('{{ $group['key'] }}')"
            class="flex items-center justify-between w-full px-3 py-2.5 text-sm font-medium {{ $group['active'] ? 'text-[#3F5189] dark:text-[#4A5A96] bg-slate-100 dark:bg-slate-700' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 group">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $group['icon'] }}"></path>
            </svg>
            <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>{{ __($group['name']) }}</span>
        </div>
        <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu !== '{{ $group['key'] }}'" x-cloak
             class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
        <svg x-show="(!sidebarCollapsed || sidebarOpen) && activeSubmenu === '{{ $group['key'] }}'" x-cloak
             class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="rail ? open : activeSubmenu === '{{ $group['key'] }}'" x-cloak x-ref="panel"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95"
         :class="rail
            ? 'fixed left-[70px] z-50 w-64 max-h-[80vh] overflow-y-auto overscroll-contain p-2 space-y-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xl'
            : 'ml-8 mt-2 space-y-1'"
         :style="{ top: rail ? top + 'px' : null }"
         @mouseenter="rail && show()" @mouseleave="hide()">
        <!-- The rail hides the group label, so the flyout carries it -->
        <div x-show="rail" x-cloak
             class="px-3 pb-2 mb-1 border-b border-slate-200 dark:border-slate-700 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            {{ __($group['name']) }}
        </div>
        @foreach($group['items'] as $item)
            <a href="{{ $item['url'] }}"
               class="flex items-center px-3 py-2 text-sm {{ $item['active'] ? 'text-[#3F5189] dark:text-[#4A5A96] font-medium' : 'text-slate-600 dark:text-slate-300' }} rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                </svg>
                <span class="flex-1">{{ __($item['name']) }}</span>
                @if(($item['badge'] ?? null))
                    <span class="ml-2 shrink-0 rounded-full bg-[#3F5189] px-2 py-0.5 text-xs font-semibold text-white">
                        {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
</div>
