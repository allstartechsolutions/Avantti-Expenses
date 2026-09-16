<x-project-layout :project="$project" active="expenses" :title="__('Expenses')">
    <div class="space-y-6">
        <!-- Header with Search, Filter and Add Button -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 flex flex-col sm:flex-row gap-4">
                <!-- Search Bar -->
                <div class="relative max-w-md">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="expenseSearch"
                        placeholder="{{ __('Search expenses...') }}"
                        class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <!-- Location Filter -->
                <select
                    wire:model.live="expenseLocationFilter"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="all">{{ __('All Locations') }}</option>
                    <option value="project">{{ __('Project (General)') }}</option>
                    @foreach($jobSites as $js)
                        <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                    @endforeach
                </select>
                <!-- Status Filter -->
                <select
                    wire:model.live="expenseStatusFilter"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="all">{{ __('All Status') }}</option>
                    <option value="paid">{{ __('Paid') }}</option>
                    <option value="unpaid">{{ __('Unpaid') }}</option>
                    <option value="partial">{{ __('Partial') }}</option>
                    <option value="overdue">{{ __('Overdue') }}</option>
                    <option value="cancelled">{{ __('Cancelled') }}</option>
                </select>

                <select
                    wire:model.live="expenseCostCodeFilter"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="all">{{ __('All Cost Codes') }}</option>
                    @foreach($costCodes as $code)
                        <option value="{{ $code->code }}">{{ $code->code }} - {{ $code->name }}</option>
                    @endforeach
                </select>
            </div>
            @can('expenses.create', $project)
                <x-ui.button
                    variant="primary"
                    icon="plus"
                    href="{{ route('expenses.project.create', $project) }}">
                    {{ __('Add Expense') }}
                </x-ui.button>
            @endcan
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Total Expenses -->
            <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/80">{{ __('Total Expenses') }}</p>
                        <x-ui.money class="block text-2xl font-bold mt-1" :amount="$totalExpensesAmount" :scope="$project" rollup />
                    </div>
                    <div class="bg-white/10 rounded-full p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-2 text-sm text-white/80">{{ trans_choice(':count expense|:count expenses', $expenses->count(), ['count' => $expenses->count()]) }}</p>
            </div>
            <!-- Paid Amount -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Paid') }}</p>
                        <x-ui.money class="block text-2xl font-bold mt-1 text-green-600 dark:text-green-400" :amount="$totalPaidAmount" :scope="$project" rollup />
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Pending Amount -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Pending') }}</p>
                        <x-ui.money class="block text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400" :amount="$totalPendingAmount" :scope="$project" rollup />
                    </div>
                    <div class="bg-amber-100 dark:bg-amber-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses List -->
        @if($expenses->count() > 0)
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Supplier / Items') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost Codes') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Payments') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($expenses as $expense)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                        {{ $expense->expense_date->appDate() }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $expense->supplier?->name ?? __('No Supplier') }}
                                                </div>
                                                @if($expense->items->count() > 0)
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                                        {{ trans_choice(':count item|:count items', $expense->items->count(), ['count' => $expense->items->count()]) }}
                                                    </span>
                                                @elseif($expense->item_name)
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $expense->item_name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php $codes = $expense->items->pluck('budgetItem')->filter()->unique('id')->values(); @endphp
                                        @if($codes->isEmpty())
                                            <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('Unassigned') }}</span>
                                        @else
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($codes->take(2) as $code)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200" title="{{ $code->name }}">
                                                        {{ $code->code }}
                                                    </span>
                                                @endforeach
                                                @if($codes->count() > 2)
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">+{{ $codes->count() - 2 }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($expense->jobSite)
                                            <span class="text-sm text-slate-900 dark:text-white">{{ $expense->jobSite->job_site_name }}</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                                {{ __('Project (General)') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ Number::currency($expense->total_amount, config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                        <span class="font-medium">{{ $expense->getPaymentLabel() }}</span>
                                        @if($expense->isInstallment())
                                            <div class="w-16 bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mt-1">
                                                <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $expense->getPaymentProgress() }}%"></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $statusColors = [
                                                'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                                'unpaid' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                                                'partial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
                                                'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                                'cancelled' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$expense->status] ?? $statusColors['unpaid'] }}">
                                            {{ $expense->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            @can('expenses.edit', $expense)
                                            <a
                                                href="{{ route('expenses.edit', $expense->id) }}"
                                                class="text-[#3F5189] hover:text-[#4A5A96] dark:text-[#4A5A96] dark:hover:text-[#5A6AA6]"
                                                title="{{ __('Edit') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            @endcan
                                            <button
                                                wire:click="openExpenseViewModal({{ $expense->id }})"
                                                class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                title="{{ __('View') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </button>
                                            @if($expense->status !== 'paid' && $expense->isOneTime())
                                                @can('expenses.pay', $expense)
                                                @if($markPaidType === 'expense' && $markPaidId === $expense->id)
                                                    <x-ui.date-input wire:model="markPaidDate" class="px-2 py-1 text-xs border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                                                    <button
                                                        wire:click="confirmMarkPaid"
                                                        class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300"
                                                        title="{{ __('Confirm') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        wire:click="cancelMarkPaid"
                                                        class="text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300"
                                                        title="{{ __('Cancel') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                @else
                                                    <button
                                                        wire:click="startMarkPaid('expense', {{ $expense->id }})"
                                                        class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300"
                                                        title="{{ __('Mark as Paid') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                    </button>
                                                @endif
                                                @endcan
                                            @elseif($expense->status === 'paid' && $expense->isOneTime())
                                                @can('expenses.edit_paid', $expense)
                                                <button
                                                    wire:click="unmarkExpensePaid({{ $expense->id }})"
                                                    wire:confirm="{{ __('Revert this expense to unpaid?') }}"
                                                    class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300"
                                                    title="{{ __('Revert to Unpaid') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v1m-15-6l4-4m-4 4l4 4"></path>
                                                    </svg>
                                                </button>
                                                @endcan
                                            @endif
                                            @can('expenses.delete', $expense)
                                            <button
                                                wire:click="deleteExpense({{ $expense->id }})"
                                                wire:confirm="{{ __('Are you sure you want to delete this expense?') }}"
                                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                                title="{{ __('Delete') }}">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No expenses') }}</h3>
                    @can('expenses.create', $project)
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Get started by adding an expense.') }}</p>
                        <div class="mt-6">
                            <x-ui.button
                                variant="primary"
                                icon="plus"
                                href="{{ route('expenses.project.create', $project) }}">
                                {{ __('Add Expense') }}
                            </x-ui.button>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing has been recorded here yet. You can see expenses on this project but not add them — ask an administrator if that is wrong.') }}</p>
                    @endcan
                </div>
            </div>
        @endif
    </div>

    @include('livewire.expense.partials.view-modal')
</x-project-layout>
