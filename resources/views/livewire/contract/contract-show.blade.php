<div>
    {{-- Breadcrumbs --}}
    @php
        $breadcrumbs = [
            ['label' => __('Projects'), 'url' => route('projects.index')],
            ['label' => $contract->project->project_name, 'url' => route('projects.overview', $contract->project)],
        ];

        if ($contract->jobSite) {
            $breadcrumbs[] = ['label' => __('Job Sites'), 'url' => route('projects.jobsites', $contract->project)];
            $breadcrumbs[] = ['label' => $contract->jobSite->job_site_name, 'url' => route('jobsites.overview', $contract->jobSite)];
            $breadcrumbs[] = ['label' => __('Contracts'), 'url' => route('jobsites.contracts', $contract->jobSite)];
        } else {
            $breadcrumbs[] = ['label' => __('Contracts'), 'url' => route('projects.contracts', $contract->project)];
        }

        $breadcrumbs[] = ['label' => 'Contract ' . $contract->contract_number];
    @endphp
    <x-ui.breadcrumb :items="$breadcrumbs" />

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        Contract {{ $contract->contract_number }}
                    </h1>
                    @php
                        $statusColors = [
                            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                            'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
                            'partially_paid' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                            'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400',
                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                        ];
                        $statusLabels = [
                            'active' => 'Active',
                            'completed' => 'Completed',
                            'partially_paid' => 'Partially Paid',
                            'paid' => 'Paid',
                            'cancelled' => 'Cancelled',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$contract->status] ?? '' }}">
                        {{ $statusLabels[$contract->status] ?? ucfirst($contract->status) }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $contract->project->project_name }}
                    @if($contract->jobSite)
                        / {{ $contract->jobSite->job_site_name }}
                    @else
                        / Project Level
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ $contract->job_site_id ? route('jobsites.contracts', $contract->job_site_id) : route('projects.contracts', $contract->project_id) }}"
                    icon="arrow-left">
                    Back to List
                </x-ui.button>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('contracts.edit', $contract->id) }}"
                    icon="edit">
                    Edit
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Contract Details Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contract Details</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Contract #</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->contract_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Subcontractor</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->subcontractor?->company_name ?? 'Not specified' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Contact</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                {{ $contract->subcontractorEmployee?->name ?? 'Not specified' }}@if($contract->subcontractorEmployee?->title) ({{ $contract->subcontractorEmployee->title }})@endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Location</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                @if($contract->isProjectLevel())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400">
                                        Project (General)
                                    </span>
                                @else
                                    {{ $contract->jobSite->job_site_name }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Created By</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->createdBy?->name ?? 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Start Date</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->start_date->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">End Date</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $contract->end_date?->format('M d, Y') ?? 'Not set' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Financial Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Financial</h3>
                </div>
                <div class="p-6">
                    @php
                        $changeOrdersPositive = $contract->changeOrders->filter(fn($co) => $co->getRawOriginal('amount') > 0)->sum(fn($co) => $co->getRawOriginal('amount')) / 100;
                        $changeOrdersNegative = $contract->changeOrders->filter(fn($co) => $co->getRawOriginal('amount') < 0)->sum(fn($co) => $co->getRawOriginal('amount')) / 100;
                        $hasChangeOrders = $changeOrdersPositive != 0 || $changeOrdersNegative != 0;
                        $adjustedAmount = $contract->getAdjustedAmount();
                        $balanceDue = $contract->getBalanceDue();
                    @endphp
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Original Amount</dt>
                            <dd class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                                {{ Number::currency($contract->amount, config('app.currency'), config('app.locale')) }}
                            </dd>
                        </div>
                        @if($changeOrdersPositive != 0)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Change Orders</dt>
                                <dd class="mt-1 text-lg font-semibold text-green-600 dark:text-green-400">
                                    +{{ Number::currency($changeOrdersPositive, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                        @if($changeOrdersNegative != 0)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Deductions</dt>
                                <dd class="mt-1 text-lg font-semibold text-red-600 dark:text-red-400">
                                    {{ Number::currency($changeOrdersNegative, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                        @if($hasChangeOrders)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Adjusted Amount</dt>
                                <dd class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                                    {{ Number::currency($adjustedAmount, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                        @if($contract->payments->count() > 0)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Amount Paid</dt>
                                <dd class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">
                                    {{ Number::currency($contract->getAmountPaid(), config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Balance Due</dt>
                                <dd class="mt-1 text-xl font-semibold {{ $balanceDue > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ Number::currency($balanceDue, config('app.currency'), config('app.locale')) }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Change Orders -->
            <livewire:contract.contract-change-orders :contract="$contract" />

            <!-- Notes Card -->
            @if($contract->notes)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Notes</h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-slate-900 dark:text-white whitespace-pre-wrap">{{ $contract->notes }}</p>
                    </div>
                </div>
            @endif

            <!-- Contract File Card -->
            @if($contract->contract_file_path)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contract File</h3>
                    </div>
                    <div class="p-6">
                        <a href="{{ route('files.show', ['path' => $contract->contract_file_path]) }}" target="_blank" class="inline-flex items-center text-sm text-[#3F5189] hover:text-[#2F3F6F]">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            View Document
                        </a>
                        <span class="mx-2 text-slate-300">|</span>
                        <a href="{{ route('files.download', ['path' => $contract->contract_file_path]) }}" class="inline-flex items-center text-sm text-[#3F5189] hover:text-[#2F3F6F]">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Actions Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Actions</h3>
                </div>
                <div class="p-6 space-y-3">
                    @if(count($this->availableStatuses) > 0)
                        <x-ui.button
                            variant="primary"
                            class="w-full justify-center"
                            wire:click="openStatusModal"
                            icon="refresh">
                            Change Status
                        </x-ui.button>
                    @endif

                    @if(in_array($contract->status, ['active', 'completed', 'partially_paid']))
                        <x-ui.button
                            variant="success"
                            class="w-full justify-center"
                            wire:click="openPaymentModal"
                            icon="plus">
                            Record Payment
                        </x-ui.button>
                    @endif

                    <x-ui.button
                        variant="secondary"
                        href="{{ route('contracts.edit', $contract->id) }}"
                        class="w-full justify-center"
                        icon="edit">
                        Edit Contract
                    </x-ui.button>

                    <x-ui.button
                        variant="danger"
                        class="w-full justify-center"
                        wire:click="delete"
                        wire:confirm="Are you sure you want to delete this contract? This action cannot be undone."
                        icon="trash">
                        Delete Contract
                    </x-ui.button>
                </div>
            </div>

            <!-- Status History Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Status History</h3>
                </div>
                <div class="p-6">
                    @if($contract->statusHistories->count() > 0)
                        <div class="flow-root">
                            <ul class="-mb-8">
                                @foreach($contract->statusHistories->sortByDesc('created_at') as $history)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    @php
                                                        $historyStatusColors = [
                                                            'active' => 'bg-green-500',
                                                            'completed' => 'bg-blue-500',
                                                            'partially_paid' => 'bg-yellow-500',
                                                            'paid' => 'bg-emerald-500',
                                                            'cancelled' => 'bg-red-500',
                                                        ];
                                                    @endphp
                                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-slate-800 {{ $historyStatusColors[$history->new_status] ?? 'bg-slate-400' }}">
                                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            @switch($history->new_status)
                                                                @case('active')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                    @break
                                                                @case('completed')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                    @break
                                                                @case('paid')
                                                                @case('partially_paid')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    @break
                                                                @case('cancelled')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                    @break
                                                                @default
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            @endswitch
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div>
                                                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                                                            @if($history->old_status)
                                                                {{ $statusLabels[$history->old_status] ?? ucfirst($history->old_status) }}
                                                                &rarr;
                                                            @endif
                                                            {{ $statusLabels[$history->new_status] ?? ucfirst($history->new_status) }}
                                                        </p>
                                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                                            by {{ $history->changedBy?->name ?? 'System' }}
                                                        </p>
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $history->created_at->format('M d, Y H:i') }}
                                                    </div>
                                                    @if($history->reason)
                                                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900 rounded p-2">
                                                            {{ $history->reason }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">No status changes recorded.</p>
                    @endif
                </div>
            </div>

            <!-- Payment History Card -->
            @if($contract->payments->count() > 0)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Payment History</h3>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($contract->payments as $payment)
                            <div class="p-4">
                                <div class="flex items-start justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ Number::currency($payment->amount, config('app.currency'), config('app.locale')) }}
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            {{ $payment->payment_date->format('M d, Y') }} &middot; {{ $payment->getPaymentMethodLabel() }}
                                        </p>
                                        @if($payment->reference_number)
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                Ref: {{ $payment->reference_number }}
                                            </p>
                                        @endif
                                        @if($payment->notes)
                                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                                                {{ $payment->notes }}
                                            </p>
                                        @endif
                                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                            by {{ $payment->createdBy?->name ?? 'Unknown' }}
                                        </p>
                                    </div>
                                    <x-ui.button
                                        variant="danger"
                                        size="sm"
                                        wire:click="deletePayment({{ $payment->id }})"
                                        wire:confirm="Are you sure you want to delete this payment?"
                                        icon="trash">
                                    </x-ui.button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Status Change Modal -->
    @if($showStatusModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeStatusModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Change Contract Status</h2>
                        <button type="button" wire:click="closeStatusModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">New Status</label>
                            <select
                                wire:model="newStatus"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @foreach($this->availableStatuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Reason (Optional)</label>
                            <textarea
                                wire:model="statusReason"
                                rows="3"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Reason for status change..."></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeStatusModal">
                            Cancel
                        </x-ui.button>
                        <x-ui.button type="button" variant="primary" wire:click="changeStatus">
                            Update Status
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Payment Modal -->
    @if($showPaymentModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closePaymentModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Record Payment</h2>
                        <button type="button" wire:click="closePaymentModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Amount *</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-500 sm:text-sm">$</span>
                                </div>
                                <input
                                    type="number"
                                    wire:model="paymentAmount"
                                    step="0.01"
                                    min="0.01"
                                    class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="0.00">
                            </div>
                            @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Payment Method *</label>
                            <select
                                wire:model="paymentMethod"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="check">Check</option>
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="pix">PIX</option>
                                <option value="other">Other</option>
                            </select>
                            @error('paymentMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Payment Date *</label>
                            <input
                                type="date"
                                wire:model="paymentDate"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('paymentDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Reference Number</label>
                            <input
                                type="text"
                                wire:model="paymentReference"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Check #, transaction ID, etc.">
                            @error('paymentReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Notes</label>
                            <textarea
                                wire:model="paymentNotes"
                                rows="2"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Optional notes..."></textarea>
                            @error('paymentNotes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closePaymentModal">
                            Cancel
                        </x-ui.button>
                        <x-ui.button type="button" variant="primary" wire:click="recordPayment">
                            Record Payment
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
