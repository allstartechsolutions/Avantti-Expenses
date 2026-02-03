@props([
    'jobSite',
    'active' => 'overview'
])

@php
    $menuItems = [
        'overview' => [
            'label' => 'Overview',
            'route' => 'jobsites.overview',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
        ],
        'expenses' => [
            'label' => 'Expenses',
            'route' => 'jobsites.expenses',
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
        ],
        'change-orders' => [
            'label' => 'Change Orders',
            'route' => 'jobsites.change-orders',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
        ],
        'purchase-orders' => [
            'label' => 'Purchase Orders',
            'route' => 'jobsites.purchase-orders',
            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
        ],
        'daily-reports' => [
            'label' => 'Daily Reports',
            'route' => 'jobsites.daily-reports',
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'
        ],
        'budget' => [
            'label' => 'Budget',
            'route' => 'jobsites.budget',
            'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'
        ],
    ];
@endphp

<div class="mb-6">
    <div class="border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
            @foreach($menuItems as $key => $item)
                <a
                    href="{{ route($item['route'], $jobSite) }}"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap {{ $active === $key ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}"
                >
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"></path>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</div>
