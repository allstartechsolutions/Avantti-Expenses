@php
    $currency = config('app.currency');
    $locale = config('app.locale');
@endphp

<div>
    {{-- Page header --}}
    <div class="mb-6 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Accounts Payable') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Expenses due across all projects, by date.') }}</p>
        </div>
        @php
            $exportParams = http_build_query([
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'projectFilter' => $projectFilter,
                'statusFilter' => $statusFilter,
            ]);
        @endphp
        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </x-ui.button>
            <x-ui.button variant="secondary" href="{{ route('reports.accounts-payable.pdf.view') }}?{{ $exportParams }}" icon="eye" target="_blank">
                {{ __('View PDF') }}
            </x-ui.button>
            <x-ui.button variant="primary" href="{{ route('reports.accounts-payable.pdf.download') }}?{{ $exportParams }}" icon="arrow-down-tray">
                {{ __('Download PDF') }}
            </x-ui.button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Status') }}</label>
                <select wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="unpaid">{{ __('Unpaid (pending + overdue)') }}</option>
                    <option value="pending">{{ __('Pending only') }}</option>
                    <option value="overdue">{{ __('Overdue only') }}</option>
                    <option value="paid">{{ __('Paid only') }}</option>
                    <option value="all">{{ __('All') }}</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" wire:click="setCurrentMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Current month') }}</button>
            <button type="button" wire:click="setNextMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Next month') }}</button>
            <button type="button" wire:click="setCurrentQuarter" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Current quarter') }}</button>
            <button type="button" wire:click="setYearToDate" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Year to date') }}</button>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Due in Period') }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number::currency($kpis['total_due'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $kpis['count_due'] }} {{ Str::plural('payment', $kpis['count_due']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Overdue (today)') }}</p>
            <p class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">{{ Number::currency($kpis['overdue_total'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Past due, regardless of filter') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Paid in Period') }}</p>
            <p class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">{{ Number::currency($kpis['total_paid'], $currency, $locale) }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Cleared in selected dates') }}</p>
        </div>
    </div>

    {{-- Selected period detail --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Selected Period') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ \Carbon\Carbon::parse($fromDate)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('M d, Y') }}
            </p>
        </div>
        @if ($rows->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('No expense payments due in the selected period.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Due Date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Vendor') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Item') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Project') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Job Site') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($rows as $row)
                            @php
                                $statusClass = match ($row['status']) {
                                    'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                    'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                    default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    {{ $row['due_date']?->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $row['vendor'] ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['item'] }}</td>
                                <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['project'] ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['job_site'] ?? __('Project-level') }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-xs">
                                    <span class="inline-flex px-2 py-0.5 rounded-full font-medium {{ $statusClass }}">{{ ucfirst($row['status']) }}</span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-white">
                                    {{ Number::currency($row['amount'], $currency, $locale) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                        <tr class="font-semibold">
                            <td colspan="6" class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($rows->sum('amount'), $currency, $locale) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    {{-- Monthly projections --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Monthly Projections') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Pending + overdue payments expected over the next 12 months, starting after the selected period.') }}
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Month') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Payments') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($projections as $month)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">{{ $month['month'] }}</td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-600 dark:text-slate-400">
                                @if ($month['count'] > 0)
                                    {{ $month['count'] }}
                                @else
                                    <span class="text-slate-400 dark:text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium {{ $month['amount'] > 0 ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-600' }}">
                                @if ($month['amount'] > 0)
                                    {{ Number::currency($month['amount'], $currency, $locale) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                    <tr class="font-semibold">
                        <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Next 12 Months') }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                            {{ collect($projections)->sum('count') }}
                        </td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                            {{ Number::currency(collect($projections)->sum('amount'), $currency, $locale) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
