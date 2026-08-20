@props(['entry'])

{{-- A top-level sidebar entry. Markup unchanged from the hand-written menu;
     only the label, icon, url and active state now come from the catalogue. --}}
<div x-data="railFlyout"
     @mouseenter="rail && show()" @mouseleave="hide()"
     @focusin="rail && show()" @focusout="hide()"
     @rail-reposition.window="open && place()"
     x-effect="rail || hide()">
    <a href="{{ $entry['url'] }}" class="flex items-center px-2.5 py-2.5 mb-1 text-sm font-medium {{ $entry['active'] ? 'text-white bg-gradient-to-r from-[#3F5189] to-[#4A5A96]' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }} rounded-lg group">
        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $entry['icon'] }}"></path>
        </svg>
        <span x-show="!sidebarCollapsed || sidebarOpen" x-cloak>{{ __($entry['name']) }}</span>
    </a>
    <!-- Rail tooltip: the label has nowhere else to go -->
    <div x-show="rail && open" x-cloak x-ref="panel"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         :style="{ top: top + 'px' }"
         class="fixed left-[70px] z-50 px-3 py-2 whitespace-nowrap rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-xl text-sm font-medium text-slate-700 dark:text-slate-200">
        {{ __($entry['name']) }}
    </div>
</div>
