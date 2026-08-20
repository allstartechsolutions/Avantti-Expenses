@props([
    'project',
    'active' => 'overview'
])

{{--
    The tabs come from config/permissions.php via App\Services\Navigation:
    a tab is here because the catalogue declares it, its module is switched on,
    and this person holds its ability on this project. There is no tab list in
    this file — see docs/permissions-module.md.
--}}
@php
    $tabs = app(\App\Services\Navigation::class)->projectTabs(auth()->user(), $project);
@endphp

@if(count($tabs))
<div class="mb-6">
    <div class="border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
            @foreach($tabs as $tab)
                <a
                    href="{{ route($tab['route'], $project) }}"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap {{ $active === $tab['key'] ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}"
                >
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}"></path>
                    </svg>
                    {{ __($tab['name']) }}
                </a>
            @endforeach
        </nav>
    </div>
</div>
@endif
