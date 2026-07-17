<!-- Expense Modal -->
<x-ui.modal name="expense-modal" maxWidth="4xl">
    <div class="p-6">
        <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">
            @if($expenseModalMode === 'view')
                {{ __('Expense Details') }}
            @elseif($expenseModalMode === 'edit')
                {{ __('Edit Expense') }}
            @else
                {{ __('Add Expense') }}
            @endif
        </h2>

        @if($expenseModalMode === 'view')
            <!-- View Mode -->
            <div class="space-y-4">
                <!-- Header Info -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Date') }}</label>
                        <p class="text-slate-900 dark:text-white">{{ $expense_date ? \Carbon\Carbon::parse($expense_date)->format('M d, Y') : '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Supplier') }}</label>
                        <p class="text-slate-900 dark:text-white">{{ $supplierSearch ?: __('No Supplier') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Location') }}</label>
                        <p class="text-slate-900 dark:text-white">
                            @if($expense_job_site_id)
                                {{ $jobSites->firstWhere('id', $expense_job_site_id)?->job_site_name ?? __('Unknown') }}
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">{{ __('Project (General)') }}</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Total Amount') }}</label>
                        <p class="text-xl font-bold text-slate-900 dark:text-white">{{ Number::currency($expense_total_amount ?: 0, config('app.currency'), config('app.locale')) }}</p>
                    </div>
                </div>

                <!-- Items Table -->
                @if(count($expenseItems) > 0)
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Items') }}</label>
                        <div class="bg-slate-50 dark:bg-slate-900 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Cost Code') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Item') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Qty') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Unit Price') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($expenseItems as $item)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                @php
                                                    $budgetItem = $item['budget_item_id'] ? \App\Models\BudgetItem::find($item['budget_item_id']) : null;
                                                @endphp
                                                {{ $budgetItem ? $budgetItem->code . ' - ' . $budgetItem->name : __('Miscellaneous') }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                {{ $item['item_name'] }}
                                                @if($item['unit'])
                                                    <span class="text-xs text-slate-500">({{ $item['unit'] }})</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm text-slate-900 dark:text-white text-right">{{ $item['quantity'] }}</td>
                                            <td class="px-4 py-2 text-sm text-slate-900 dark:text-white text-right">{{ Number::currency($item['unit_price'] ?: 0, config('app.currency'), config('app.locale')) }}</td>
                                            <td class="px-4 py-2 text-sm font-medium text-slate-900 dark:text-white text-right">{{ Number::currency($item['total_amount'] ?: 0, config('app.currency'), config('app.locale')) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if($expense_notes)
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
                        <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $expense_notes }}</p>
                    </div>
                @endif

                @if($existingReceiptPath)
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Receipt') }}</label>
                        <a href="{{ route('files.show', ['path' => $existingReceiptPath]) }}" target="_blank" class="text-[#3F5189] hover:underline">{{ __('View Receipt') }}</a>
                    </div>
                @endif

                <!-- Payment Information Section -->
                <div class="border-t border-slate-200 dark:border-slate-700 pt-4 mt-4">
                    <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-4">{{ __('Payment Information') }}</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Status') }}</label>
                            @php
                                $statusColors = [
                                    'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                    'unpaid' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                                    'partial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
                                    'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                    'cancelled' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $statusColors[$expense_status] ?? $statusColors['unpaid'] }}">
                                {{ __(ucfirst($expense_status)) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Payment Method') }}</label>
                            <p class="text-slate-900 dark:text-white">
                                {{ $expense_payment_method ? __(str_replace('_', ' ', ucfirst($expense_payment_method))) : __('Not specified') }}
                                @if($expense_is_auto_payment)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300">{{ __('Auto') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($expense_has_installments && $viewingExpense)
                        <!-- Installment Payment Schedule -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-3">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Payment Schedule') }}</label>
                                <span class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ $viewingExpense->getPaidInstallmentsCount() }}/{{ $viewingExpense->total_installments }} {{ __('paid') }}
                                </span>
                            </div>

                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 mb-4">
                                <div class="bg-green-500 h-2.5 rounded-full" style="width: {{ $viewingExpense->getPaymentProgress() }}%"></div>
                            </div>

                            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">#</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Due Date') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Amount') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Status') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Paid Date') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                        @foreach($viewingExpense->payments as $payment)
                                            <tr>
                                                <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">{{ $payment->payment_number }}</td>
                                                <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">{{ $payment->due_date->format('M d, Y') }}</td>
                                                <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">{{ Number::currency($payment->amount, config('app.currency'), config('app.locale')) }}</td>
                                                <td class="px-4 py-2">
                                                    @php
                                                        $paymentStatusColors = [
                                                            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                                            'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
                                                            'overdue' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                                        ];
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $paymentStatusColors[$payment->status] ?? $paymentStatusColors['pending'] }}">
                                                        {{ __(ucfirst($payment->status)) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                    {{ $payment->paid_date ? $payment->paid_date->format('M d, Y') : '-' }}
                                                    @if($payment->status === 'paid' && $payment->paidBy)
                                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('by') }} {{ $payment->paidBy->name }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-right">
                                                    @if($payment->status === 'pending')
                                                        <button wire:click="markPaymentAsPaid({{ $payment->id }})" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">{{ __('Mark Paid') }}</button>
                                                        <button wire:click="markPaymentAsOverdue({{ $payment->id }})" class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium">{{ __('Overdue') }}</button>
                                                    @elseif($payment->status === 'overdue')
                                                        <button wire:click="markPaymentAsPaid({{ $payment->id }})" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">{{ __('Mark Paid') }}</button>
                                                    @elseif($payment->status === 'paid')
                                                        @admin
                                                            <button wire:click="unmarkPaymentPaid({{ $payment->id }})" wire:confirm="{{ __('Revert this payment to pending?') }}" class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 text-sm font-medium">{{ __('Revert') }}</button>
                                                        @endadmin
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-3 flex justify-between text-sm">
                                <span class="text-green-600 dark:text-green-400 font-medium">{{ __('Paid:') }} {{ Number::currency($viewingExpense->getPaidAmount(), config('app.currency'), config('app.locale')) }}</span>
                                <span class="text-amber-600 dark:text-amber-400 font-medium">{{ __('Pending:') }} {{ Number::currency($viewingExpense->getPendingAmount(), config('app.currency'), config('app.locale')) }}</span>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            @if($expense_status === 'paid' && $expense_paid_date)
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Paid Date') }}</label>
                                    <p class="text-slate-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($expense_paid_date)->format('M d, Y') }}
                                        @if($viewingExpense?->paidBy)
                                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('by') }} {{ $viewingExpense->paidBy->name }}</span>
                                        @endif
                                    </p>
                                </div>
                            @elseif($expense_status === 'unpaid' && $expense_payment_due_date)
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Due Date') }}</label>
                                    <p class="text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($expense_payment_due_date)->format('M d, Y') }}</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if($viewingExpense)
                <div class="mt-6 border-t border-slate-200 dark:border-slate-700 pt-4">
                    <livewire:shared.attachments
                        model-type="expense"
                        :model-id="$viewingExpense->id"
                        :key="'expense-attachments-'.$viewingExpense->id" />
                </div>
            @endif

            @include('livewire.project.partials.expense-history')

            <div class="flex items-center justify-end space-x-4 mt-6">
                <x-ui.button type="button" variant="secondary" wire:click="closeExpenseModal">{{ __('Close') }}</x-ui.button>
            </div>
        @else
            <!-- Create/Edit Mode -->
            <form wire:submit.prevent="saveExpense" class="space-y-4">
                <!-- Header Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Location') }}</label>
                        <select wire:model.live="expense_job_site_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="">{{ __('Project (General)') }}</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Supplier -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Supplier') }}</label>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="supplierSearch" placeholder="{{ __('Search supplier...') }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @if($expense_supplier_id)
                                <button type="button" wire:click="clearSupplier" class="absolute right-2 top-2 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        @if($suppliers->count() > 0 && !$expense_supplier_id)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                @foreach($suppliers as $supplier)
                                    <button type="button" wire:click="selectSupplier({{ $supplier->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $supplier->name }}</div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Expense Date') }} <span class="text-red-500">*</span></label>
                        <input type="date" wire:model="expense_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        @error('expense_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Items Section -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-white">{{ __('Items') }}</h3>
                        <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="addExpenseItem">{{ __('Add Item') }}</x-ui.button>
                    </div>

                    @error('expenseItems') <div class="text-red-500 text-sm mb-2">{{ $message }}</div> @enderror

                    <div class="space-y-4">
                        @foreach($expenseItems as $index => $item)
                            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4" wire:key="expense-item-{{ $index }}">
                                <div class="flex items-start justify-between mb-3">
                                    <span class="text-sm font-medium text-slate-500">{{ __('Item') }} #{{ $index + 1 }}</span>
                                    @if(count($expenseItems) > 1)
                                        <button type="button" wire:click="removeExpenseItem({{ $index }})" class="text-red-500 hover:text-red-700">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                                    <!-- Cost Code -->
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Cost Code') }}</label>
                                        <select wire:model="expenseItems.{{ $index }}.budget_item_id" class="w-full px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded focus:outline-none focus:ring-1 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                            <option value="">{{ __('Miscellaneous (Auto)') }}</option>
                                            @foreach($budgetItems as $bi)
                                                <option value="{{ $bi['id'] }}">{{ $bi['full_name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Item Name -->
                                    <div class="md:col-span-2">
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Item Name') }} <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <input type="text" wire:model.live.debounce.300ms="catalogItemSearches.{{ $index }}" placeholder="{{ __('Search or type...') }}" class="w-full px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded focus:outline-none focus:ring-1 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                            @if($activeSearchIndex === $index && $catalogItems->count() > 0)
                                                <div class="absolute z-20 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                                    @foreach($catalogItems as $catalogItem)
                                                        <button type="button" wire:click="selectCatalogItemForRow({{ $catalogItem->id }}, {{ $index }})" class="w-full px-3 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $catalogItem->name }}</div>
                                                            <div class="text-xs text-slate-500">{{ Number::currency($catalogItem->current_cost, config('app.currency'), config('app.locale')) }}</div>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <input type="hidden" wire:model="expenseItems.{{ $index }}.item_name" value="{{ $catalogItemSearches[$index] ?? '' }}">
                                        @error('expenseItems.'.$index.'.item_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Unit -->
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Unit') }}</label>
                                        <input type="text" wire:model="expenseItems.{{ $index }}.unit" placeholder="{{ __('Each') }}" class="w-full px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded focus:outline-none focus:ring-1 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    </div>

                                    <!-- Remove placeholder for alignment -->
                                    <div class="hidden md:block"></div>
                                </div>

                                <div class="grid grid-cols-3 gap-3 mt-3">
                                    <!-- Quantity -->
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Qty') }} <span class="text-red-500">*</span></label>
                                        <input type="number" step="0.01" wire:model.live="expenseItems.{{ $index }}.quantity" class="w-full px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded focus:outline-none focus:ring-1 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        @error('expenseItems.'.$index.'.quantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Unit Price -->
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Unit Price') }} <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute left-2 top-1.5 text-slate-500 text-sm">$</span>
                                            <input type="number" step="0.01" wire:model.live="expenseItems.{{ $index }}.unit_price" class="w-full pl-6 pr-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded focus:outline-none focus:ring-1 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        </div>
                                        @error('expenseItems.'.$index.'.unit_price') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Line Total -->
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">{{ __('Total') }}</label>
                                        <div class="px-2 py-1.5 text-sm bg-slate-100 dark:bg-slate-800 rounded text-slate-900 dark:text-white font-medium">
                                            {{ Number::currency($item['total_amount'] ?? 0, config('app.currency'), config('app.locale')) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Total -->
                    <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                        <div class="text-right">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Total Amount:') }}</span>
                            <span class="ml-2 text-xl font-bold text-slate-900 dark:text-white">{{ Number::currency($expense_total_amount ?? 0, config('app.currency'), config('app.locale')) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Notes') }}</label>
                    <textarea wire:model="expense_notes" rows="2" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white" placeholder="{{ __('Optional notes...') }}"></textarea>
                </div>

                <!-- Receipt Upload -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Receipt') }}</label>
                    @if($existingReceiptPath && !$expense_receipt)
                        <div class="mb-2 p-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg flex items-center justify-between text-sm">
                            <span class="text-slate-600 dark:text-slate-400">{{ __('Current receipt attached') }}</span>
                            <a href="{{ route('files.download', ['path' => $existingReceiptPath]) }}" class="text-[#3F5189] hover:underline font-medium">{{ __('Download') }}</a>
                        </div>
                    @endif
                    <input type="file" wire:model="expense_receipt" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#3F5189] file:text-white hover:file:bg-[#2F3F6F]">
                    @error('expense_receipt') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Payment Section -->
                <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                    <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-4">{{ __('Payment Information') }}</h3>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Method') }}</label>
                            <select wire:model="expense_payment_method" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="">{{ __('Select method') }}</option>
                                <option value="cash">{{ __('Cash') }}</option>
                                <option value="check">{{ __('Check') }}</option>
                                <option value="credit_card">{{ __('Credit Card') }}</option>
                                <option value="debit_card">{{ __('Debit Card') }}</option>
                                <option value="bank_transfer">{{ __('Bank Transfer') }}</option>
                                @if(config('app.country') === 'BR')
                                    <option value="pix">PIX</option>
                                @endif
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="expense_is_auto_payment" class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">{{ __('Auto Payment') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Installments Toggle -->
                    <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-900 rounded-lg mb-4">
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Split into installments') }}</span>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Enable for multiple payments') }}</p>
                        </div>
                        <button type="button" wire:click="$toggle('expense_has_installments')" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $expense_has_installments ? 'bg-[#3F5189]' : 'bg-slate-200' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $expense_has_installments ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>

                    @if(!$expense_has_installments)
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Status') }}</label>
                                <select wire:model.live="expense_status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="paid">{{ __('Paid') }}</option>
                                    <option value="unpaid">{{ __('Unpaid') }}</option>
                                </select>
                            </div>
                            @if($expense_status === 'paid')
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Paid Date') }}</label>
                                    <input type="date" wire:model="expense_paid_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                </div>
                            @else
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Due Date') }} <span class="text-red-500">*</span></label>
                                    <input type="date" wire:model="expense_payment_due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @error('expense_payment_due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('# of Installments') }} <span class="text-red-500">*</span></label>
                                <input type="number" min="2" max="120" wire:model.live="expense_total_installments" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('expense_total_installments') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Frequency') }} <span class="text-red-500">*</span></label>
                                <select wire:model.live="expense_payment_frequency" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="weekly">{{ __('Weekly') }}</option>
                                    <option value="biweekly">{{ __('Biweekly') }}</option>
                                    <option value="monthly">{{ __('Monthly') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('First Payment') }} <span class="text-red-500">*</span></label>
                                <input type="date" wire:model.live="expense_payment_due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('expense_payment_due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        @if(count($expense_payment_schedule_preview) > 0)
                            <div class="mt-4 bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                                <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Preview') }}</h4>
                                <div class="max-h-32 overflow-auto">
                                    <table class="min-w-full text-sm">
                                        <tbody>
                                            @foreach($expense_payment_schedule_preview as $preview)
                                                <tr class="border-b border-slate-200 dark:border-slate-700 last:border-0">
                                                    <td class="py-1 text-slate-600 dark:text-slate-400">#{{ $preview['number'] }}</td>
                                                    <td class="py-1 text-slate-900 dark:text-white">{{ $preview['due_date_formatted'] }}</td>
                                                    <td class="py-1 text-right text-slate-900 dark:text-white font-medium">{{ Number::currency($preview['amount'], config('app.currency'), config('app.locale')) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-4">
                    <x-ui.button type="button" variant="secondary" wire:click="closeExpenseModal">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ $expenseModalMode === 'edit' ? __('Update') : __('Add Expense') }}</x-ui.button>
                </div>
            </form>
        @endif
    </div>
</x-ui.modal>
