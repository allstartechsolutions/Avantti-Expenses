<div>
    @php
        $currency = config('app.currency');
        $locale = config('app.locale');
        $statusStyles = [
            'settled' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'open' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400',
            'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
        ];
        $statusLabels = [
            'settled' => __('Settled'),
            'open' => __('Open'),
            'overdue' => __('Overdue'),
        ];
        $sourceLabels = [
            'income' => __('Income'),
            'invoice' => __('Invoices'),
            'expense' => __('Expenses'),
            'contract' => __('Contracts'),
        ];
    @endphp

    {{-- Page header --}}
    <div class="mb-6 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Company Financials') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Everything received and paid, and everything still to receive and still to pay — income, invoices, expenses and contracts with their payment schedules.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @can('reports.export')
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </x-ui.button>
            @endcan
            {{-- PDF links build their URL at click time from the address bar, which
                 Livewire keeps in sync with the active filters. --}}
            <x-ui.button variant="secondary" href="{{ route('reports.company-financials.pdf.view') }}" icon="eye"
                x-data x-on:click.prevent="window.open($el.getAttribute('href') + window.location.search, '_blank')">
                {{ __('View PDF') }}
            </x-ui.button>
            <x-ui.button variant="primary" href="{{ route('reports.company-financials.pdf.download') }}" icon="arrow-down-tray"
                x-data x-on:click.prevent="window.location.href = $el.getAttribute('href') + window.location.search">
                {{ __('Download PDF') }}
            </x-ui.button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
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
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="button" wire:click="setAllTime" class="px-3 py-1 text-xs rounded-md {{ !$fromDate && !$toDate ? 'bg-[#3F5189] text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">{{ __('All time') }}</button>
            <button type="button" wire:click="setCurrentMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('This month') }}</button>
            <button type="button" wire:click="setNextMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Next month') }}</button>
            <button type="button" wire:click="setNext3Months" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Next 3 months') }}</button>
            <button type="button" wire:click="setThisYear" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('This year') }}</button>
        </div>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            {{ __('Settled money is matched by the date it moved; open money by the date it is due. Overdue is derived from due dates as of today.') }}
        </p>
    </div>

    {{-- Position --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Received') }}</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ Number::currency($data['in']['settled'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $data['in']['settled_count'] }} {{ __('entries') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Paid') }}</p>
            <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ Number::currency($data['out']['settled'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $data['out']['settled_count'] }} {{ __('entries') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('To Receive') }}</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ Number::currency($data['net']['to_receive'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Overdue') }}: {{ Number::currency($data['in']['overdue'], $currency, $locale) }}
            </p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('To Pay') }}</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ Number::currency($data['net']['to_pay'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Overdue') }}: <span class="{{ $data['out']['overdue'] > 0 ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">{{ Number::currency($data['out']['overdue'], $currency, $locale) }}</span>
            </p>
        </div>
    </div>

    {{-- Net position --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Net Cash (settled)') }}</p>
            <p class="text-3xl font-bold mt-1 {{ $data['net']['cash'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                {{ Number::currency($data['net']['cash'], $currency, $locale) }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Received minus paid in the selected period.') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Net Forecast (with open items)') }}</p>
            <p class="text-3xl font-bold mt-1 {{ $data['net']['forecast'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                {{ Number::currency($data['net']['forecast'], $currency, $locale) }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Everything received and to receive, minus everything paid and to pay.') }}</p>
        </div>
    </div>

    {{-- By source --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('By Source') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Source') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Direction') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Settled') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Open') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overdue') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($data['sources'] as $source)
                        <tr>
                            <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $source['label'] }}</td>
                            <td class="px-6 py-3 text-sm">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $source['direction'] === 'in' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">
                                    {{ $source['direction'] === 'in' ? __('In') : __('Out') }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($source['settled'], $currency, $locale) }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($source['open'], $currency, $locale) }}</td>
                            <td class="px-6 py-3 text-sm text-right {{ $source['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400' }}">{{ Number::currency($source['overdue'], $currency, $locale) }}</td>
                            <td class="px-6 py-3 text-sm text-right font-medium text-slate-900 dark:text-white">{{ Number::currency($source['total'], $currency, $locale) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing in the selected period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Timeline --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Month by Month') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Settled money on the date it moved, open money on the date it is due.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Month') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('In') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Out') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Net') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($data['timeline']['months'] as $month)
                        <tr>
                            <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $month['label'] }}</td>
                            <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($month['in'], $currency, $locale) }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($month['out'], $currency, $locale) }}</td>
                            <td class="px-6 py-3 text-sm text-right font-medium {{ $month['net'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">{{ Number::currency($month['net'], $currency, $locale) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing in the selected period.') }}</td></tr>
                    @endforelse
                    @if ($data['timeline']['undated']['in'] > 0 || $data['timeline']['undated']['out'] > 0)
                        <tr class="bg-slate-50 dark:bg-slate-900/40">
                            <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-300">{{ __('No due date') }}</td>
                            <td class="px-6 py-3 text-sm text-right text-green-600 dark:text-green-400">{{ Number::currency($data['timeline']['undated']['in'], $currency, $locale) }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($data['timeline']['undated']['out'], $currency, $locale) }}</td>
                            <td class="px-6 py-3 text-sm text-right font-medium {{ $data['timeline']['undated']['net'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">{{ Number::currency($data['timeline']['undated']['net'], $currency, $locale) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- Detail --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Detail') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Every entry behind the numbers above. Narrowing this list does not change the totals.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="directionFilter" class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('In and out') }}</option>
                    <option value="in">{{ __('In') }}</option>
                    <option value="out">{{ __('Out') }}</option>
                </select>
                <select wire:model.live="statusFilter" class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="settled">{{ __('Settled') }}</option>
                    <option value="open">{{ __('Open') }}</option>
                    <option value="overdue">{{ __('Overdue') }}</option>
                </select>
                <select wire:model.live="sourceFilter" class="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All sources') }}</option>
                    @foreach ($sourceLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Source') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Description') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Party') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Project') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Job Site') }}</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-6 py-3 text-sm whitespace-nowrap text-slate-900 dark:text-white">{{ $row['date']?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $sourceLabels[$row['source']] ?? $row['source'] }}</td>
                            <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $row['description'] }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['party'] ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['project'] ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $row['job_site'] ?? '—' }}</td>
                            <td class="px-6 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusStyles[$row['status']] ?? '' }}">
                                    {{ $statusLabels[$row['status']] ?? $row['status'] }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-sm text-right font-medium whitespace-nowrap {{ $row['direction'] === 'in' ? 'text-green-600 dark:text-green-400' : 'text-slate-900 dark:text-white' }}">
                                {{ $row['direction'] === 'in' ? '+' : '−' }}{{ Number::currency($row['amount'], $currency, $locale) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing matches the selected filters.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                    <tr class="font-semibold">
                        <td colspan="7" class="px-6 py-3 text-sm text-slate-900 dark:text-white">
                            {{ __('Listed') }} ({{ $rows->count() }})
                        </td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                            {{ Number::currency($rows->where('direction', 'in')->sum('amount') - $rows->where('direction', 'out')->sum('amount'), $currency, $locale) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
