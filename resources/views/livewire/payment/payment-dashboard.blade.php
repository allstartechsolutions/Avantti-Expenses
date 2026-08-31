<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Payments Dashboard') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Track and manage upcoming and overdue payments') }}</p>
        </div>
    </div>

    <!-- Flash Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <div class="flex items-center">
                <svg class="h-5 w-5 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-green-800 dark:text-green-300">{{ session('message') }}</span>
            </div>
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Pending -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Total Pending') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Number::currency($summary['pending'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Overdue -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Overdue') }}</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                        {{ Number::currency($summary['overdue'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Due This Month -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Due This Month') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Number::currency($summary['this_month'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Paid This Month -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Paid This Month') }}</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ Number::currency($summary['paid_this_month'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <!-- View Mode Tabs -->
            <div class="flex rounded-lg bg-slate-100 dark:bg-slate-900 p-1">
                <button
                    wire:click="$set('viewMode', 'upcoming')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $viewMode === 'upcoming' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    {{ __('Upcoming') }}
                </button>
                <button
                    wire:click="$set('viewMode', 'overdue')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $viewMode === 'overdue' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    {{ __('Overdue') }}
                </button>
                <button
                    wire:click="$set('viewMode', 'all')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $viewMode === 'all' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    {{ __('All Pending') }}
                </button>
            </div>

            <div class="flex-1"></div>

            <!-- Project Filter -->
            <div class="w-full md:w-48">
                <select
                    wire:model.live="projectFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Projects') }}</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range -->
            <div class="flex items-center gap-2">
                <x-ui.date-input wire:model.live="dateFrom" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm" />
                <span class="text-slate-500 dark:text-slate-400">{{ __('to') }}</span>
                <x-ui.date-input wire:model.live="dateTo" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm" />
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Description') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Project / Job Site') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Payment') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Due Date') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Amount') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 {{ $payment['is_overdue'] ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $payment['description'] }}
                                </div>
                                @if($payment['payment_method'])
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ __(ucwords(str_replace('_', ' ', $payment['payment_method']))) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-900 dark:text-white" title="{{ $payment['project'] }}">{{ Str::limit($payment['project'], 30) }}</div>
                                @if($payment['job_site'])
                                    <div class="text-xs text-slate-500 dark:text-slate-400" title="{{ $payment['job_site'] }}">{{ Str::limit($payment['job_site'], 30) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200">
                                    {{ $payment['payment_label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm {{ $payment['is_overdue'] ? 'text-red-600 dark:text-red-400 font-medium' : 'text-slate-900 dark:text-white' }}">
                                    {{ $payment['due_date'] ? $payment['due_date']->appDate() : '-' }}
                                </div>
                                @if($payment['is_overdue'] && $payment['due_date'])
                                    <div class="text-xs text-red-600 dark:text-red-400">
                                        {{ $payment['due_date']->diffForHumans() }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <span class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ Number::currency($payment['amount'], config('app.currency'), config('app.locale')) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($payment['status'] === 'overdue' || $payment['is_overdue'])
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                        {{ __('Overdue') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300">
                                        {{ __('Pending') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <button
                                        wire:click="openPayModal({{ $payment['id'] }}, '{{ $payment['type'] }}')"
                                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        {{ __('Pay') }}
                                    </button>
                                    @if(!$payment['is_overdue'] && $payment['status'] !== 'overdue')
                                        <button
                                            wire:click="markAsOverdue({{ $payment['id'] }}, '{{ $payment['type'] }}')"
                                            wire:confirm="{{ __('Mark this payment as overdue?') }}"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ __('Overdue') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No payments found') }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    @if($viewMode === 'upcoming')
                                        {{ __('No upcoming payments in the selected date range.') }}
                                    @elseif($viewMode === 'overdue')
                                        {{ __('Great! No overdue payments.') }}
                                    @else
                                        {{ __('No pending payments found.') }}
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pay Modal -->
    <x-ui.modal name="pay-modal" maxWidth="md">
        <div class="p-6">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/30 mb-4">
                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-slate-900 dark:text-white text-center mb-4">
                {{ __('Mark Payment as Paid') }}
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Payment Method') }}
                    </label>
                    <select
                        wire:model="paymentMethod"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
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
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Paid Date') }}
                    </label>
                    <x-ui.date-input wire:model="paidDate" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end space-x-3">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    x-on:click="$dispatch('close-modal', 'pay-modal')">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button
                    type="button"
                    variant="success"
                    wire:click="confirmPayment">
                    {{ __('Confirm Payment') }}
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
