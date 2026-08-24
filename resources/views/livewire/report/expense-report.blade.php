@php
    $currency = config('app.currency');
    $locale = config('app.locale');

    $categories = [
        'product' => __('Product'),
        'service' => __('Service'),
        'rental' => __('Rental'),
    ];

    $tabs = [
        'project' => __('By Project / Job Site'),
        'vendor' => __('By Vendor'),
        'costcode' => __('By Cost Code'),
        'detail' => __('Detail'),
    ];
@endphp

<div>
    {{-- Page header --}}
    <div class="mb-6 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Expense Report') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Expenses rolled up by project, job site, vendor, and cost code — with paid, outstanding, and overdue totals.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </x-ui.button>
            {{-- PDF links build their URL at click time from the address bar, which
                 Livewire keeps in sync with the active filters. --}}
            <x-ui.button variant="secondary" href="{{ route('reports.expenses.pdf.view') }}" icon="eye"
                x-data x-on:click.prevent="window.open($el.getAttribute('href') + window.location.search, '_blank')">
                {{ __('View PDF') }}
            </x-ui.button>
            <x-ui.button variant="primary" href="{{ route('reports.expenses.pdf.download') }}" icon="arrow-down-tray"
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
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Category') }}</label>
                <select wire:model.live="categoryFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Status') }}</label>
                <select wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="all">{{ __('All') }}</option>
                    <option value="unpaid">{{ __('Unpaid (has balance)') }}</option>
                    <option value="pending">{{ __('Pending (not overdue)') }}</option>
                    <option value="overdue">{{ __('Overdue') }}</option>
                    <option value="paid">{{ __('Fully paid') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Date Basis') }}</label>
                <select wire:model.live="dateBasis"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="expense">{{ __('Expense date (incurred)') }}</option>
                    <option value="due">{{ __('Payment due date') }}</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" wire:click="setCurrentMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Current month') }}</button>
            <button type="button" wire:click="setCurrentQuarter" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Current quarter') }}</button>
            <button type="button" wire:click="setYearToDate" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Year to date') }}</button>
            <button type="button" wire:click="setLastYear" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Last year') }}</button>
        </div>
    </div>

    {{-- Due-date basis caveat --}}
    @if ($dateBasis === 'due')
        <div class="mb-6 px-6 py-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-900/30 rounded-lg">
            <p class="text-xs text-amber-800 dark:text-amber-400">
                {{ __('Showing expenses with a payment due in this period. Amounts are full expense totals, not the portion due in the period.') }}
            </p>
        </div>
    @endif

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Total Expenses') }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count expense|:count expenses', $kpis['count'], ['count' => $kpis['count']]) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Paid') }}</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ Number::currency($kpis['paid'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Cleared to date') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Outstanding') }}</p>
            <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ Number::currency($kpis['outstanding'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Remaining balance') }}</p>
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

        {{-- By Project / Job Site --}}
        @if ($view === 'project')
            @if ($byProject->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No expenses match the selected filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Project / Job Site') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Outstanding') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overdue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($byProject as $proj)
                                <tr class="bg-slate-50/60 dark:bg-slate-700/30 font-semibold">
                                    <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">
                                        @if ($proj['project_id'])
                                            <a href="{{ route('projects.overview', $proj['project_id']) }}" target="_blank" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $proj['project'] }}</a>
                                        @else
                                            {{ $proj['project'] ?? __('—') }}
                                        @endif
                                        <span class="ml-1 text-xs font-normal text-slate-400">({{ $proj['count'] }})</span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($proj['total'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($proj['paid'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($proj['outstanding'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right {{ $proj['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-600' }}">{{ Number::currency($proj['overdue'], $currency, $locale) }}</td>
                                </tr>
                                @foreach ($proj['jobsites'] as $js)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                        <td class="px-6 py-2 pl-10 text-sm">
                                            @if ($js['job_site_id'])
                                                <a href="{{ route('jobsites.overview', $js['job_site_id']) }}" target="_blank" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $js['job_site'] }}</a>
                                            @else
                                                <span class="text-slate-500 dark:text-slate-400 italic">{{ __('Project-level') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-2 text-sm text-right text-slate-700 dark:text-slate-200">{{ Number::currency($js['total'], $currency, $locale) }}</td>
                                        <td class="px-6 py-2 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($js['paid'], $currency, $locale) }}</td>
                                        <td class="px-6 py-2 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($js['outstanding'], $currency, $locale) }}</td>
                                        <td class="px-6 py-2 text-sm text-right {{ $js['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-600' }}">{{ Number::currency($js['overdue'], $currency, $locale) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                            <tr class="font-semibold">
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($kpis['paid'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($kpis['outstanding'], $currency, $locale) }}</td>
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
                <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No expenses match the selected filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Vendor') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Expenses') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Outstanding') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overdue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($byVendor as $v)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                    <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $v['vendor'] ?? __('No vendor') }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-600 dark:text-slate-400">{{ $v['count'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($v['total'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($v['paid'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($v['outstanding'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right {{ $v['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-600' }}">{{ Number::currency($v['overdue'], $currency, $locale) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                            <tr class="font-semibold">
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-600 dark:text-slate-400">{{ $kpis['count'] }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($kpis['paid'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($kpis['outstanding'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ Number::currency($kpis['overdue'], $currency, $locale) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif

        {{-- By Cost Code --}}
        @if ($view === 'costcode')
            <div class="px-6 pt-4 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Expense line items plus subcontractor contracts per cost code. Contracted is the full allocated value; Contract Paid counts payments dated inside the range. Contracts are hidden when a vendor, category or status filter is applied.') }}
            </div>
            @if ($byCostCode->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No expenses match the selected filters.') }}</div>
            @else
                <div class="overflow-x-auto mt-2">
                    <p class="px-6 pt-4 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Spend in the selected period. Click a code to see it against its budget, where the figures are lifetime.') }}
                </p>
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost Code') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Line Items') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Expenses') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contracted') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contract Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total Committed') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($byCostCode as $cc)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                    <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">
                                        @if($cc['budget_id'] && $cc['budget_item_id'])
                                            <a href="{{ route('budgets.cost-code', [$cc['budget_id'], $cc['budget_item_id']]) }}"
                                               class="text-[#3F5189] dark:text-[#4A5A96] hover:underline"
                                               title="{{ __('See this code against its budget') }}">
                                                {{ $cc['code'] }}
                                            </a>
                                        @else
                                            {{ $cc['code'] }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-600 dark:text-slate-400">{{ $cc['count'] }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($cc['expenses'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($cc['contracted'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right {{ $cc['contract_paid'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' }}">{{ Number::currency($cc['contract_paid'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 text-sm text-right font-medium text-slate-900 dark:text-white">{{ Number::currency($cc['total'], $currency, $locale) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                            <tr class="font-semibold">
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-600 dark:text-slate-400">{{ $byCostCode->sum('count') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($byCostCode->sum('expenses'), $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($byCostCode->sum('contracted'), $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($byCostCode->sum('contract_paid'), $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($byCostCode->sum('total'), $currency, $locale) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif

        {{-- Detail --}}
        @if ($view === 'detail')
            @if ($detail->isEmpty())
                <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No expenses match the selected filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $dateBasis === 'due' ? __('Due Date') : __('Date') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Item') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Vendor') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Project') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Job Site') }}</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Installments') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Outstanding') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overdue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach ($detail as $row)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-900 dark:text-white">{{ ($dateBasis === 'due' ? $row['due_date'] : $row['expense_date'])?->format('M d, Y') }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['item'] }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $row['vendor'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['project'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['job_site'] ?? __('Project-level') }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-center text-slate-600 dark:text-slate-400">{{ $row['payment_label'] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($row['total'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($row['paid'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($row['outstanding'], $currency, $locale) }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right {{ $row['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400 dark:text-slate-600' }}">{{ Number::currency($row['overdue'], $currency, $locale) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                            <tr class="font-semibold">
                                <td colspan="6" class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($kpis['total'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($kpis['paid'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-amber-600 dark:text-amber-400">{{ Number::currency($kpis['outstanding'], $currency, $locale) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">{{ Number::currency($kpis['overdue'], $currency, $locale) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif
    </div>
</div>
