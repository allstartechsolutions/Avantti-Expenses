<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Contract Payments') }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Manage and process subcontractor contract payments in batch') }}</p>
        </div>
    </div>

    <!-- Flash Messages -->
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

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-center">
                <svg class="h-5 w-5 text-red-600 dark:text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="text-red-800 dark:text-red-300">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Pending Balance -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Pending Balance') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Number::currency($this->summary['pending_balance'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Active Contracts -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Active Contracts') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $this->summary['active_count'] }}
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
                        {{ Number::currency($this->summary['paid_this_month'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Total Contract Value -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Total Contract Value') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Number::currency($this->summary['total_value'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <!-- Client Filter -->
            <div class="w-full md:w-48">
                <select
                    wire:model.live="clientFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Clients') }}</option>
                    @foreach($this->clients as $client)
                        <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Project Filter -->
            <div class="w-full md:w-48">
                <select
                    wire:model.live="projectFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Projects') }}</option>
                    @foreach($this->projects as $project)
                        <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Subcontractor Filter -->
            <div class="w-full md:w-48">
                <select
                    wire:model.live="subcontractorFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Subcontractors') }}</option>
                    @foreach($this->subcontractors as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->company_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div class="w-full md:w-44">
                <select
                    wire:model.live="statusFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="completed">{{ __('Completed') }}</option>
                    <option value="partially_paid">{{ __('Partially Paid') }}</option>
                    <option value="paid">{{ __('Paid') }}</option>
                    <option value="cancelled">{{ __('Cancelled') }}</option>
                </select>
            </div>

            <div class="flex-1"></div>

            <!-- Show Paid/Cancelled Toggle -->
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" wire:model.live="showZeroBalance" class="sr-only peer">
                <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#3F5189] dark:peer-focus:ring-[#4A5A96] rounded-full peer dark:bg-slate-600 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-slate-500 peer-checked:bg-[#3F5189]"></div>
                <span class="ms-3 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Show Paid/Cancelled') }}</span>
            </label>
        </div>
    </div>

    <!-- Payment Date Bar -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-center gap-4">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">{{ __('Payment Date') }}</label>
                <input
                    type="date"
                    wire:model="paymentDate"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
            </div>
            <div class="flex-1"></div>
            <x-ui.button
                variant="success"
                icon="save"
                wire:click="processPayments"
                wire:confirm="{{ __('Process all entered payments? This action cannot be undone.') }}">
                {{ __('Process Payments') }}
            </x-ui.button>
        </div>
    </div>

    <!-- Contracts Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Subcontractor') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Project') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Job Site / Lot') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Contract #') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Amount') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Paid') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Balance') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Last Payment') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Pay Today') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Method') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Notes') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($this->contracts as $contract)
                        @php
                            $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
                            $balance = round($contract->amount - $totalPaidDollars, 2);
                            $isPaidOrCancelled = in_array($contract->status, ['paid', 'cancelled']);
                        @endphp
                        <tr class="{{ $isPaidOrCancelled ? 'opacity-50 bg-slate-50 dark:bg-slate-900/30' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50' }}">
                            <!-- Subcontractor -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $contract->subcontractor?->company_name ?? '-' }}
                                </div>
                            </td>
                            <!-- Project -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('projects.overview', $contract->project_id) }}"
                                   class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ $contract->project->project_name }}
                                </a>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $contract->project->client?->company_name }}
                                </div>
                            </td>
                            <!-- Job Site / Lot -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($contract->jobSite)
                                    <a href="{{ route('jobsites.overview', $contract->job_site_id) }}"
                                       class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                        {{ $contract->jobSite->job_site_name }}
                                    </a>
                                @else
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Project General') }}</span>
                                @endif
                            </td>
                            <!-- Contract # -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('contracts.show', $contract->id) }}"
                                   class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ $contract->contract_number }}
                                </a>
                            </td>
                            <!-- Amount -->
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <span class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ Number::currency($contract->amount, config('app.currency'), config('app.locale')) }}
                                </span>
                            </td>
                            <!-- Paid -->
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">
                                    {{ Number::currency($totalPaidDollars, config('app.currency'), config('app.locale')) }}
                                </span>
                            </td>
                            <!-- Balance -->
                            <td class="px-4 py-3 whitespace-nowrap text-right">
                                <span class="text-sm font-medium {{ $balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ Number::currency($balance, config('app.currency'), config('app.locale')) }}
                                </span>
                            </td>
                            <!-- Last Payment -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($contract->latestPayment)
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $contract->latestPayment->payment_date->format('M d, Y') }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ Number::currency($contract->latestPayment->amount, config('app.currency'), config('app.locale')) }}
                                    </div>
                                @else
                                    <span class="text-sm text-slate-400 dark:text-slate-500">-</span>
                                @endif
                            </td>
                            <!-- Pay Today -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if(!$isPaidOrCancelled && $balance > 0)
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="{{ $balance }}"
                                        wire:model.blur="payAmounts.{{ $contract->id }}"
                                        placeholder="0.00"
                                        class="w-28 px-2 py-1.5 text-sm text-right border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @endif
                            </td>
                            <!-- Method -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if(!$isPaidOrCancelled && $balance > 0)
                                    <select
                                        wire:model="payMethods.{{ $contract->id }}"
                                        class="w-32 px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        <option value="">{{ __('Select...') }}</option>
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
                                @endif
                            </td>
                            <!-- Notes -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if(!$isPaidOrCancelled && $balance > 0)
                                    <input
                                        type="text"
                                        wire:model.blur="payNotes.{{ $contract->id }}"
                                        placeholder="{{ __('Notes...') }}"
                                        class="w-36 px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No contracts found') }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No contracts match the current filters. Try adjusting your filters or toggle "Show Paid/Cancelled".') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
