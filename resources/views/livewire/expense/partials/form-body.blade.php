{{--
    The expense form body — details, line items and payment terms.
    Shared by ExpenseCreate and ExpenseEdit.
    Expects: $jobSites, $suppliers, $amountsLocked
--}}
        <!-- Expense Details Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Expense Details') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Basic information about this expense') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Location, Supplier, Date -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Location') }}</label>
                        <select
                            wire:model.live="expense_job_site_id"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
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
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="supplierSearch"
                                placeholder="{{ __('Search supplier...') }}"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @if($expense_supplier_id)
                                <button type="button" wire:click="clearSupplier" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        @if($suppliers->count() > 0)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                @foreach($suppliers as $supplier)
                                    <button type="button" wire:click="selectSupplier({{ $supplier->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $supplier->name }}</div>
                                        @if($supplier->city)
                                            <div class="text-xs text-slate-500">{{ $supplier->city }}, {{ $supplier->state }}</div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Expense Date') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            wire:model="expense_date"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        @error('expense_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Notes') }}</label>
                    <textarea
                        wire:model="expense_notes"
                        rows="2"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('Optional notes about this expense...') }}"></textarea>
                </div>

                <!-- Receipt -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Receipt') }}</label>
                    <x-ui.file-drop
                        wire:model="expense_receipt"
                        :multiple="false"
                        accept=".pdf,.jpg,.jpeg,.png"
                        :label="__('Drop the receipt here, or')"
                        :hint="__('PDF, JPG or PNG, up to 10MB.')">

                        @error('expense_receipt') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                        @if($expense_receipt)
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                    {{ $expense_receipt->getClientOriginalName() }}
                                </span>
                                <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                    {{ \App\Services\DocumentSettings::formatBytes($expense_receipt->getSize()) }}
                                </span>
                                <x-ui.icon-button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    type="button"
                                    wire:click="clearExpenseReceipt"
                                    title="{{ __('Remove :file', ['file' => $expense_receipt->getClientOriginalName()]) }}"
                                    aria-label="{{ __('Remove :file', ['file' => $expense_receipt->getClientOriginalName()]) }}"
                                    class="hover:text-red-600 dark:hover:text-red-400" />
                            </div>
                        @endif
                    </x-ui.file-drop>
                </div>
            </div>
        </div>

        <!-- Items Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Items') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ $amountsLocked
                                ? __('The amounts are fixed on this expense. Cost codes and names can still be corrected.')
                                : __('Add the items for this expense') }}
                        </p>
                    </div>
                    @unless($amountsLocked)
                        <x-ui.button type="button" variant="primary" icon="plus" wire:click="openAddItemModal">
                            {{ __('Add Item') }}
                        </x-ui.button>
                    @endunless
                </div>
            </div>
            <div class="p-6">
                @error('items') <div class="text-red-500 text-sm mb-4">{{ $message }}</div> @enderror

                @if(count($items) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Cost Code') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Item') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Unit Price') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Total') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($items as $index => $item)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-white">
                                            @if($item['cost_code'] ?? null)
                                                {{ $item['cost_code'] }}
                                            @else
                                                <span class="text-slate-400">{{ __('Unassigned') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item['item_name'] }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ $item['item_type'] === 'catalog' ? __('From Catalog') : __('Custom') }}
                                                @if($item['unit']) &middot; {{ $item['unit'] }} @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-white text-right">{{ $item['quantity'] }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-white text-right">{{ Number::currency($item['unit_price'], config('app.currency'), config('app.locale')) }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white text-right">{{ Number::currency($item['total_amount'], config('app.currency'), config('app.locale')) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" wire:click="openEditItemModal({{ $index }})" class="text-[#3F5189] hover:text-[#2F3F6F] mr-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </button>
                                            @unless($amountsLocked)
                                                <button type="button" wire:click="removeItem({{ $index }})" wire:confirm="{{ __('Remove this item?') }}" class="text-red-500 hover:text-red-700">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white text-right">{{ __('Total:') }}</td>
                                    <td class="px-4 py-3 text-lg font-bold text-slate-900 dark:text-white text-right">{{ Number::currency($expense_total_amount, config('app.currency'), config('app.locale')) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No items') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Add at least one item to this expense.') }}</p>
                        @unless($amountsLocked)
                            <div class="mt-4">
                                <x-ui.button type="button" variant="primary" icon="plus" wire:click="openAddItemModal">
                                    {{ __('Add Item') }}
                                </x-ui.button>
                            </div>
                        @endunless
                    </div>
                @endif
            </div>
        </div>

        <!-- Payment Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Payment Information') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $amountsLocked
                        ? __('The payment terms are fixed on this expense. Use the payment tools on the expense list to record or reverse a payment.')
                        : __('Payment method and schedule') }}
                </p>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Method') }}</label>
                        <select
                            @disabled($amountsLocked) wire:model="expense_payment_method"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
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
                            <input @disabled($amountsLocked) type="checkbox" wire:model="expense_is_auto_payment" class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                            <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">{{ __('Auto Payment') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Installments Toggle -->
                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-lg">
                    <div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Split into installments') }}</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Enable to divide this expense into multiple payments') }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="$toggle('expense_has_installments')"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $expense_has_installments ? 'bg-[#3F5189]' : 'bg-slate-200' }}">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $expense_has_installments ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>

                @if(!$expense_has_installments)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Status') }}</label>
                            <select
                                @disabled($amountsLocked) wire:model.live="expense_status"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="paid">{{ __('Paid') }}</option>
                                <option value="unpaid">{{ __('Unpaid') }}</option>
                            </select>
                        </div>
                        @if($expense_status === 'paid')
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Paid Date') }}</label>
                                <input @disabled($amountsLocked) type="date" wire:model="expense_paid_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Payment Due Date') }} <span class="text-red-500">*</span></label>
                                <input @disabled($amountsLocked) type="date" wire:model="expense_payment_due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('expense_payment_due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Number of Installments') }} <span class="text-red-500">*</span></label>
                            <input @disabled($amountsLocked) type="number" min="2" max="120" wire:model="expense_total_installments" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('expense_total_installments') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Frequency') }} <span class="text-red-500">*</span></label>
                            <select @disabled($amountsLocked) wire:model="expense_payment_frequency" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="weekly">{{ __('Weekly') }}</option>
                                <option value="biweekly">{{ __('Biweekly') }}</option>
                                <option value="monthly">{{ __('Monthly') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('First Payment Date') }} <span class="text-red-500">*</span></label>
                            <input @disabled($amountsLocked) type="date" wire:model="expense_payment_due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('expense_payment_due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif
            </div>
        </div>
