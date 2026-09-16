{{--
    The expense view modal, shared by the project expenses screen and the
    company expenses screen. Expects the properties of
    App\Livewire\Concerns\HandlesExpensePaymentActions on the component.
    A project row shows its location and cost codes; a company row shows its
    category instead. Every guard asks the row which area it answers to.
--}}
    <x-ui.modal name="expense-view-modal" maxWidth="4xl">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">{{ __('Expense Details') }}</h2>

            @if($viewingExpense)
                <div class="space-y-4">
                    <!-- Header Info -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Date') }}</label>
                            <p class="text-slate-900 dark:text-white">{{ $expense_date ? \Carbon\Carbon::parse($expense_date)->appDate() : '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Supplier') }}</label>
                            <p class="text-slate-900 dark:text-white">{{ $supplierSearch ?: __('No Supplier') }}</p>
                        </div>
                        @if($viewingExpense->isCompanyLevel())
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Category') }}</label>
                                <p class="text-slate-900 dark:text-white">
                                    @if($viewingExpense->category)
                                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $viewingExpense->category->account_code }}</span>
                                        {{ __($viewingExpense->category->name) }}
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">{{ __('Uncategorised') }}</span>
                                    @endif
                                </p>
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Location') }}</label>
                                <p class="text-slate-900 dark:text-white">
                                    @if($viewingExpense->jobSite)
                                        {{ $viewingExpense->jobSite->job_site_name }}
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">{{ __('Project (General)') }}</span>
                                    @endif
                                </p>
                            </div>
                        @endif
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
                                            @unless($viewingExpense->isCompanyLevel())
                                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Cost Code') }}</th>
                                            @endunless
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Item') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Qty') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Unit Price') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                        @foreach($expenseItems as $item)
                                            <tr wire:key="view-item-{{ $item['id'] }}">
                                                @unless($viewingExpense->isCompanyLevel())
                                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                        {{ $item['cost_code'] ?? __('Unassigned') }}
                                                    </td>
                                                @endunless
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
                                    {{ \App\Models\Expense::statusLabel($expense_status) }}
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Payment Method') }}</label>
                                <p class="text-slate-900 dark:text-white">
                                    {{ \App\Models\Expense::paymentMethodLabel($expense_payment_method) ?? __('Not specified') }}
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
                                                <tr wire:key="view-payment-{{ $payment->id }}">
                                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">{{ $payment->payment_number }}</td>
                                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                        @if($editDueDateId === $payment->id)
                                                            <div class="flex items-center gap-1">
                                                                <x-ui.date-input wire:model="editDueDate" class="px-2 py-1 text-xs border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                                                                <button wire:click="confirmEditDueDate" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300" title="{{ __('Confirm') }}">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                                </button>
                                                                <button wire:click="cancelEditDueDate" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300" title="{{ __('Cancel') }}">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                                </button>
                                                            </div>
                                                        @else
                                                            {{ $payment->due_date->appDate() }}
                                                            @if($payment->status !== 'paid' && auth()->user()->can($viewingExpense->ability('edit'), $viewingExpense))
                                                                <button wire:click="startEditDueDate({{ $payment->id }})" class="ml-1 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6] align-middle" title="{{ __('Change due date') }}">
                                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                                </button>
                                                            @endif
                                                        @endif
                                                    </td>
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
                                                            {{ $payment->getStatusLabel() }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                        {{ $payment->paid_date ? $payment->paid_date->appDate() : '-' }}
                                                        @if($payment->status === 'paid' && $payment->paidBy)
                                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('by') }} {{ $payment->paidBy->name }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2 text-right">
                                                        @if($markPaidType === 'payment' && $markPaidId === $payment->id)
                                                            <div class="flex items-center justify-end gap-2">
                                                                <x-ui.date-input wire:model="markPaidDate" class="px-2 py-1 text-xs border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                                                                <button wire:click="confirmMarkPaid" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">{{ __('Confirm') }}</button>
                                                                <button wire:click="cancelMarkPaid" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300 text-sm font-medium">{{ __('Cancel') }}</button>
                                                            </div>
                                                        @elseif($payment->status === 'pending')
                                                            @can($viewingExpense->ability('pay'), $viewingExpense)
                                                                <button wire:click="startMarkPaid('payment', {{ $payment->id }})" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">{{ __('Mark Paid') }}</button>
                                                                <button wire:click="markPaymentAsOverdue({{ $payment->id }})" class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium">{{ __('Overdue') }}</button>
                                                            @endcan
                                                        @elseif($payment->status === 'overdue')
                                                            @can($viewingExpense->ability('pay'), $viewingExpense)
                                                                <button wire:click="startMarkPaid('payment', {{ $payment->id }})" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">{{ __('Mark Paid') }}</button>
                                                            @endcan
                                                        @elseif($payment->status === 'paid')
                                                            @can($viewingExpense->ability('edit_paid'), $viewingExpense)
                                                                <button wire:click="unmarkPaymentPaid({{ $payment->id }})" wire:confirm="{{ __('Revert this payment to pending?') }}" class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 text-sm font-medium">{{ __('Revert') }}</button>
                                                            @endcan
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
                                            {{ \Carbon\Carbon::parse($expense_paid_date)->appDate() }}
                                            @if($viewingExpense?->paidBy)
                                                <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('by') }} {{ $viewingExpense->paidBy->name }}</span>
                                            @endif
                                        </p>
                                    </div>
                                @elseif($expense_status === 'unpaid' && $expense_payment_due_date)
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Due Date') }}</label>
                                        <p class="text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($expense_payment_due_date)->appDate() }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs text-slate-500 dark:text-slate-400">
                        <div>
                            <span class="block font-medium text-slate-700 dark:text-slate-300">{{ __('Created by') }}</span>
                            {{ $viewingExpense->createdBy?->name ?? '—' }}
                        </div>
                        <div>
                            <span class="block font-medium text-slate-700 dark:text-slate-300">{{ __('Created at') }}</span>
                            {{ $viewingExpense->created_at?->appDateTime() ?? '—' }}
                        </div>
                        <div>
                            <span class="block font-medium text-slate-700 dark:text-slate-300">{{ __('Last updated') }}</span>
                            {{ $viewingExpense->updated_at?->appDateTime() ?? '—' }}
                        </div>
                        <div>
                            <span class="block font-medium text-slate-700 dark:text-slate-300">{{ __('Payment terms') }}</span>
                            {{ $viewingExpense->getPaymentLabel() }}
                        </div>
                    </div>

                    <div class="mt-6 border-t border-slate-200 dark:border-slate-700 pt-4">
                        <livewire:shared.attachments
                            model-type="expense"
                            :model-id="$viewingExpense->id"
                            :key="'expense-attachments-'.$viewingExpense->id" />
                    </div>

                    @include('livewire.project.partials.expense-history')
                </div>
            @endif

            <div class="flex items-center justify-end space-x-4 mt-6">
                <x-ui.button type="button" variant="secondary" wire:click="closeExpenseModal">{{ __('Close') }}</x-ui.button>
            </div>
        </div>
    </x-ui.modal>
