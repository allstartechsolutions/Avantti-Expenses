<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Sales Tax Report') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    @if($basis === 'cash')
                        {{ __('Cash basis — based on payment date (prorated by invoice share)') }}
                    @else
                        {{ __('Accrual basis — based on invoice date') }}
                    @endif
                </p>
            </div>
            <div>
                @can('reports.export')
                <x-ui.button variant="secondary" wire:click="exportCsv" icon="download">
                    {{ __('Export CSV') }}
                </x-ui.button>
                @endcan
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="basis" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Basis') }}</label>
                    <select id="basis" wire:model.live="basis"
                            class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                        <option value="accrual">{{ __('Accrual (invoice date)') }}</option>
                        <option value="cash">{{ __('Cash (payment date)') }}</option>
                    </select>
                </div>
                <div>
                    <label for="fromDate" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('From') }}</label>
                    <input type="date" id="fromDate" wire:model.live="fromDate"
                           class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                </div>
                <div>
                    <label for="toDate" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('To') }}</label>
                    <input type="date" id="toDate" wire:model.live="toDate"
                           class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                </div>
                @if($basis === 'accrual')
                    <div>
                        <label for="statusFilter" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Invoice Status') }}</label>
                        <select id="statusFilter" wire:model.live="statusFilter"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                            <option value="non_draft">{{ __('All except Draft') }}</option>
                            <option value="all">{{ __('All (including Draft)') }}</option>
                            <option value="sent">{{ __('Sent') }}</option>
                            <option value="pending">{{ __('Pending') }}</option>
                            <option value="partial">{{ __('Partial') }}</option>
                            <option value="paid">{{ __('Paid') }}</option>
                        </select>
                    </div>
                @else
                    <div></div>
                @endif
                <div class="flex items-end">
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button variant="ghost" size="sm" wire:click="setCurrentMonth">{{ __('This Month') }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="setLastMonth">{{ __('Last Month') }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="setCurrentQuarter">{{ __('This Quarter') }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" wire:click="setYearToDate">{{ __('YTD') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Taxable Sales') }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number::currency($totals['taxable'], config('app.currency'), config('app.locale')) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Non-Taxable Sales') }}</p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ Number::currency($totals['non_taxable'], config('app.currency'), config('app.locale')) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Tax Collected') }}</p>
            <p class="mt-2 text-2xl font-bold text-[#3F5189]">{{ Number::currency($totals['tax'], config('app.currency'), config('app.locale')) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                {{ $basis === 'cash' ? __('Payments') : __('Invoices') }}
            </p>
            <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ $totals['ref_count'] }}</p>
        </div>
    </div>

    <!-- Summary by Tax Rate -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Summary by Tax Rate') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Tax Rate') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Taxable Sales') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Non-Taxable') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Tax Collected') }}</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ $basis === 'cash' ? __('Payments') : __('Invoices') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($groupedByRate as $row)
                        <tr>
                            <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ number_format((float) $row->tax_rate * 100, 4) }}%</td>
                            <td class="px-6 py-3 text-sm text-slate-900 dark:text-white text-right">{{ Number::currency($row->taxable, config('app.currency'), config('app.locale')) }}</td>
                            <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400 text-right">{{ Number::currency($row->non_taxable, config('app.currency'), config('app.locale')) }}</td>
                            <td class="px-6 py-3 text-sm font-semibold text-[#3F5189] text-right">{{ Number::currency($row->tax, config('app.currency'), config('app.locale')) }}</td>
                            <td class="px-6 py-3 text-sm text-slate-900 dark:text-white text-right">{{ $row->ref_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No data found for the selected period.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($groupedByRate->isNotEmpty())
                    <tfoot class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <td class="px-6 py-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Total') }}</td>
                            <td class="px-6 py-3 text-sm font-bold text-slate-900 dark:text-white text-right">{{ Number::currency($totals['taxable'], config('app.currency'), config('app.locale')) }}</td>
                            <td class="px-6 py-3 text-sm font-bold text-slate-900 dark:text-white text-right">{{ Number::currency($totals['non_taxable'], config('app.currency'), config('app.locale')) }}</td>
                            <td class="px-6 py-3 text-sm font-bold text-[#3F5189] text-right">{{ Number::currency($totals['tax'], config('app.currency'), config('app.locale')) }}</td>
                            <td class="px-6 py-3 text-sm font-bold text-slate-900 dark:text-white text-right">{{ $totals['ref_count'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- Breakdown -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ $basis === 'cash' ? __('Payment Breakdown') : __('Invoice Breakdown') }}
            </h3>
        </div>
        <div class="overflow-x-auto">
            @if($basis === 'cash')
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Payment Date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Invoice #') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Client') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Method') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Payment Amount') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Share') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Attributed Tax') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($breakdown as $payment)
                            <tr>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $payment->payment_date->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-sm font-medium">
                                    @if($payment->invoice)
                                        <a href="{{ route('invoices.show', $payment->invoice->id) }}" class="text-[#3F5189] hover:underline">{{ $payment->invoice->invoice_number }}</a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $payment->invoice?->client?->company_name }}</td>
                                <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $payment->getPaymentMethodLabel() }}</td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white text-right">{{ Number::currency($payment->amount, config('app.currency'), config('app.locale')) }}</td>
                                <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400 text-right">{{ number_format($payment->payment_share * 100, 2) }}%</td>
                                <td class="px-6 py-3 text-sm font-semibold text-[#3F5189] text-right">{{ Number::currency($payment->attributed_tax, config('app.currency'), config('app.locale')) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No payments found for the selected period.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Invoice #') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Client') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Subtotal') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Discount') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Tax') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($breakdown as $invoice)
                            <tr>
                                <td class="px-6 py-3 text-sm font-medium">
                                    <a href="{{ route('invoices.show', $invoice->id) }}" class="text-[#3F5189] hover:underline">{{ $invoice->invoice_number }}</a>
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $invoice->invoice_date->format('M d, Y') }}</td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">{{ $invoice->client?->company_name }}</td>
                                <td class="px-6 py-3 text-sm">
                                    <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">{{ $invoice->status_label }}</span>
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white text-right">{{ Number::currency($invoice->subtotal, config('app.currency'), config('app.locale')) }}</td>
                                <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400 text-right">{{ Number::currency($invoice->discount_amount, config('app.currency'), config('app.locale')) }}</td>
                                <td class="px-6 py-3 text-sm font-semibold text-[#3F5189] text-right">{{ Number::currency($invoice->tax_total, config('app.currency'), config('app.locale')) }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white text-right">{{ Number::currency($invoice->total_amount, config('app.currency'), config('app.locale')) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No invoices found for the selected period.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
