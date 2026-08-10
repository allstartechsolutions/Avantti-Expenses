@php
    $currency = config('app.currency');
    $locale = config('app.locale');

    $tabs = [
        'detail' => __('Detail'),
        'project' => __('By Project / Job Site'),
        'vendor' => __('By Vendor'),
    ];

    $statusColors = [
        'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
    ];
@endphp

<div>
    {{-- Page header --}}
    <div class="mb-6 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Payment Details') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every payment individually — expense installments by due date, contract payments, and open contract balances.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </x-ui.button>
            {{-- PDF links build their URL at click time from the address bar, which
                 Livewire keeps in sync with the active filters. --}}
            <x-ui.button variant="secondary" href="{{ route('reports.payment-details.pdf.view') }}" icon="eye"
                x-data x-on:click.prevent="window.open($el.getAttribute('href') + window.location.search, '_blank')">
                {{ __('View PDF') }}
            </x-ui.button>
            <x-ui.button variant="primary" href="{{ route('reports.payment-details.pdf.download') }}" icon="arrow-down-tray"
                x-data x-on:click.prevent="window.location.href = $el.getAttribute('href') + window.location.search">
                {{ __('Download PDF') }}
            </x-ui.button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('From') }}</label>
                <input type="date" wire:model.live="fromDate"
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('To') }}</label>
                <input type="date" wire:model.live="toDate"
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Client') }}</label>
                <select wire:model.live="clientFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All clients') }}</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Project') }}</label>
                <select wire:model.live="projectFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All projects') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Job Site') }}</label>
                <select wire:model.live="jobSiteFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All job sites') }}</option>
                    @foreach ($jobSites as $j)
                        <option value="{{ $j->id }}">{{ $j->job_site_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Vendor') }}</label>
                <select wire:model.live="vendorFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All vendors') }}</option>
                    @foreach ($vendors as $v)
                        <option value="{{ $v->id }}">{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Subcontractor') }}</label>
                <select wire:model.live="subcontractorFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All subcontractors') }}</option>
                    @foreach ($subcontractors as $s)
                        <option value="{{ $s->id }}">{{ $s->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Type') }}</label>
                <select wire:model.live="typeFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="all">{{ __('Expenses + Contracts') }}</option>
                    <option value="expenses">{{ __('Expenses only') }}</option>
                    <option value="contracts">{{ __('Contracts only') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('Status') }}
                    <span class="text-xs font-normal text-slate-400 dark:text-slate-500">({{ __('none selected = all') }})</span>
                </label>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-1 py-2">
                    @foreach(['pending' => __('Pending'), 'overdue' => __('Overdue'), 'paid' => __('Paid')] as $statusValue => $statusLabel)
                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                            <input type="checkbox"
                                   wire:model.live="statusFilter"
                                   value="{{ $statusValue }}"
                                   class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] dark:bg-slate-700">
                            {{ $statusLabel }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" wire:click="setCurrentMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Current month') }}</button>
            <button type="button" wire:click="setNextMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Next month') }}</button>
            <button type="button" wire:click="setNextThreeMonths" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Next 3 months') }}</button>
            <button type="button" wire:click="setThisYear" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('This year') }}</button>
        </div>
    </div>

    {{-- Contract balance caveat --}}
    <div class="mb-6 px-6 py-3 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-900/30 rounded-lg">
        <p class="text-xs text-blue-800 dark:text-blue-400">
            {{ __('Contracts have no payment schedule: open contract balances are placed on the contract end date (balances without an end date always appear, undated). Contract payments already made are shown on their payment date.') }}
        </p>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Total in Period') }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $kpis['count'] }} {{ Str::plural('payment', $kpis['count']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Paid') }}</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ Number::currency($kpis['paid'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Paid within the period') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Pending') }}</p>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ Number::currency($kpis['pending'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Due in the period, not yet overdue') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border-2 border-red-200 dark:border-red-900/50 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Overdue (today)') }}</p>
            <p class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">{{ Number::currency($kpis['overdue'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Past due, derived from due dates') }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="border-b border-slate-200 dark:border-slate-700 px-4">
            <nav class="-mb-px flex flex-wrap gap-4">
                @foreach ($tabs as $key => $label)
                    <button type="button" wire:click="$set('view', '{{ $key }}')"
                            class="py-3 px-1 border-b-2 text-sm font-medium whitespace-nowrap transition
                                   {{ $view === $key
                                        ? 'border-[#3F5189] text-[#3F5189] dark:text-[#4A5A96] dark:border-[#4A5A96]'
                                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Detail --}}
        @if ($view === 'detail')
            @if ($rows->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No payments match the selected filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Vendor') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Item') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Project') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Job Site') }}</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Installment') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($rows as $r)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-900 dark:text-white">{{ $r['date']?->format('M d, Y') ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">
                                        {{ $r['vendor'] ?? '—' }}
                                        @if ($r['type'] === 'contract')
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300">{{ __('Contract') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $r['item'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $r['project'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $r['job_site'] ?? __('Project-level') }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-center text-slate-600 dark:text-slate-400">{{ $r['installment_label'] ?? '—' }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColors[$r['status']] ?? $statusColors['pending'] }}">
                                            {{ __(ucfirst($r['status'])) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                                        @if ($r['paid_date'])
                                            {{ $r['paid_date']->format('M d, Y') }}
                                            @if ($r['paid_by'])
                                                <span class="block text-xs text-slate-400 dark:text-slate-500">{{ __('by') }} {{ $r['paid_by'] }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-white">{{ Number::currency($r['amount'], $currency, $locale) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                            <tr class="font-semibold">
                                <td colspan="8" class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif

        {{-- By Project / Job Site --}}
        @if ($view === 'project')
            @if ($byProject->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No payments match the selected filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Project / Job Site') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Payments') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Pending') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overdue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($byProject as $proj)
                                <tr class="bg-slate-50/70 dark:bg-slate-700/40 font-medium">
                                    <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $proj['project'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-600 dark:text-slate-400">{{ $proj['count'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($proj['total'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($proj['paid'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($proj['pending'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ Number::currency($proj['overdue'], $currency, $locale) }}</td>
                                </tr>
                                @foreach ($proj['jobsites'] as $js)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                        <td class="px-6 py-2 pl-10 text-sm text-slate-600 dark:text-slate-400">{{ $js['job_site'] ?? __('Project-level') }}</td>
                                        <td class="px-6 py-2 text-sm text-right text-slate-600 dark:text-slate-400">{{ $js['count'] }}</td>
                                        <td class="px-6 py-2 text-sm text-right text-slate-600 dark:text-slate-400">{{ Number::currency($js['total'], $currency, $locale) }}</td>
                                        <td class="px-6 py-2 text-sm text-right text-slate-600 dark:text-slate-400">{{ Number::currency($js['paid'], $currency, $locale) }}</td>
                                        <td class="px-6 py-2 text-sm text-right text-slate-600 dark:text-slate-400">{{ Number::currency($js['pending'], $currency, $locale) }}</td>
                                        <td class="px-6 py-2 text-sm text-right text-slate-600 dark:text-slate-400">{{ Number::currency($js['overdue'], $currency, $locale) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                            <tr class="font-semibold">
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ $kpis['count'] }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($kpis['paid'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($kpis['pending'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ Number::currency($kpis['overdue'], $currency, $locale) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif

        {{-- By Vendor --}}
        @if ($view === 'vendor')
            @if ($byVendor->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No payments match the selected filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Vendor') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Payments') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Pending') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overdue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($byVendor as $v)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $v['vendor'] ?? __('No vendor') }}
                                        @if ($v['type'] === 'contract')
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300">{{ __('Contract') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-600 dark:text-slate-400">{{ $v['count'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($v['total'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($v['paid'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($v['pending'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ Number::currency($v['overdue'], $currency, $locale) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                            <tr class="font-semibold">
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ $kpis['count'] }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($kpis['paid'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($kpis['pending'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ Number::currency($kpis['overdue'], $currency, $locale) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
