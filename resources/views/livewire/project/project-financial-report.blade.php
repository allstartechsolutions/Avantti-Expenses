<x-project-layout :project="$project" active="report" :title="__('Financial Report')">
    <x-slot:actions>
        <x-ui.button
            variant="secondary"
            href="{{ route('projects.report.pdf.view', $project) }}"
            icon="eye"
            target="_blank">
            {{ __('View PDF') }}
        </x-ui.button>
        <x-ui.button
            variant="primary"
            href="{{ route('projects.report.pdf.download', $project) }}"
            icon="arrow-down-tray">
            {{ __('Download PDF') }}
        </x-ui.button>
    </x-slot:actions>

    @php
        $currency = config('app.currency');
        $locale = config('app.locale');
        $isProfit = $financials['profit'] >= 0;
        $profitCardBg = $isProfit ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-orange-500 to-orange-600';
    @endphp

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
        <!-- Contract Value Card -->
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Contract Value') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency($financials['contract_value'], $currency, $locale) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-sm text-white/80">
                {{ __('Base') }}: {{ Number::currency($financials['base_contract_value'], $currency, $locale) }}
                @if ($financials['change_orders_total'] != 0)
                    · {{ $financials['change_orders_total'] >= 0 ? '+' : '' }}{{ Number::currency($financials['change_orders_total'], $currency, $locale) }} {{ __('CO') }}
                @endif
            </p>
        </div>

        <!-- Total Expenses Card -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Total Expenses') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency($financials['total_expenses'], $currency, $locale) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-sm text-white/80">
                {{ trans_choice(':count expense|:count expenses', $financials['expenses_count'], ['count' => $financials['expenses_count']]) }} {{ __('recorded') }}
            </p>
        </div>

        <!-- Contracts Card -->
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Contracts') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency($financials['contracts_adjusted'], $currency, $locale) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-sm text-white/90">
                <span>{{ __('Paid') }}: <span class="font-semibold">{{ Number::currency($financials['contracts_paid'], $currency, $locale) }}</span></span>
                <span>{{ __('Unpaid') }}: <span class="font-semibold">{{ Number::currency($financials['contracts_unpaid'], $currency, $locale) }}</span></span>
            </div>
        </div>

        <!-- Profit / Loss Card -->
        <div class="{{ $profitCardBg }} rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ $isProfit ? __('Profit') : __('Loss') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency(abs($financials['profit']), $currency, $locale) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    @if ($isProfit)
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    @else
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                        </svg>
                    @endif
                </div>
            </div>
            <p class="mt-4 text-sm text-white/80">{{ __('Contract Value − Expenses − Contracts') }}</p>
        </div>
    </div>

    <!-- Revenue detail (Contract Value breakdown) -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contract Value Breakdown') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('How the contract value is calculated.') }}</p>
        </div>
        <div class="px-6 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-2">{{ __('Base Value') }}</p>
            @if (count($revenueDetail['base_lines']) === 0)
                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('No base value set.') }}</p>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($revenueDetail['base_lines'] as $line)
                        <div class="flex justify-between py-1.5 text-sm">
                            <span class="text-slate-700 dark:text-slate-300">{{ $line['label'] }}</span>
                            <span class="font-medium text-slate-900 dark:text-white">{{ Number::currency($line['amount'], $currency, $locale) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between py-2 mt-1 border-t border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    <span class="text-slate-700 dark:text-slate-300">{{ __('Subtotal') }}</span>
                    <span class="text-slate-900 dark:text-white">{{ Number::currency($financials['base_contract_value'], $currency, $locale) }}</span>
                </div>
            @endif

            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mt-5 mb-2">{{ __('Change Orders') }}</p>
            @if (count($revenueDetail['change_orders']) === 0)
                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('No change orders.') }}</p>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    @foreach ($revenueDetail['change_orders'] as $co)
                        <div class="flex justify-between gap-4 py-1.5 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="text-slate-900 dark:text-white truncate">{{ $co['title'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $co['date']?->appDate() }} · {{ $co['scope'] }}
                                    @if(($co['status'] ?? 'approved') !== 'approved')
                                        · <span class="text-amber-600 dark:text-amber-400">{{ __('not approved') }}</span>
                                    @endif
                                    @if(($co['cost'] ?? 0.0) != 0.0)
                                        · {{ __('cost') }} {{ Number::currency($co['cost'], $currency, $locale) }}
                                        · {{ __('margin') }} {{ Number::currency($co['amount'] - $co['cost'], $currency, $locale) }}
                                    @else
                                        · <span class="text-slate-400 dark:text-slate-500">{{ __('no cost breakdown') }}</span>
                                    @endif
                                </p>
                            </div>
                            <span class="font-medium whitespace-nowrap {{ $co['amount'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ $co['amount'] >= 0 ? '+' : '' }}{{ Number::currency($co['amount'], $currency, $locale) }}
                            </span>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between py-2 mt-1 border-t border-slate-200 dark:border-slate-700 text-sm font-semibold">
                    <span class="text-slate-700 dark:text-slate-300">{{ __('Subtotal') }}</span>
                    <span class="{{ $financials['change_orders_total'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                        {{ $financials['change_orders_total'] >= 0 ? '+' : '' }}{{ Number::currency($financials['change_orders_total'], $currency, $locale) }}
                    </span>
                </div>
            @endif

            <div class="flex justify-between py-3 mt-4 border-t-2 border-slate-300 dark:border-slate-600 text-base font-bold">
                <span class="text-slate-900 dark:text-white">{{ __('Net Contract Value') }}</span>
                <span class="text-slate-900 dark:text-white">{{ Number::currency($financials['contract_value'], $currency, $locale) }}</span>
            </div>
        </div>
    </div>

    <!-- Per-jobsite breakdown -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Per Job Site Breakdown') }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Project-level row contains resources not tied to a specific job site.') }}
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Job Site') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contract Value') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Expenses') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contracts') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }} / {{ __('Unpaid') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Profit / Loss') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($breakdown as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">
                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $row['name'] }}</a>
                                @else
                                    <span class="italic text-slate-600 dark:text-slate-400">{{ $row['name'] }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($row['contract_value'], $currency, $locale) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($row['expenses'], $currency, $locale) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($row['contracts_adjusted'], $currency, $locale) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-right text-slate-600 dark:text-slate-400">
                                {{ Number::currency($row['contracts_paid'], $currency, $locale) }}
                                <span class="text-slate-400">/</span>
                                {{ Number::currency($row['contracts_unpaid'], $currency, $locale) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold {{ $row['profit'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ $row['profit'] < 0 ? '−' : '' }}{{ Number::currency(abs($row['profit']), $currency, $locale) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                    <tr class="font-semibold">
                        <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Project Total') }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                            {{ Number::currency($financials['contract_value'], $currency, $locale) }}
                        </td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                            {{ Number::currency($financials['total_expenses'], $currency, $locale) }}
                        </td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                            {{ Number::currency($financials['contracts_adjusted'], $currency, $locale) }}
                        </td>
                        <td class="px-6 py-3 text-xs text-right text-slate-600 dark:text-slate-400">
                            {{ Number::currency($financials['contracts_paid'], $currency, $locale) }}
                            <span class="text-slate-400">/</span>
                            {{ Number::currency($financials['contracts_unpaid'], $currency, $locale) }}
                        </td>
                        <td class="px-6 py-3 text-sm text-right {{ $financials['profit'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            {{ $financials['profit'] < 0 ? '−' : '' }}{{ Number::currency(abs($financials['profit']), $currency, $locale) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if ($project->amount_source?->value === 'manual')
            <div class="px-6 py-3 bg-amber-50 dark:bg-amber-900/10 border-t border-amber-200 dark:border-amber-900/30">
                <p class="text-xs text-amber-800 dark:text-amber-400">
                    {{ __('This project uses a manual contract value. Job-site amounts are shown for reference; the Project Total uses the project amount, not the sum of job-site amounts.') }}
                </p>
            </div>
        @endif
    </div>

    <!-- Payment Schedule -->
    @include('livewire.shared.cost-code-section', ['costCodes' => $costCodes, 'showLocation' => true])

    @include('livewire.shared.payment-schedule-section', ['schedule' => $paymentSchedule])

    <!-- Expenses detail -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Expenses') }}</h3>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count expense|:count expenses', $expensesDetail->count(), ['count' => $expensesDetail->count()]) }}</span>
        </div>
        @if ($expensesDetail->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('No expenses recorded for this project.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Item') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Supplier') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Scope') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($expensesDetail as $expense)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    {{ $expense->expense_date?->appDate() }}
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $expense->item_name }}</td>
                                <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ $expense->supplier?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">
                                    {{ $expense->jobSite?->job_site_name ?? __('Project-level') }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-xs">
                                    @php
                                        $statusClass = match ($expense->status) {
                                            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                            'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                            'cancelled' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                                            default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-full font-medium {{ $statusClass }}">{{ $expense->getStatusLabel() }}</span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-white">
                                    {{ Number::currency($expense->total_amount, $currency, $locale) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                        <tr class="font-semibold">
                            <td colspan="5" class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($financials['total_expenses'], $currency, $locale) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    <!-- Contracts detail -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contracts') }}</h3>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count contract|:count contracts', $contractsDetail->count(), ['count' => $contractsDetail->count()]) }}</span>
        </div>
        @if ($contractsDetail->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('No contracts for this project.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contract #') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Subcontractor') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Scope') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($contractsDetail as $contract)
                            @php
                                $adjusted = $contract->getAdjustedAmount();
                                $paid = $contract->getAmountPaid();
                                $balance = round($adjusted - $paid, 2);
                                $coTotal = $contract->getChangeOrdersTotal();
                                $contractStatusClass = match ($contract->status) {
                                    'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
                                    'partially_paid' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                    'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                    default => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="px-6 py-3 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('contracts.show', $contract->id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                        #{{ $contract->contract_number }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $contract->subcontractor?->company_name ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">
                                    {{ $contract->jobSite?->job_site_name ?? __('Project-level') }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-xs">
                                    <span class="inline-flex px-2 py-0.5 rounded-full font-medium {{ $contractStatusClass }}">
                                        {{ $contract->getStatusLabel() }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-900 dark:text-white">
                                    {{ Number::currency($adjusted, $currency, $locale) }}
                                    @if ($coTotal != 0)
                                        <div class="text-xs {{ $coTotal < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400' }}">
                                            {{ __('incl.') }} {{ $coTotal >= 0 ? '+' : '' }}{{ Number::currency($coTotal, $currency, $locale) }} {{ __('CO') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-900 dark:text-white">
                                    {{ Number::currency($paid, $currency, $locale) }}
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium {{ $balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ Number::currency($balance, $currency, $locale) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                        <tr class="font-semibold">
                            <td colspan="4" class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($financials['contracts_adjusted'], $currency, $locale) }}
                            </td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($financials['contracts_paid'], $currency, $locale) }}
                            </td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">
                                {{ Number::currency($financials['contracts_unpaid'], $currency, $locale) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

</x-project-layout>
