<x-jobsite-layout :jobSite="$jobSite" active="overview" :title="__('Job Site Details')">
    <x-slot:actions>
        @can('projects.delete', $jobSite)
            <x-ui.button
                variant="danger"
                wire:click="confirmDeleteJobSite"
                icon="trash">
                {{ __('Delete') }}
            </x-ui.button>
        @endcan
    </x-slot:actions>

    <!-- Summary Cards -->
    @php
        $totalContractValue = $jobSite->job_amount + $totalChangeOrdersAmount;
        $profitLoss = $totalContractValue - $totalExpensesAmount - $totalContractsAdjusted;
        $isProfit = $profitLoss >= 0;
        $cardBgClass = $isProfit ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-orange-500 to-orange-600';
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
        <!-- Total Contract Value Card -->
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Total Contract Value') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency($totalContractValue, config('app.currency'), config('app.locale')) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-sm text-white/80">{{ __('Job Amount') }} + {{ $changeOrders->count() }} {{ Str::plural('change order', $changeOrders->count()) }}</p>
        </div>

        <!-- Total Expenses Card -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Total Expenses') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency($totalExpensesAmount, config('app.currency'), config('app.locale')) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-sm text-white/80">{{ $expenses->count() }} {{ Str::plural('expense', $expenses->count()) }} {{ __('recorded') }}</p>
        </div>

        <!-- Contracts Card -->
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Contracts') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency($totalContractsAdjusted, config('app.currency'), config('app.locale')) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-sm text-white/90">
                <span>{{ __('Paid') }}: <span class="font-semibold">{{ Number::currency($totalContractsPaid, config('app.currency'), config('app.locale')) }}</span></span>
                <span>{{ __('Unpaid') }}: <span class="font-semibold">{{ Number::currency($totalContractsUnpaid, config('app.currency'), config('app.locale')) }}</span></span>
            </div>
        </div>

        <!-- Profit & Loss Card -->
        <div class="{{ $cardBgClass }} rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ $isProfit ? __('Profit') : __('Loss') }}</p>
                    <p class="text-3xl font-bold mt-1">{{ Number::currency(abs($profitLoss), config('app.currency'), config('app.locale')) }}</p>
                </div>
                <div class="bg-white/10 rounded-full p-4">
                    @if($isProfit)
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    @else
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                        </svg>
                    @endif
                </div>
            </div>
            <p class="mt-4 text-sm text-white/80">{{ __('Contract Value − Expenses − Contracts') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Job Site Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Job Site Information') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Job Site Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Job Site Name') }}
                            </label>
                            <p class="text-slate-900 dark:text-white font-medium">{{ $jobSite->job_site_name }}</p>
                        </div>

                        <!-- Project -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Project') }}
                            </label>
                            <a href="{{ route('projects.overview', $jobSite->project->id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                {{ $jobSite->project->project_name }}
                            </a>
                        </div>

                        <!-- Job Amount -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Job Amount') }}
                            </label>
                            <p class="text-slate-900 dark:text-white font-medium">{{ Number::currency($jobSite->job_amount, config('app.currency'), config('app.locale')) }}</p>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Status') }}
                            </label>
                            @php
                                $statusColors = [
                                    'created' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
                                    'in_progress' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-300',
                                    'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                    'on_hold' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300',
                                ];
                                $statusColor = $statusColors[$jobSite->status->value] ?? $statusColors['created'];
                            @endphp
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusColor }}">
                                {{ $jobSite->status->label() }}
                            </span>
                        </div>

                        <!-- Created At -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Created On') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $jobSite->created_at->format('F d, Y') }}</p>
                        </div>

                        <!-- Created By -->
                        @if($jobSite->createdBy)
                            <div>
                                <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    {{ __('Created By') }}
                                </label>
                                <p class="text-slate-900 dark:text-white">{{ $jobSite->createdBy->name }}</p>
                            </div>
                        @endif

                        <!-- Supervisor -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Supervisor') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">
                                {{ $jobSite->supervisor?->name ?? __('Not assigned') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contact Information') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Contact Person') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $jobSite->contact_person }}</p>
                        </div>

                        @if($jobSite->phone)
                            <div>
                                <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    {{ __('Phone') }}
                                </label>
                                <p class="text-slate-900 dark:text-white">{{ $jobSite->formatted_phone }}</p>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Email') }}
                            </label>
                            <a href="mailto:{{ $jobSite->email }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                {{ $jobSite->email }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Information Card -->
            @if($jobSite->street || $jobSite->city || $jobSite->state || $jobSite->postal_code)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Address Information') }}</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @if($jobSite->street)
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Street Address') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->street }}</p>
                                </div>
                            @endif

                            @if($jobSite->address_2)
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Address Line 2') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->address_2 }}</p>
                                </div>
                            @endif

                            @if(config('app.country') === 'BR' && $jobSite->neighborhood)
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Neighborhood (Bairro)') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->neighborhood }}</p>
                                </div>
                            @endif

                            @if($jobSite->city)
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('City') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->city }}</p>
                                </div>
                            @endif

                            @if($jobSite->state)
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('State') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->state }}</p>
                                </div>
                            @endif

                            @if($jobSite->postal_code)
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ config('app.country') === 'BR' ? __('CEP') : __('Postal Code') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->postal_code }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Open action items (meetings module) -->
            <x-open-tasks-card :jobSite="$jobSite" />

            <!-- Expenses Summary Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Expenses') }}</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Total Expenses') }}
                            </label>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $expenses->count() }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Total Amount') }}
                            </label>
                            <p class="text-2xl font-bold text-red-600 dark:text-red-500">{{ Number::currency($totalExpensesAmount, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                        <div class="pt-2">
                            <x-ui.button
                                variant="primary"
                                href="{{ route('jobsites.expenses', $jobSite) }}"
                                class="w-full">
                                {{ __('View Expenses') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Orders Summary Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Change Orders') }}</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Total Change Orders') }}
                            </label>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $changeOrders->count() }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Total Amount') }}
                            </label>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-500">{{ Number::currency($totalChangeOrdersAmount, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                        <div class="pt-2">
                            <x-ui.button
                                variant="primary"
                                href="{{ route('jobsites.change-orders', $jobSite) }}"
                                class="w-full">
                                {{ __('View Change Orders') }}
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supervisor History Card -->
            @if($jobSite->supervisorHistories->isNotEmpty())
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Supervisor History') }}</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            @foreach($jobSite->supervisorHistories as $history)
                                <div class="relative pl-4 border-l-2 border-slate-200 dark:border-slate-600">
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">
                                        {{ $history->created_at->format('M d, Y h:i A') }}
                                    </div>
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        @if($history->old_supervisor_id === null && $history->new_supervisor_id !== null)
                                            {{ __('Initial assignment') }}: <span class="font-medium">{{ $history->newSupervisor?->name }}</span>
                                        @elseif($history->new_supervisor_id === null)
                                            <span class="font-medium">{{ $history->oldSupervisor?->name }}</span> &rarr; <span class="text-slate-400 italic">{{ __('Removed') }}</span>
                                        @else
                                            <span class="font-medium">{{ $history->oldSupervisor?->name }}</span> &rarr; <span class="font-medium">{{ $history->newSupervisor?->name }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('by') }} {{ $history->changedBy?->name }}
                                    </div>
                                    @if($history->note)
                                        <div class="mt-1 text-xs text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/50 rounded px-2 py-1">
                                            {{ $history->note }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Delete Job Site Confirmation Modal -->
    @if($showDeleteJobSiteModal)
        <x-ui.modal name="delete-jobsite-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    {{ __('Delete Job Site') }}
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    {{ __('Are you sure you want to delete') }} <strong>{{ $deleteJobSiteData['name'] ?? $jobSite->job_site_name }}</strong>?
                    {{ __('This action') }} <strong>{{ __('cannot be undone') }}</strong>.
                </p>

                @if(!empty($deleteJobSiteData))
                    @php
                        $hasRelatedJobSite = ($deleteJobSiteData['expenses'] ?? 0) > 0
                            || ($deleteJobSiteData['change_orders'] ?? 0) > 0
                            || ($deleteJobSiteData['daily_reports'] ?? 0) > 0
                            || ($deleteJobSiteData['budgets'] ?? 0) > 0;
                    @endphp

                    @if($hasRelatedJobSite)
                        <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                            <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">{{ __('The following data will be permanently deleted:') }}</p>
                            <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                                @if(($deleteJobSiteData['expenses'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['expenses'] }} {{ __('Expense(s)') }}
                                    </li>
                                @endif
                                @if(($deleteJobSiteData['change_orders'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['change_orders'] }} {{ __('Change Order(s)') }}
                                    </li>
                                @endif
                                @if(($deleteJobSiteData['daily_reports'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['daily_reports'] }} {{ __('Daily Report(s)') }}
                                    </li>
                                @endif
                                @if(($deleteJobSiteData['budgets'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['budgets'] }} {{ __('Budget(s)') }}
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif
                @endif

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDeleteJobSite"
                        icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteJobSite"
                        icon="trash">
                        {{ __('Delete Job Site') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</x-jobsite-layout>
