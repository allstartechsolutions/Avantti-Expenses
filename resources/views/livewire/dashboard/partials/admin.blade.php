@php
    $kpis = $this->kpis;
    $cashflow = $this->cashflowChart;
    $overduePayments = $this->overduePayments;
    $pastDueInvoices = $this->pastDueInvoicesList;
    $overBudgetProjects = $this->overBudgetProjects;
    $pendingApprovals = $this->pendingApprovals;
    $currency = config('app.currency');
    $locale = config('app.locale');
    $headerSubtitle = $modules['invoices']
        ? __('Financial overview and pending actions')
        : __('Operations overview and pending actions');
@endphp

<div>
    {{-- Header --}}
    <div class="mb-6 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Dashboard') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $headerSubtitle }}</p>
        </div>
        <div class="flex items-center gap-2">
            <label for="month" class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Month') }}</label>
            <select
                id="month"
                wire:model.live="month"
                class="block rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]"
            >
                @foreach ($this->availableMonths as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Cash to Pay --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Cash to Pay') }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Number::currency($kpis['cash_to_pay'], $currency, $locale) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Due this month') }}</p>
                </div>
                <div class="h-10 w-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        @if ($modules['invoices'])
            {{-- Receivables --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Receivables') }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {{ Number::currency($kpis['receivables'], $currency, $locale) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <span>{{ __('Due this month') }}</span>
                            @if ($kpis['past_due_invoices'] > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                    {{ $kpis['past_due_invoices'] }} {{ __('past due') }}
                                </span>
                            @endif
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                </div>
            </div>
        @else
            {{-- Projects Over Budget (replaces Receivables when invoices module is off) --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Over Budget') }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {{ $kpis['projects_over_budget'] }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Active projects over budget') }}</p>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                </div>
            </div>
        @endif

        @if ($modules['estimates'])
            {{-- Open Estimates --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Open Estimates') }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {{ Number::currency($kpis['open_estimates'], $currency, $locale) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $kpis['open_estimates_count'] }} {{ __('in pipeline') }}
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        @else
            {{-- Open Purchase Orders (replaces Open Estimates when estimates module is off) --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Open Purchase Orders') }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {{ $kpis['open_purchase_orders'] }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Pending approval') }}</p>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                </div>
            </div>
        @endif

        {{-- Active Projects --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Active Projects') }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $kpis['active_projects'] }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 flex items-center gap-2">
                        @if ($kpis['at_risk_projects'] > 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                {{ $kpis['at_risk_projects'] }} {{ __('at risk') }}
                            </span>
                        @else
                            <span>{{ __('All on track') }}</span>
                        @endif
                    </p>
                </div>
                <div class="h-10 w-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                    <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Action lists --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        {{-- Overdue Payments --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Overdue Payments') }}</h3>
                <a href="{{ route('payments.index') }}" class="text-xs text-[#3F5189] dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-slate-700 flex-1">
                @forelse ($overduePayments as $payment)
                    @php
                        $expense = $payment->expense;
                        $daysOverdue = $payment->due_date ? now()->startOfDay()->diffInDays($payment->due_date, false) : 0;
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                {{ $expense?->supplier?->name ?? $expense?->item_name ?? __('Expense') }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                {{ $expense?->project?->project_name ?? __('—') }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ Number::currency($payment->amount, $currency, $locale) }}
                            </p>
                            <p class="text-xs text-red-600 dark:text-red-400">
                                {{ abs($daysOverdue) }} {{ __('days overdue') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ __('No overdue payments') }}
                    </div>
                @endforelse
            </div>
        </div>

        @if ($modules['invoices'])
            {{-- Past-Due Invoices --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Past-Due Invoices') }}</h3>
                    <a href="{{ route('invoices.index') }}" class="text-xs text-[#3F5189] dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700 flex-1">
                    @forelse ($pastDueInvoices as $invoice)
                        @php
                            $daysPast = now()->startOfDay()->diffInDays($invoice->due_date, false);
                        @endphp
                        <a href="{{ route('invoices.show', $invoice->id) }}" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                        #{{ $invoice->invoice_number }} — {{ $invoice->client?->company_name ?? __('Client') }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                        {{ $invoice->project?->project_name ?? __('—') }}
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ Number::currency($invoice->getBalanceDue(), $currency, $locale) }}
                                    </p>
                                    <p class="text-xs text-red-600 dark:text-red-400">
                                        {{ abs($daysPast) }} {{ __('days past') }}
                                    </p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('No past-due invoices') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @else
            {{-- Over Budget Projects (replaces Past-Due Invoices when invoices module is off) --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Projects Over Budget') }}</h3>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700 flex-1">
                    @forelse ($overBudgetProjects as $project)
                        @php
                            $contractValueDollars = $project->getAdjustedContractValue();
                            $expensesDollars = round(($project->expenses_total ?? 0) / 100, 2);
                            $overageDollars = round($expensesDollars - $contractValueDollars, 2);
                            $percentOver = $contractValueDollars > 0
                                ? round((($expensesDollars - $contractValueDollars) / $contractValueDollars) * 100, 0)
                                : 0;
                        @endphp
                        <a href="{{ route('projects.overview', $project->id) }}" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                        {{ $project->project_name }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                        {{ __('Budget') }}: {{ Number::currency($contractValueDollars, $currency, $locale) }}
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-red-600 dark:text-red-400">
                                        +{{ Number::currency($overageDollars, $currency, $locale) }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        +{{ $percentOver }}%
                                    </p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('All projects within budget') }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- Pending Approvals --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Pending Approvals') }}</h3>
            </div>
            <div class="divide-y divide-slate-200 dark:divide-slate-700 flex-1">
                @forelse ($pendingApprovals['purchase_orders'] as $po)
                    <a href="{{ route('purchase-orders.show', $po->id) }}" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                    {{ __('PO') }} #{{ $po->po_number ?? $po->id }} — {{ $po->supplier?->name ?? __('Supplier') }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {{ $po->project?->project_name ?? __('—') }}
                                </p>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 shrink-0">
                                {{ __('PO') }}
                            </span>
                        </div>
                    </a>
                @empty
                @endforelse

                @forelse ($pendingApprovals['payment_batches'] as $batch)
                    <a href="{{ route('payment-batches.show', $batch->id) }}" class="block px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                    {{ $batch->name ?? __('Batch') . ' #' . $batch->id }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    {{ $batch->items_count }} {{ __('items') }}
                                </p>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 shrink-0">
                                {{ __('Batch') }}
                            </span>
                        </div>
                    </a>
                @empty
                @endforelse

                @if ($pendingApprovals['purchase_orders']->isEmpty() && $pendingApprovals['payment_batches']->isEmpty())
                    <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Nothing waiting for approval') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Cashflow Chart --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5"
         x-data="cashflowChart(@js($cashflow), '{{ $currency }}')"
         x-init="render()"
         wire:ignore>
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                {{ $modules['invoices'] ? __('Cash Flow (Last 6 Months)') : __('Cash Out (Last 6 Months)') }}
            </h3>
            <div class="flex items-center gap-4 text-xs">
                @if ($modules['invoices'])
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-sm bg-green-500"></span>
                        <span class="text-slate-600 dark:text-slate-400">{{ __('AR (received)') }}</span>
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-sm bg-red-500"></span>
                    <span class="text-slate-600 dark:text-slate-400">{{ __('AP (paid)') }}</span>
                </span>
            </div>
        </div>
        <div class="h-72">
            <canvas x-ref="canvas"></canvas>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            function cashflowChart(data, currency) {
                return {
                    chart: null,
                    render() {
                        if (this.chart) {
                            this.chart.destroy();
                        }
                        const ctx = this.$refs.canvas.getContext('2d');
                        const datasets = [];
                        if (data.show_inflow) {
                            datasets.push({
                                label: 'AR',
                                data: data.inflow,
                                backgroundColor: 'rgba(34, 197, 94, 0.7)',
                                borderColor: 'rgb(34, 197, 94)',
                                borderWidth: 1,
                            });
                        }
                        datasets.push({
                            label: 'AP',
                            data: data.outflow,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: 'rgb(239, 68, 68)',
                            borderWidth: 1,
                        });
                        this.chart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: data.labels,
                                datasets: datasets,
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => {
                                                const v = ctx.parsed.y;
                                                return ctx.dataset.label + ': ' + new Intl.NumberFormat(undefined, {
                                                    style: 'currency',
                                                    currency: currency,
                                                }).format(v);
                                            },
                                        },
                                    },
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: (v) => new Intl.NumberFormat(undefined, {
                                                style: 'currency',
                                                currency: currency,
                                                maximumFractionDigits: 0,
                                            }).format(v),
                                        },
                                    },
                                },
                            },
                        });
                    },
                };
            }
        </script>
    @endpush
</div>
