{{-- Payment Schedule section — shared by project and job site financial reports.
     Expects: $schedule (PaymentScheduleService::build() payload), $currency, $locale --}}
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Payment Schedule') }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {{ __('Expense payments by due date. Overdue is derived from due dates as of today. Cancelled expenses excluded.') }}
        </p>
    </div>

    <!-- Expense schedule tiles -->
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Expense Commitments') }}</p>
            <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ Number::currency($schedule['expenses']['total'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $schedule['expenses']['paid_count'] + $schedule['expenses']['open_count'] }} {{ __('payments') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ Number::currency($schedule['expenses']['paid'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $schedule['expenses']['paid_count'] }} {{ __('payments') }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Upcoming') }}</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ Number::currency($schedule['expenses']['upcoming'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $schedule['expenses']['open_count'] - $schedule['expenses']['overdue_count'] }} {{ __('payments') }}</p>
        </div>
        <div class="rounded-lg border-2 {{ $schedule['expenses']['overdue'] > 0 ? 'border-red-200 dark:border-red-900/50' : 'border-slate-200 dark:border-slate-700' }} p-4">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overdue') }}</p>
            <p class="text-2xl font-bold {{ $schedule['expenses']['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }} mt-1">{{ Number::currency($schedule['expenses']['overdue'], $currency, $locale) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $schedule['expenses']['overdue_count'] }} {{ __('payments') }}</p>
        </div>
    </div>

    <!-- Contracts strip -->
    <div class="px-6 pb-6">
        <div class="rounded-lg bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 p-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Subcontractor Contracts') }} <span class="text-xs font-normal text-slate-500 dark:text-slate-400">({{ $schedule['contracts']['count'] }})</span></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('Contracts have no payment due dates (no schedule); balances are point-in-time and not included in the monthly projection.') }}
                    </p>
                </div>
                <div class="flex items-center gap-6 text-sm">
                    <div class="text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Adjusted Amount') }}</p>
                        <p class="font-semibold text-slate-900 dark:text-white">{{ Number::currency($schedule['contracts']['adjusted'], $currency, $locale) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Paid') }}</p>
                        <p class="font-semibold text-green-600 dark:text-green-400">{{ Number::currency($schedule['contracts']['paid'], $currency, $locale) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Balance Due') }}</p>
                        <p class="font-semibold {{ $schedule['contracts']['balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ Number::currency($schedule['contracts']['balance'], $currency, $locale) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Combined summary -->
    <div class="px-6 pb-6">
        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider"></th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Committed') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Outstanding') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    <tr>
                        <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ __('Expenses') }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['expenses']['total'], $currency, $locale) }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['expenses']['paid'], $currency, $locale) }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['expenses']['outstanding'], $currency, $locale) }}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ __('Contracts') }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['contracts']['total'], $currency, $locale) }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['contracts']['paid'], $currency, $locale) }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['contracts']['outstanding'], $currency, $locale) }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                    <tr class="font-semibold">
                        <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total') }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['committed'], $currency, $locale) }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['paid'], $currency, $locale) }}</td>
                        <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['combined']['outstanding'], $currency, $locale) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Monthly projection -->
    <div class="px-6 pb-6">
        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">{{ __('Upcoming Payments by Month') }}</h4>
        @if (empty($schedule['projection']['buckets']))
            <div class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400 rounded-lg border border-slate-200 dark:border-slate-700">
                {{ __('No open scheduled payments.') }}
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Month') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Payments Due') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount Due') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($schedule['projection']['buckets'] as $bucket)
                            <tr class="{{ $bucket['type'] === 'overdue' ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                <td class="px-6 py-3 text-sm {{ $bucket['type'] === 'overdue' ? 'font-semibold text-red-700 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ $bucket['type'] === 'overdue' ? __('Overdue (past due)') : $bucket['label'] }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right {{ $bucket['type'] === 'overdue' ? 'text-red-700 dark:text-red-400' : 'text-slate-600 dark:text-slate-400' }}">
                                    {{ $bucket['count'] }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right {{ $bucket['type'] === 'overdue' ? 'font-semibold text-red-700 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ Number::currency($bucket['amount'], $currency, $locale) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-100 dark:bg-slate-700/60">
                        <tr class="font-semibold">
                            <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ __('Total Open') }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ $schedule['projection']['total_count'] }}</td>
                            <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ Number::currency($schedule['projection']['total_open'], $currency, $locale) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
