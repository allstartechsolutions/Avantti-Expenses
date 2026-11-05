@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $select = 'block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $statusColors = [
        'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
        'unpaid' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
        'partial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
        'cancelled' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
    ];
@endphp
<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Company Expenses') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('What the company pays that belongs to no project — rent, utilities, insurance, fuel, office — filed by category with its account code.') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                @can('reports.export')
                    <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">{{ __('Export CSV') }}</x-ui.button>
                @endcan
                @can('company-expenses.create')
                    <x-ui.button variant="primary" href="{{ route('company-expenses.create') }}" icon="plus">{{ __('Add Company Expense') }}</x-ui.button>
                @endcan
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-5 text-white">
            <p class="text-sm font-medium text-white/80">{{ __('Total') }}</p>
            <x-ui.money class="block text-2xl font-bold mt-1" :amount="$totalAmount" rollup />
            <p class="mt-1 text-xs text-white/80">{{ trans_choice(':count expense|:count expenses', $expenses->count(), ['count' => $expenses->count()]) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Paid') }}</p>
            <x-ui.money class="block text-2xl font-bold mt-1 text-green-600 dark:text-green-400" :amount="$paidAmount" rollup />
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Settled, in full or by installment') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Pending') }}</p>
            <x-ui.money class="block text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400" :amount="$pendingAmount" rollup />
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Still to be paid') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Overdue') }}</p>
            <x-ui.money class="block text-2xl font-bold mt-1 {{ $overdueAmount > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}" :amount="$overdueAmount" rollup />
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Past its due date') }}</p>
        </div>
    </div>

    <!-- Search and filters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 p-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-4">
                <label for="search" class="sr-only">{{ __('Search company expenses') }}</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" id="search" wire:model.live.debounce.300ms="search" class="{{ $select }} pl-10 placeholder-slate-400" placeholder="{{ __('Search by item, vendor, category, code, notes, check or machine…') }}">
                </div>
            </div>
            <div class="md:col-span-2">
                <label for="categoryFilter" class="sr-only">{{ __('Category') }}</label>
                <select id="categoryFilter" wire:model.live="categoryFilter" class="{{ $select }}">
                    <option value="">{{ __('Category: all') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->getDisplayLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="vendorFilter" class="sr-only">{{ __('Vendor') }}</label>
                <select id="vendorFilter" wire:model.live="vendorFilter" class="{{ $select }}">
                    <option value="">{{ __('Vendor: all') }}</option>
                    @foreach($vendors as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="statusFilter" class="sr-only">{{ __('Status') }}</label>
                <select id="statusFilter" wire:model.live="statusFilter" class="{{ $select }}">
                    <option value="">{{ __('Status: all') }}</option>
                    @foreach(['unpaid', 'partial', 'paid', 'overdue', 'cancelled'] as $status)
                        <option value="{{ $status }}">{{ \App\Models\Expense::statusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="methodFilter" class="sr-only">{{ __('Payment Method') }}</label>
                <select id="methodFilter" wire:model.live="methodFilter" class="{{ $select }}">
                    <option value="">{{ __('Payment method: all') }}</option>
                    @foreach(\App\Livewire\CompanyExpense\CompanyExpenseIndex::METHODS as $method)
                        <option value="{{ $method }}">{{ \App\Livewire\CompanyExpense\CompanyExpenseIndex::methodLabel($method) }}</option>
                    @endforeach
                    <option value="autopay">{{ __('Autopay') }}</option>
                    <option value="none">{{ __('No method') }}</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="fromDate" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('From') }}</label>
                <x-ui.date-input id="fromDate" wire:model.live="fromDate" class="{{ $select }}" />
            </div>
            <div class="md:col-span-2">
                <label for="toDate" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('To') }}</label>
                <x-ui.date-input id="toDate" wire:model.live="toDate" class="{{ $select }}" />
            </div>
        </div>
        @if($this->hasFilters())
            <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Totals above follow the filters.') }}</p>
                <x-ui.button variant="secondary" size="sm" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button>
            </div>
        @endif
    </div>

    {{-- How the filtered expenses were paid (client request 25, Nov 2026). A row filters the list to it. --}}
    @if($methodSummary !== [])
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('By payment method') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('For the dates and filters above. Click a method to list only its expenses.') }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Payment Method') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Expenses') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Total') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Paid') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Pending') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($methodSummary as $row)
                            <tr wire:key="method-{{ $row['key'] }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 {{ $methodFilter === $row['key'] ? 'bg-[#3F5189]/5 dark:bg-[#4A5A96]/10' : '' }}">
                                <td class="px-6 py-2.5">
                                    <button type="button" wire:click="$set('methodFilter', '{{ $methodFilter === $row['key'] ? '' : $row['key'] }}')" class="font-medium text-[#3F5189] dark:text-[#8B9BD0] hover:underline">{{ $row['label'] }}</button>
                                </td>
                                <td class="px-6 py-2.5 text-right text-slate-700 dark:text-slate-300">{{ $row['count'] }}</td>
                                <td class="px-6 py-2.5 text-right"><x-ui.money class="font-medium text-slate-900 dark:text-white" :amount="$row['total']" rollup /></td>
                                <td class="px-6 py-2.5 text-right"><x-ui.money class="text-green-600 dark:text-green-400" :amount="$row['paid']" rollup /></td>
                                <td class="px-6 py-2.5 text-right"><x-ui.money class="text-amber-600 dark:text-amber-400" :amount="$row['pending']" rollup /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 dark:border-slate-600">
                        <tr>
                            <td class="px-6 py-2.5 font-semibold text-slate-900 dark:text-white">{{ __('Total') }}</td>
                            <td class="px-6 py-2.5 text-right font-semibold text-slate-900 dark:text-white">{{ collect($methodSummary)->sum('count') }}</td>
                            <td class="px-6 py-2.5 text-right"><x-ui.money class="font-bold text-slate-900 dark:text-white" :amount="collect($methodSummary)->sum('total')" rollup /></td>
                            <td class="px-6 py-2.5 text-right"><x-ui.money class="font-semibold text-green-600 dark:text-green-400" :amount="collect($methodSummary)->sum('paid')" rollup /></td>
                            <td class="px-6 py-2.5 text-right"><x-ui.money class="font-semibold text-amber-600 dark:text-amber-400" :amount="collect($methodSummary)->sum('pending')" rollup /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    <!-- Expenses List -->
    @if($expenses->count() > 0)
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Date') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Category') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Vendor / Items') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Notes') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Total') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Payments') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($expenses as $expense)
                            <tr wire:key="company-expense-{{ $expense->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    {{ $expense->expense_date->appDate() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($expense->category)
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ __($expense->category->name) }}</div>
                                        <div class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $expense->category->account_code }}</div>
                                    @else
                                        <span class="text-sm text-slate-400 dark:text-slate-500">{{ __('Uncategorised') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $expense->supplier?->name ?? __('No Supplier') }}</div>
                                    @if($expense->items->count() > 0)
                                        <div class="text-xs text-slate-500 dark:text-slate-400" title="{{ $expense->items->pluck('item_name')->implode(', ') }}">
                                            {{ \Illuminate\Support\Str::limit($expense->items->first()->item_name, 40) }}
                                            @if($expense->items->count() > 1)
                                                <span class="text-slate-400">{{ trans_choice('+:count more item|+:count more items', $expense->items->count() - 1, ['count' => $expense->items->count() - 1]) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if($expense->equipment)
                                        <div class="mt-1 inline-flex max-w-full items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/20 dark:text-amber-300" title="{{ __('Machine') }}">
                                            <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            <span class="truncate">{{ $expense->equipment->displayName() }}</span>
                                        </div>
                                    @endif
                                </td>
                                {{-- Who or what the expense is for, without opening it. Two lines,
                                     the whole note on hover. --}}
                                <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 min-w-[12rem] max-w-xs">
                                    @if(filled($expense->notes))
                                        <div class="line-clamp-2 whitespace-pre-line break-words" title="{{ $expense->notes }}">{{ $expense->notes }}</div>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <x-ui.money class="text-sm font-medium text-slate-900 dark:text-white" :amount="$expense->total_amount" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    {{ $expense->getPaymentLabel() }}
                                    @if($expense->check_number)
                                        <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[12rem]" title="{{ $expense->check_number }}">{{ __('Check no. :number', ['number' => $expense->check_number]) }}</div>
                                    @endif
                                    @if($expense->isInstallment())
                                        <div class="w-24 bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mt-1">
                                            <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $expense->getPaymentProgress() }}%"></div>
                                        </div>
                                    @elseif($expense->status === 'paid' && $expense->paid_date)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $expense->paid_date->appDate() }}</div>
                                    @elseif($expense->payment_due_date)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Due') }} {{ $expense->payment_due_date->appDate() }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$expense->status] ?? $statusColors['unpaid'] }}">
                                        {{ $expense->getStatusLabel() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        @include('livewire.expense.partials.files-button')
                                        @can('company-expenses.edit', $expense)
                                            <a href="{{ route('expenses.edit', $expense->id) }}" class="text-[#3F5189] hover:text-[#4A5A96] dark:text-[#4A5A96] dark:hover:text-[#5A6AA6]" title="{{ __('Edit') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </a>
                                        @endcan
                                        <button wire:click="openExpenseViewModal({{ $expense->id }})" class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]" title="{{ __('View') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        </button>
                                        @if($expense->status !== 'paid' && $expense->isOneTime())
                                            @can('company-expenses.pay', $expense)
                                                @if($markPaidType === 'expense' && $markPaidId === $expense->id)
                                                    <x-ui.date-input wire:model="markPaidDate" class="px-2 py-1 text-xs border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                                                    <button wire:click="confirmMarkPaid" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300" title="{{ __('Confirm') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    </button>
                                                    <button wire:click="cancelMarkPaid" class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300" title="{{ __('Cancel') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                @else
                                                    <button wire:click="startMarkPaid('expense', {{ $expense->id }})" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300" title="{{ __('Mark as Paid') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    </button>
                                                @endif
                                            @endcan
                                        @elseif($expense->status === 'paid' && $expense->isOneTime())
                                            @can('company-expenses.edit_paid', $expense)
                                                <button wire:click="unmarkExpensePaid({{ $expense->id }})" wire:confirm="{{ __('Revert this expense to unpaid?') }}" class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300" title="{{ __('Revert to Unpaid') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v1m-15-6l4-4m-4 4l4 4"></path></svg>
                                                </button>
                                            @endcan
                                        @endif
                                        @can('company-expenses.delete', $expense)
                                            <button wire:click="deleteExpense({{ $expense->id }})" wire:confirm="{{ __('Are you sure you want to delete this expense?') }}" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300" title="{{ __('Delete') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4m-4 4h.01M9 15h.01M13 15h.01M13 11h.01M13 19h.01M9 19h.01"></path>
                </svg>
                @if($this->hasFilters())
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No company expenses match these filters') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Widen the dates or clear a filter to see more.') }}</p>
                    <div class="mt-6">
                        <x-ui.button variant="secondary" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button>
                    </div>
                @else
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No company expenses yet') }}</h3>
                    @can('company-expenses.create')
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Rent, utilities, insurance and equipment upkeep are filed here — anything the company pays that belongs to no project.') }}</p>
                        <div class="mt-6">
                            <x-ui.button variant="primary" href="{{ route('company-expenses.create') }}" icon="plus">{{ __('Add Company Expense') }}</x-ui.button>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing has been filed yet. You can see company expenses but not add them — ask an administrator if that is wrong.') }}</p>
                    @endcan
                @endif
            </div>
        </div>
    @endif

    @include('livewire.expense.partials.view-modal')
</div>
