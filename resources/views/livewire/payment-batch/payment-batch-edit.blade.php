<div>
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $paymentBatch->name }}</h1>
                @php
                    $headerColor = match($paymentBatch->getStatusColor()) {
                        'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-700 dark:text-amber-300',
                        'green' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300',
                        'red' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300',
                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $headerColor }}">
                    {{ __($paymentBatch->getStatusLabel()) }}
                </span>
            </div>
            <p class="text-slate-500 dark:text-slate-400 mt-1">{{ __('Edit batch items and approve payments') }}</p>
        </div>
        <div class="flex items-center gap-3 mt-4 md:mt-0">
            <x-ui.button variant="secondary" href="{{ route('payment-batches.index') }}" icon="arrow-left">
                {{ __('Back') }}
            </x-ui.button>
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

    <!-- Editable Batch Fields -->
    @if($paymentBatch->canBeEdited())
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Batch Name') }}</label>
                    <input type="text" wire:model="name"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Payment Date') }}</label>
                    <x-ui.date-input wire:model="payment_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm" />
                    @error('payment_date') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
                    <input type="text" wire:model="notes" placeholder="{{ __('Optional notes...') }}"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                </div>
            </div>
        </div>
    @endif

    <!-- Batch Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Items -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Total Items') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $this->batchSummary['total_items'] }}</p>
                </div>
            </div>
        </div>

        <!-- Total Amount -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Total Amount') }}</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ Number::currency($this->batchSummary['total_amount'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Pending -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Pending') }} ({{ $this->batchSummary['pending_count'] }})</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {{ Number::currency($this->batchSummary['pending_amount'], config('app.currency'), config('app.locale')) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Approved -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Approved') }} ({{ $this->batchSummary['approved_count'] }})</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ Number::currency($this->batchSummary['approved_amount'], config('app.currency'), config('app.locale')) }}
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
                <select wire:model.live="clientFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Clients') }}</option>
                    @foreach($this->clients as $client)
                        <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Project Filter -->
            <div class="w-full md:w-48">
                <select wire:model.live="projectFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Projects') }}</option>
                    @foreach($this->projects as $project)
                        <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Subcontractor Filter -->
            <div class="w-full md:w-48">
                <select wire:model.live="subcontractorFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Subcontractors') }}</option>
                    @foreach($this->subcontractors as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->company_name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Project Manager Filter -->
            <div class="w-full md:w-48">
                <select wire:model.live="projectManagerFilter"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                    <option value="">{{ __('All Project Managers') }}</option>
                    @foreach($this->projectManagers as $pm)
                        <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div class="w-full md:w-44">
                <select wire:model.live="statusFilter"
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

    <!-- Action Bar -->
    @if($paymentBatch->canBeEdited())
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex items-center gap-3">
                    <x-ui.button variant="primary" wire:click="saveDraft" icon="save">
                        {{ __('Save Draft') }}
                    </x-ui.button>

                    @if($this->batchSummary['pending_count'] > 0)
                        <x-ui.button variant="success" wire:click="approveAll"
                            wire:confirm="{{ __('Approve all :count pending items and process payments? This cannot be undone.', ['count' => $this->batchSummary['pending_count']]) }}"
                            icon="check">
                            {{ __('Approve All Pending') }} ({{ $this->batchSummary['pending_count'] }})
                        </x-ui.button>
                    @endif
                </div>

                <div class="flex-1"></div>

                @if($this->batchSummary['approved_count'] === 0)
                    <x-ui.button variant="danger" wire:click="cancelBatch"
                        wire:confirm="{{ __('Are you sure you want to cancel this batch? This cannot be undone.') }}"
                        icon="x">
                        {{ __('Cancel Batch') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    @endif

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
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Batch Amount') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Pays') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Method') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Phase') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Notes') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Item Status') }}
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($contracts as $contract)
                        @php
                            $totalPaidDollars = ($contract->total_paid_cents ?? 0) / 100;
                            $changeOrdersTotal = ($contract->change_orders_total_cents ?? 0) / 100;
                            $balance = round($contract->amount + $changeOrdersTotal - $totalPaidDollars, 2);
                            $isPaidOrCancelled = in_array($contract->status, ['paid', 'cancelled']);
                            $batchItem = $batchItems[$contract->id] ?? null;
                            $isApproved = $batchItem && $batchItem->status === 'approved';
                            $isRejected = $batchItem && $batchItem->status === 'rejected';
                            $isPending = $batchItem && $batchItem->status === 'pending';
                        @endphp
                        <tr wire:key="contract-row-{{ $contract->id }}" class="{{ $isPaidOrCancelled ? 'opacity-50 bg-slate-50 dark:bg-slate-900/30' : ($isApproved ? 'bg-green-50/50 dark:bg-green-900/10' : ($isRejected ? 'bg-red-50/50 dark:bg-red-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50')) }}">
                            <!-- Subcontractor -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900 dark:text-white {{ $isRejected ? 'line-through' : '' }}">
                                    {{ $contract->subcontractor?->company_name ?? '-' }}
                                </div>
                            </td>
                            <!-- Project -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900 dark:text-white {{ $isRejected ? 'line-through' : '' }}">
                                    {{ $contract->project->project_name }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $contract->project->client?->company_name }}
                                </div>
                            </td>
                            <!-- Job Site -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($contract->jobSite)
                                    <span class="text-sm text-slate-900 dark:text-white {{ $isRejected ? 'line-through' : '' }}">
                                        {{ $contract->jobSite->job_site_name }}
                                    </span>
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
                                    {{ Number::currency($contract->amount + $changeOrdersTotal, config('app.currency'), config('app.locale')) }}
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
                            <!-- Batch Amount -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($isApproved)
                                    <div class="text-sm font-medium text-green-600 dark:text-green-400 text-center">
                                        {{ $batchItem->getRawOriginal('amount') ? Number::currency($batchItem->amount, config('app.currency'), config('app.locale')) : '—' }}
                                    </div>
                                @elseif($isRejected)
                                    <div class="text-sm font-medium text-red-400 dark:text-red-500 text-center line-through">
                                        {{ $batchItem->getRawOriginal('amount') ? Number::currency($batchItem->amount, config('app.currency'), config('app.locale')) : '—' }}
                                    </div>
                                @elseif(!$isPaidOrCancelled && $balance > 0 && $paymentBatch->canBeEdited() && ! $this->batchBlockReason($contract))
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="{{ $balance }}"
                                        wire:model="payAmounts.{{ $contract->id }}"
                                        placeholder="0.00"
                                        class="w-28 px-2 py-1.5 text-sm text-right border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @endif
                            </td>
                            <!-- Pays (installment / measurement) -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $editableRow = $paymentBatch->canBeEdited() && !$isApproved && !$isRejected;
                                    $targets = $editableRow ? $this->payableTargetsFor($contract) : [];
                                    $blockReason = $editableRow ? $this->batchBlockReason($contract) : null;
                                    $runsOnSchedule = $contract->scheduleItems->isNotEmpty() || $contract->measurements->isNotEmpty();
                                @endphp
                                @if($isApproved || $isRejected)
                                    <div class="text-xs text-slate-600 dark:text-slate-400 text-center {{ $isRejected ? 'line-through' : '' }}">
                                        {{ $batchItem?->scheduleItem?->description
                                            ?? ($batchItem?->measurement ? __('Measurement').' #'.$batchItem->measurement->measurement_number : '—') }}
                                    </div>
                                @elseif(!$isPaidOrCancelled && $balance > 0 && $paymentBatch->canBeEdited())
                                    @if($blockReason)
                                        <div class="text-xs text-amber-600 dark:text-amber-400 text-center">{{ $blockReason }}</div>
                                    @elseif($runsOnSchedule)
                                        <select
                                            wire:model.live="payTargets.{{ $contract->id }}"
                                            class="w-52 px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                            <option value="">{{ $contract->getUnscheduledRemaining() > 0.009 ? __('Unscheduled balance') : __('Select...') }}</option>
                                            @foreach($targets as $targetKey => $target)
                                                <option value="{{ $targetKey }}">
                                                    {{ $target['label'] }}@unless($target['stale'] ?? false) · {{ Number::currency($target['amount'], config('app.currency'), config('app.locale')) }}@endunless
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <div class="text-xs text-slate-400 dark:text-slate-500 text-center">—</div>
                                    @endif
                                @endif
                            </td>

                            <!-- Method -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($isApproved || $isRejected)
                                    <div class="text-sm text-slate-600 dark:text-slate-400 text-center {{ $isRejected ? 'line-through' : '' }}">
                                        {{ __($batchItem->getPaymentMethodLabel()) }}
                                    </div>
                                @elseif(!$isPaidOrCancelled && $balance > 0 && $paymentBatch->canBeEdited())
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
                            <!-- Phase -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($isApproved || $isRejected)
                                    <div class="text-sm text-slate-600 dark:text-slate-400 text-center {{ $isRejected ? 'line-through' : '' }}">
                                        {{ $batchItem->phase ?? '-' }}
                                    </div>
                                @elseif(!$isPaidOrCancelled && $balance > 0 && $paymentBatch->canBeEdited())
                                    <input
                                        type="text"
                                        wire:model="payPhases.{{ $contract->id }}"
                                        placeholder="{{ __('Phase...') }}"
                                        class="w-28 px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @endif
                            </td>
                            <!-- Notes -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($isApproved || $isRejected)
                                    <div class="text-sm text-slate-600 dark:text-slate-400 text-center max-w-[140px] truncate {{ $isRejected ? 'line-through' : '' }}" title="{{ $batchItem->notes }}">
                                        {{ $batchItem->notes ?? '-' }}
                                    </div>
                                @elseif(!$isPaidOrCancelled && $balance > 0 && $paymentBatch->canBeEdited())
                                    <input
                                        type="text"
                                        wire:model="payNotes.{{ $contract->id }}"
                                        placeholder="{{ __('Notes...') }}"
                                        class="w-36 px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @endif
                            </td>
                            <!-- Item Status -->
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                @if($batchItem)
                                    @php
                                        $itemColor = match($batchItem->getStatusColor()) {
                                            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-700 dark:text-amber-300',
                                            'green' => 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300',
                                            'red' => 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300',
                                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $itemColor }}">
                                        {{ __($batchItem->getStatusLabel()) }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                @endif
                            </td>
                            <!-- Actions -->
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                @if($isPending && $paymentBatch->canBeEdited())
                                    <div class="flex items-center justify-center gap-1">
                                        <button
                                            wire:click="approveItem({{ $batchItem->id }})"
                                            wire:confirm="{{ __('Approve this payment and process it immediately?') }}"
                                            class="p-1.5 text-green-600 hover:bg-green-100 dark:hover:bg-green-900/30 rounded-lg transition-colors"
                                            title="{{ __('Approve') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </button>
                                        <button
                                            wire:click="rejectItem({{ $batchItem->id }})"
                                            wire:confirm="{{ __('Reject this item?') }}"
                                            class="p-1.5 text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                            title="{{ __('Reject') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @elseif($isApproved)
                                    <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @elseif($isRejected)
                                    <svg class="w-5 h-5 text-red-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-6 py-12 text-center">
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

        <!-- Pagination -->
        @if($contracts->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $contracts->links() }}
            </div>
        @endif
    </div>
</div>
