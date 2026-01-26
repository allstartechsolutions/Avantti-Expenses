<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-2 text-sm text-slate-500 dark:text-slate-400 mb-2">
                    <a href="{{ route('projects.index') }}" class="hover:text-[#3F5189] dark:hover:text-[#4A5A96]">Projects</a>
                    <span>/</span>
                    <a href="{{ route('projects.show', $jobSite->project->id) }}" class="hover:text-[#3F5189] dark:hover:text-[#4A5A96]">{{ $jobSite->project->project_name }}</a>
                    <span>/</span>
                    <span class="text-slate-900 dark:text-white">{{ $jobSite->job_site_name }}</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Job Site Details</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $jobSite->job_site_name }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('projects.show', $jobSite->project->id) }}"
                    icon="arrow-left">
                    Back to Project
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-8">
                <button
                    wire:click="setActiveTab('overview')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'overview' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Overview
                </button>
                <button
                    wire:click="setActiveTab('expenses')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'expenses' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Expenses
                </button>
                <button
                    wire:click="setActiveTab('changeorders')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'changeorders' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Change Orders
                </button>
                <button
                    wire:click="setActiveTab('dailyreports')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'dailyreports' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    Daily Reports
                </button>
                <button
                    wire:click="setActiveTab('budget')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'budget' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    Budget
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    <div>
        <!-- Overview Tab -->
        @if($activeTab === 'overview')
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Total Contract Value Card -->
                <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80">Total Contract Value</p>
                            <p class="text-3xl font-bold mt-1">{{ Number::currency($jobSite->job_amount + $totalChangeOrdersAmount, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                        <div class="bg-white/10 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-white/80">Job Amount + {{ $changeOrders->count() }} {{ Str::plural('change order', $changeOrders->count()) }}</p>
                </div>

                <!-- Total Expenses Card -->
                <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80">Total Expenses</p>
                            <p class="text-3xl font-bold mt-1">{{ Number::currency($totalExpensesAmount, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                        <div class="bg-white/10 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-white/80">{{ $expenses->count() }} {{ Str::plural('expense', $expenses->count()) }} recorded</p>
                </div>

                <!-- Profit & Loss Card -->
                @php
                    $totalContractValue = $jobSite->job_amount + $totalChangeOrdersAmount;
                    $profitLoss = $totalContractValue - $totalExpensesAmount;
                    $isProfit = $profitLoss >= 0;
                    $cardBgClass = $isProfit ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-orange-500 to-orange-600';
                @endphp
                <div class="{{ $cardBgClass }} rounded-lg shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80">{{ $isProfit ? 'Profit' : 'Loss' }}</p>
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
                    <p class="mt-4 text-sm text-white/80">{{ $isProfit ? 'Project is profitable' : 'Project over budget' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Job Site Information Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Job Site Information</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Job Site Name -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Job Site Name
                                    </label>
                                    <p class="text-slate-900 dark:text-white font-medium">{{ $jobSite->job_site_name }}</p>
                                </div>

                                <!-- Project -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Project
                                    </label>
                                    <a href="{{ route('projects.show', $jobSite->project->id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                        {{ $jobSite->project->project_name }}
                                    </a>
                                </div>

                                <!-- Job Amount -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Job Amount
                                    </label>
                                    <p class="text-slate-900 dark:text-white font-medium">{{ Number::currency($jobSite->job_amount, config('app.currency'), config('app.locale')) }}</p>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Status
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
                                        Created On
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->created_at->format('F d, Y') }}</p>
                                </div>

                                <!-- Created By -->
                                @if($jobSite->createdBy)
                                    <div>
                                        <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                            Created By
                                        </label>
                                        <p class="text-slate-900 dark:text-white">{{ $jobSite->createdBy->name }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contact Information</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Contact Person
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $jobSite->contact_person }}</p>
                                </div>

                                @if($jobSite->phone)
                                    <div>
                                        <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                            Phone
                                        </label>
                                        <p class="text-slate-900 dark:text-white">{{ $jobSite->phone }}</p>
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Email
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
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Address Information</h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    @if($jobSite->street)
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                                Street Address
                                            </label>
                                            <p class="text-slate-900 dark:text-white">{{ $jobSite->street }}</p>
                                        </div>
                                    @endif

                                    @if($jobSite->address_2)
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                                Address Line 2
                                            </label>
                                            <p class="text-slate-900 dark:text-white">{{ $jobSite->address_2 }}</p>
                                        </div>
                                    @endif

                                    @if(config('app.country') === 'BR' && $jobSite->neighborhood)
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                                Neighborhood (Bairro)
                                            </label>
                                            <p class="text-slate-900 dark:text-white">{{ $jobSite->neighborhood }}</p>
                                        </div>
                                    @endif

                                    @if($jobSite->city)
                                        <div>
                                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                                City
                                            </label>
                                            <p class="text-slate-900 dark:text-white">{{ $jobSite->city }}</p>
                                        </div>
                                    @endif

                                    @if($jobSite->state)
                                        <div>
                                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                                State
                                            </label>
                                            <p class="text-slate-900 dark:text-white">{{ $jobSite->state }}</p>
                                        </div>
                                    @endif

                                    @if($jobSite->postal_code)
                                        <div>
                                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                                {{ config('app.country') === 'BR' ? 'CEP' : 'Postal Code' }}
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
                    <!-- Expenses Summary Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Expenses</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Total Expenses
                                    </label>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $expenses->count() }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Total Amount
                                    </label>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-500">{{ Number::currency($totalExpensesAmount, config('app.currency'), config('app.locale')) }}</p>
                                </div>
                                <div class="pt-2">
                                    <x-ui.button
                                        variant="primary"
                                        wire:click="setActiveTab('expenses')"
                                        class="w-full">
                                        View Expenses
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Change Orders Summary Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Change Orders</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Total Change Orders
                                    </label>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $changeOrders->count() }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Total Amount
                                    </label>
                                    <p class="text-2xl font-bold text-green-600 dark:text-green-500">{{ Number::currency($totalChangeOrdersAmount, config('app.currency'), config('app.locale')) }}</p>
                                </div>
                                <div class="pt-2">
                                    <x-ui.button
                                        variant="primary"
                                        wire:click="setActiveTab('changeorders')"
                                        class="w-full">
                                        View Change Orders
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Expenses Tab -->
        @if($activeTab === 'expenses')
            <div class="space-y-6">
                <!-- Header with Search, Filter and Add Button -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 flex flex-col sm:flex-row gap-4">
                        <!-- Search Bar -->
                        <div class="relative max-w-md">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="expenseSearch"
                                placeholder="Search expenses..."
                                class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <!-- Status Filter -->
                        <select
                            wire:model.live="expenseStatusFilter"
                            class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="all">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <x-ui.button
                        variant="primary"
                        icon="plus"
                        href="{{ route('expenses.jobsite.create', $jobSite) }}">
                        Add Expense
                    </x-ui.button>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Total Expenses -->
                    <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-white/80">Total Expenses</p>
                                <p class="text-2xl font-bold mt-1">{{ Number::currency($totalExpensesAmount, config('app.currency'), config('app.locale')) }}</p>
                            </div>
                            <div class="bg-white/10 rounded-full p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-white/80">{{ $expenses->count() }} {{ Str::plural('expense', $expenses->count()) }}</p>
                    </div>
                    <!-- Paid Amount -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Paid</p>
                                <p class="text-2xl font-bold mt-1 text-green-600 dark:text-green-400">{{ Number::currency($totalPaidAmount, config('app.currency'), config('app.locale')) }}</p>
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
                                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending</p>
                                <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ Number::currency($totalPendingAmount, config('app.currency'), config('app.locale')) }}</p>
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
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Item</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Payments</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($expenses as $expense)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                                {{ $expense->expense_date->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                            {{ $expense->item_name }}
                                                        </div>
                                                        @if($expense->isCustom())
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                                                Custom
                                                            </span>
                                                        @else
                                                            <span class="text-xs text-slate-500 dark:text-slate-400">From Catalog</span>
                                                        @endif
                                                    </div>
                                                </div>
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
                                                    {{ ucfirst($expense->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <button
                                                        wire:click="openExpenseViewModal({{ $expense->id }})"
                                                        class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                        title="View">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </button>
                                                    @if($expense->isEditable())
                                                        <button
                                                            wire:click="openExpenseEditModal({{ $expense->id }})"
                                                            class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                            title="Edit">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                            </svg>
                                                        </button>
                                                    @else
                                                        <span class="text-slate-300 dark:text-slate-600 cursor-not-allowed" title="Cannot edit - has payments">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                            </svg>
                                                        </span>
                                                    @endif
                                                    @if($expense->status !== 'paid' && $expense->isOneTime())
                                                        <button
                                                            wire:click="markExpenseAsPaid({{ $expense->id }})"
                                                            class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300"
                                                            title="Mark as Paid">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                    <button
                                                        wire:click="deleteExpense({{ $expense->id }})"
                                                        wire:confirm="Are you sure you want to delete this expense?"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                                        title="Delete">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
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
                            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No expenses</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by adding an expense.</p>
                            <div class="mt-6">
                                <x-ui.button
                                    variant="primary"
                                    icon="plus"
                                    href="{{ route('expenses.jobsite.create', $jobSite) }}">
                                    Add Expense
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Change Orders Tab -->
        @if($activeTab === 'changeorders')
            <div class="space-y-6">
                <!-- Header with Search and Add Button -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">Change Orders ({{ $changeOrders->count() }})</h3>
                        <!-- Search Bar -->
                        <div class="relative max-w-md">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="changeOrderSearch"
                                placeholder="Search change orders..."
                                class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500"
                            >
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <x-ui.button
                        variant="primary"
                        wire:click="openCreateModal"
                        icon="plus">
                        Add Change Order
                    </x-ui.button>
                </div>

                <!-- Change Orders Table -->
                @if($changeOrders->count() > 0)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-700/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Title
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            File
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Created
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($changeOrders as $changeOrder)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $changeOrder->title }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">
                                                    {{ $changeOrder->description ?? '-' }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ Number::currency($changeOrder->amount, config('app.currency'), config('app.locale')) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($changeOrder->file_path)
                                                    <a href="{{ route('files.download', ['path' => $changeOrder->file_path]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline text-sm">
                                                        <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                        </svg>
                                                        Download
                                                    </a>
                                                @else
                                                    <span class="text-sm text-slate-400">No file</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                                    {{ $changeOrder->created_at->format('M d, Y') }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <button
                                                        wire:click="openViewModal({{ $changeOrder->id }})"
                                                        class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                        title="View">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        wire:click="openEditModal({{ $changeOrder->id }})"
                                                        class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                        title="Edit">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        wire:click="deleteChangeOrder({{ $changeOrder->id }})"
                                                        wire:confirm="Are you sure you want to delete this change order?"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                                        title="Delete">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No change orders</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a new change order.</p>
                        <div class="mt-6">
                            <x-ui.button
                                variant="primary"
                                wire:click="openCreateModal"
                                icon="plus">
                                Add Change Order
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Daily Reports Tab -->
        @if($activeTab === 'dailyreports')
            <div>
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Daily Reports</h2>
                    <x-ui.button
                        variant="primary"
                        icon="plus"
                        href="{{ route('dailyreports.create', $jobSite) }}">
                        Create Daily Report
                    </x-ui.button>
                </div>

                <!-- Daily Reports List -->
                @if($dailyReports->count() > 0)
                    <div class="space-y-4">
                        @foreach($dailyReports as $report)
                            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                                <div class="p-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                                    Daily Report - {{ $report->report_date->format('M d, Y') }}
                                                </h3>
                                                @if(!$report->isEditable())
                                                    <span class="px-2 py-1 text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400 rounded">
                                                        Locked
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex items-center space-x-4 text-sm text-slate-500 dark:text-slate-400">
                                                <span class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                    {{ $report->preparedBy->name }}
                                                </span>
                                                <span class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    Created {{ $report->created_at->diffForHumans() }}
                                                </span>
                                                <span class="flex items-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                                    </svg>
                                                    {{ $report->tasks->count() }} {{ Str::plural('task', $report->tasks->count()) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <x-ui.button
                                                variant="secondary"
                                                size="sm"
                                                icon="eye"
                                                href="{{ route('dailyreports.edit', [$jobSite, $report]) }}">
                                                View
                                            </x-ui.button>
                                            @if($report->isEditable())
                                                <x-ui.button
                                                    variant="secondary"
                                                    size="sm"
                                                    icon="edit"
                                                    href="{{ route('dailyreports.edit', [$jobSite, $report]) }}">
                                                    Edit
                                                </x-ui.button>
                                            @endif
                                            <x-ui.button
                                                variant="secondary"
                                                size="sm"
                                                href="{{ route('dailyreports.pdf.download', $report) }}"
                                                title="Download PDF">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                            </x-ui.button>
                                        </div>
                                    </div>

                                    @if($report->tasks->count() > 0)
                                        <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                                            <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Tasks:</h4>
                                            <div class="space-y-2">
                                                @foreach($report->tasks as $task)
                                                    <div class="flex items-start space-x-2">
                                                        <svg class="w-4 h-4 mt-0.5 text-[#3F5189] dark:text-[#4A5A96] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                        </svg>
                                                        <div class="flex-1">
                                                            <div class="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">
                                                                {!! Str::limit(strip_tags($task->description), 150) !!}
                                                            </div>
                                                            @if($task->images->count() > 0)
                                                                <span class="inline-flex items-center text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                                    </svg>
                                                                    {{ $task->images->count() }} {{ Str::plural('image', $task->images->count()) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No daily reports yet</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating your first daily report.</p>
                        <div class="mt-6">
                            <x-ui.button
                                variant="primary"
                                href="{{ route('dailyreports.create', $jobSite) }}"
                                icon="plus">
                                Create Daily Report
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Budget Tab -->
        @if($activeTab === 'budget')
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Job Site Budget</h3>
                        @if(!$budget)
                            <x-ui.button
                                variant="primary"
                                size="sm"
                                href="{{ route('job-sites.budgets.create', $jobSite->id) }}"
                                icon="plus">
                                Create Budget
                            </x-ui.button>
                        @endif
                    </div>

                    <div class="p-6">
                        @if($budget)
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-[#3F5189]/10 to-[#5A6FA8]/10 dark:from-[#3F5189]/20 dark:to-[#5A6FA8]/20 rounded-lg">
                                <div>
                                    <h4 class="font-semibold text-slate-900 dark:text-white">{{ $budget->name }}</h4>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                        {{ $budget->items_count }} cost codes
                                        @if($budget->sourceTemplate)
                                            &bull; Template: {{ $budget->sourceTemplate->name }}
                                        @endif
                                    </p>
                                    @if($budget->notes)
                                        <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">{{ Str::limit($budget->notes, 100) }}</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ Number::currency($budget->total_amount, config('app.currency'), config('app.locale')) }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-2">
                                        <x-ui.button
                                            variant="secondary"
                                            size="sm"
                                            href="{{ route('budgets.show', $budget->id) }}"
                                            icon="eye">
                                            View Details
                                        </x-ui.button>
                                        <x-ui.button
                                            variant="ghost"
                                            size="sm"
                                            href="{{ route('budgets.edit', $budget->id) }}"
                                            icon="edit">
                                            Edit
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Preview of Top Cost Codes -->
                            @if($budget->parentItems->count() > 0)
                                <div class="mt-6">
                                    <h5 class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Cost Code Summary</h5>
                                    <div class="space-y-2">
                                        @foreach($budget->parentItems->take(5) as $item)
                                            <div class="flex items-center justify-between py-2 px-3 bg-slate-50 dark:bg-slate-900/50 rounded">
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 text-xs font-mono font-medium rounded bg-[#3F5189] text-white">{{ $item->code }}</span>
                                                    <span class="text-sm text-slate-700 dark:text-slate-300">{{ $item->name }}</span>
                                                </div>
                                                <span class="text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ Number::currency($item->budgeted_amount, config('app.currency'), config('app.locale')) }}
                                                </span>
                                            </div>
                                        @endforeach
                                        @if($budget->parentItems->count() > 5)
                                            <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-2">
                                                + {{ $budget->parentItems->count() - 5 }} more cost codes
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                <h4 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No budget yet</h4>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a budget to track cost allocation for this job site.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Change Order Modal -->
    <x-ui.modal name="change-order-modal" maxWidth="2xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                @if($modalMode === 'view')
                    View Change Order
                @elseif($modalMode === 'edit')
                    Edit Change Order
                @else
                    Add New Change Order
                @endif
            </h3>
        </div>

        <div class="p-6">
            @if($modalMode === 'view')
                <!-- View Mode -->
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                            <p class="text-slate-900 dark:text-white">{{ $title }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Requested Date</label>
                            <p class="text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($requested_date)->format('M d, Y') }}</p>
                        </div>
                    </div>

                    @if($description)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                            <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $description }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount</label>
                        <p class="text-slate-900 dark:text-white font-medium">{{ Number::currency($amount ?: 0, config('app.currency'), config('app.locale')) }}</p>
                    </div>

                    @if($existingFilePath)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Attached File</label>
                            <a href="{{ route('files.download', ['path' => $existingFilePath]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Download File
                            </a>
                        </div>
                    @endif

                    @if($editingChangeOrder)
                        @php
                            $changeOrder = \App\Models\ChangeOrder::with('createdBy')->find($editingChangeOrder);
                        @endphp
                        @if($changeOrder && $changeOrder->createdBy)
                            <div class="pt-4 mt-4 border-t border-slate-200 dark:border-slate-700">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Created By</label>
                                        <p class="text-slate-900 dark:text-white">{{ $changeOrder->createdBy->name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Created On</label>
                                        <p class="text-slate-900 dark:text-white">{{ $changeOrder->created_at->format('M d, Y g:i A') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="flex items-center justify-end space-x-4 mt-6">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="closeModal">
                        Close
                    </x-ui.button>
                </div>
            @else
                <!-- Create/Edit Form -->
                <form wire:submit="saveChangeOrder" class="space-y-6">
                    <!-- Title and Date -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Title <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="title"
                                wire:model="title"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Enter change order title"
                            >
                            @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="requested_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Requested Date <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="requested_date"
                                wire:model="requested_date"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            >
                            @error('requested_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Description
                        </label>
                        <textarea
                            id="description"
                            wire:model="description"
                            rows="4"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="Enter description (optional)"
                        ></textarea>
                        @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Amount <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-slate-500 dark:text-slate-400">$</span>
                            <input
                                type="number"
                                step="0.01"
                                id="amount"
                                wire:model="amount"
                                class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="0.00"
                            >
                        </div>
                        @error('amount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- File Upload -->
                    <div>
                        <label for="file" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Attach File
                        </label>
                        @if($existingFilePath && !$file)
                            <div class="mb-2 text-sm text-slate-600 dark:text-slate-400">
                                Current file:
                                <a href="{{ route('files.download', ['path' => $existingFilePath]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    Download
                                </a>
                            </div>
                        @endif
                        <input
                            type="file"
                            id="file"
                            wire:model="file"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Maximum file size: 10MB</p>
                        @error('file') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                        @if($file)
                            <div class="mt-2 text-sm text-green-600 dark:text-green-400">
                                New file selected: {{ $file->getClientOriginalName() }}
                            </div>
                        @endif
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="closeModal">
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary">
                            {{ $modalMode === 'edit' ? 'Update' : 'Create' }}
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </x-ui.modal>

    <!-- Expense Modal -->
    <x-ui.modal name="expense-modal" maxWidth="2xl">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-4">
                @if($expenseModalMode === 'view')
                    Expense Details
                @elseif($expenseModalMode === 'edit')
                    Edit Expense
                @else
                    Add Expense
                @endif
            </h2>

            @if($expenseModalMode === 'view')
                <!-- View Mode -->
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Date</label>
                            <p class="text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($expense_date)->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Type</label>
                            <p class="text-slate-900 dark:text-white">
                                @if($isCustomItem)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">Custom Item</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">From Catalog</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Item Name</label>
                        <p class="text-slate-900 dark:text-white">{{ $expense_item_name }}</p>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Quantity</label>
                            <p class="text-slate-900 dark:text-white">{{ $expense_quantity }}{{ $expense_usage_unit ? ' ' . $expense_usage_unit : '' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Unit Price</label>
                            <p class="text-slate-900 dark:text-white">{{ Number::currency($expense_unit_price ?: 0, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Amount</label>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ Number::currency($expense_total_amount ?: 0, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                    </div>

                    @if($expense_notes)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                            <p class="text-slate-900 dark:text-white">{{ $expense_notes }}</p>
                        </div>
                    @endif

                    @if($existingReceiptPath)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Receipt</label>
                            <a href="{{ route('files.download', ['path' => $existingReceiptPath]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Download Receipt
                            </a>
                        </div>
                    @endif

                    <!-- Payment Information Section -->
                    <div class="border-t border-slate-200 dark:border-slate-700 pt-4 mt-4">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-4">Payment Information</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
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
                                    {{ ucfirst($expense_status) }}
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Payment Method</label>
                                <p class="text-slate-900 dark:text-white">
                                    {{ $expense_payment_method ? str_replace('_', ' ', ucfirst($expense_payment_method)) : 'Not specified' }}
                                    @if($expense_is_auto_payment)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300">
                                            Auto
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($expense_has_installments && $viewingExpense)
                            <!-- Installment Payment Schedule -->
                            <div class="mt-4">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Payment Schedule</label>
                                    <span class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ $viewingExpense->getPaidInstallmentsCount() }}/{{ $viewingExpense->total_installments }} paid
                                    </span>
                                </div>

                                <!-- Progress Bar -->
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5 mb-4">
                                    <div class="bg-green-500 h-2.5 rounded-full" style="width: {{ $viewingExpense->getPaymentProgress() }}%"></div>
                                </div>

                                <div class="bg-slate-50 dark:bg-slate-900 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">#</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Due Date</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Amount</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Status</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Paid Date</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
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
                                                            {{ ucfirst($payment->status) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                        {{ $payment->paid_date ? $payment->paid_date->format('M d, Y') : '-' }}
                                                    </td>
                                                    <td class="px-4 py-2 text-right">
                                                        @if($payment->status === 'pending')
                                                            <button
                                                                wire:click="markPaymentAsPaid({{ $payment->id }})"
                                                                class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">
                                                                Mark Paid
                                                            </button>
                                                            <button
                                                                wire:click="markPaymentAsOverdue({{ $payment->id }})"
                                                                class="ml-2 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium">
                                                                Overdue
                                                            </button>
                                                        @elseif($payment->status === 'overdue')
                                                            <button
                                                                wire:click="markPaymentAsPaid({{ $payment->id }})"
                                                                class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 text-sm font-medium">
                                                                Mark Paid
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3 flex justify-between text-sm">
                                    <span class="text-green-600 dark:text-green-400 font-medium">
                                        Paid: {{ Number::currency($viewingExpense->getPaidAmount(), config('app.currency'), config('app.locale')) }}
                                    </span>
                                    <span class="text-amber-600 dark:text-amber-400 font-medium">
                                        Pending: {{ Number::currency($viewingExpense->getPendingAmount(), config('app.currency'), config('app.locale')) }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <!-- One-time Payment Info -->
                            <div class="grid grid-cols-2 gap-4 mt-4">
                                @if($expense_status === 'paid' && $expense_paid_date)
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Paid Date</label>
                                        <p class="text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($expense_paid_date)->format('M d, Y') }}</p>
                                    </div>
                                @elseif($expense_status === 'unpaid' && $expense_payment_due_date)
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Due Date</label>
                                        <p class="text-slate-900 dark:text-white">{{ \Carbon\Carbon::parse($expense_payment_due_date)->format('M d, Y') }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end pt-4">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="closeExpenseModal">
                            Close
                        </x-ui.button>
                    </div>
                </div>
            @else
                <!-- Create/Edit Mode -->
                <form wire:submit.prevent="saveExpense" class="space-y-4">
                    <!-- Item Type Toggle -->
                    <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-lg">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $isCustomItem ? 'Custom Item' : 'From Catalog' }}
                        </span>
                        <button
                            type="button"
                            wire:click="toggleCustomItem"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $isCustomItem ? 'bg-[#3F5189]' : 'bg-slate-200' }}">
                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isCustomItem ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                    </div>

                    @if(!$isCustomItem)
                        <!-- Catalog Item Search -->
                        <div class="relative">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Search Catalog Item</label>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="catalogItemSearch"
                                placeholder="Type to search catalog..."
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">

                            @if($catalogItems->count() > 0 && !$selectedCatalogItem)
                                <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-60 overflow-auto">
                                    @foreach($catalogItems as $catalogItem)
                                        <button
                                            type="button"
                                            wire:click="selectCatalogItem({{ $catalogItem->id }})"
                                            class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center justify-between">
                                            <div>
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $catalogItem->name }}</div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ ucfirst($catalogItem->type) }} - {{ Number::currency($catalogItem->current_cost, config('app.currency'), config('app.locale')) }}</div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Item Name (auto-filled from catalog or manual for custom) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Item Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model="expense_item_name"
                            {{ $isCustomItem ? '' : 'readonly' }}
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white {{ $isCustomItem ? '' : 'bg-slate-100 dark:bg-slate-900' }}"
                            placeholder="Enter item name">
                        @error('expense_item_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    @if(!$isCustomItem && $selectedCatalogItem && $expense_purchase_unit && $expense_usage_unit)
                        <!-- Unit Type Selector (for products with conversion) -->
                        <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">
                                Select Unit Type <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="relative flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all {{ $expense_unit_type_used === 'usage' ? 'border-[#3F5189] bg-[#3F5189]/5' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400' }}">
                                    <input
                                        type="radio"
                                        wire:model.live="expense_unit_type_used"
                                        value="usage"
                                        class="sr-only">
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $expense_usage_unit }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Usage unit</div>
                                    </div>
                                </label>
                                <label class="relative flex items-center p-3 border-2 rounded-lg cursor-pointer transition-all {{ $expense_unit_type_used === 'purchase' ? 'border-[#3F5189] bg-[#3F5189]/5' : 'border-slate-300 dark:border-slate-600 hover:border-slate-400' }}">
                                    <input
                                        type="radio"
                                        wire:model.live="expense_unit_type_used"
                                        value="purchase"
                                        class="sr-only">
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $expense_purchase_unit }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Purchase unit</div>
                                    </div>
                                </label>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-2">
                                Example: Use "{{ $expense_usage_unit }}" for individual items or "{{ $expense_purchase_unit }}" for whole packages
                            </p>
                        </div>
                    @endif

                    @if($isCustomItem)
                        <!-- Custom Item Fields -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Type</label>
                                <select wire:model="expense_item_type" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="">Select type (optional)</option>
                                    <option value="product">Product</option>
                                    <option value="service">Service</option>
                                    <option value="rental">Rental</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Unit</label>
                                <input
                                    type="text"
                                    wire:model="expense_usage_unit"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="e.g., Each, Hour">
                            </div>
                        </div>
                    @endif

                    <!-- Quantity and Price -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Quantity <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                wire:model.live="expense_quantity"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="0.00">
                            @error('expense_quantity') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Unit Price <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-slate-500 dark:text-slate-400">$</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    wire:model.live="expense_unit_price"
                                    class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="0.00">
                            </div>
                            @error('expense_unit_price') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Total</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-slate-500 dark:text-slate-400">$</span>
                                <input
                                    type="text"
                                    value="{{ $expense_total_amount }}"
                                    readonly
                                    class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-white font-semibold">
                            </div>
                        </div>
                    </div>

                    <!-- Date -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Expense Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            wire:model="expense_date"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        @error('expense_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Notes</label>
                        <textarea
                            wire:model="expense_notes"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="Optional notes about this expense"></textarea>
                    </div>

                    <!-- Receipt Upload -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Receipt
                        </label>
                        @if($existingReceiptPath && !$expense_receipt)
                            <div class="mb-2 p-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-600 dark:text-slate-400">Current receipt:</span>
                                    <a href="{{ route('files.download', ['path' => $existingReceiptPath]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline font-medium">
                                        <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Download
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div
                            x-data="{ isDragging: false }"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }))"
                            :class="isDragging ? 'border-[#3F5189] bg-[#3F5189]/5' : 'border-slate-300 dark:border-slate-600'"
                            class="relative border-2 border-dashed rounded-lg p-6 transition-colors duration-200 hover:border-[#3F5189] dark:hover:border-[#4A5A96] cursor-pointer"
                            @click="$refs.fileInput.click()">

                            <input
                                type="file"
                                x-ref="fileInput"
                                wire:model="expense_receipt"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="hidden"
                            >

                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="mt-4 flex justify-center text-sm text-slate-600 dark:text-slate-400">
                                    <span class="relative font-medium text-[#3F5189] dark:text-[#4A5A96] hover:text-[#2F3F6F]">
                                        Click to upload
                                    </span>
                                    <span class="pl-1">or drag and drop</span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">PDF, JPG, PNG up to 10MB</p>
                            </div>
                        </div>

                        @error('expense_receipt') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror

                        @if($expense_receipt)
                            <div class="mt-3 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-green-600 dark:text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-sm text-green-800 dark:text-green-300 font-medium">
                                        {{ $expense_receipt->getClientOriginalName() }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Payment Section -->
                    <div class="border-t border-slate-200 dark:border-slate-700 pt-4 mt-4">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-white mb-4">Payment Information</h3>

                        <!-- Payment Method and Auto Payment -->
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Payment Method</label>
                                <select
                                    wire:model="expense_payment_method"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="">Select method</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    @if(config('app.country') === 'BR')
                                        <option value="pix">PIX</option>
                                    @endif
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="flex items-center">
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="expense_is_auto_payment"
                                        class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                    <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Auto Payment</span>
                                </label>
                            </div>
                        </div>

                        <!-- Installments Toggle -->
                        <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-lg mb-4">
                            <div>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Split into installments</span>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Enable to divide this expense into multiple payments</p>
                            </div>
                            <button
                                type="button"
                                wire:click="$toggle('expense_has_installments')"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $expense_has_installments ? 'bg-[#3F5189]' : 'bg-slate-200' }}">
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $expense_has_installments ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>

                        @if(!$expense_has_installments)
                            <!-- One-Time Payment Options -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Status</label>
                                    <select
                                        wire:model.live="expense_status"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        <option value="paid">Paid</option>
                                        <option value="unpaid">Unpaid</option>
                                    </select>
                                </div>
                                @if($expense_status === 'paid')
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Paid Date</label>
                                        <input
                                            type="date"
                                            wire:model="expense_paid_date"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    </div>
                                @else
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Payment Due Date <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="date"
                                            wire:model="expense_payment_due_date"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        @error('expense_payment_due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                @endif
                            </div>
                        @else
                            <!-- Installment Options -->
                            <div class="space-y-4">
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Number of Installments <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            min="2"
                                            max="120"
                                            wire:model.live="expense_total_installments"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        @error('expense_total_installments') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Frequency <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            wire:model.live="expense_payment_frequency"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                            <option value="weekly">Weekly</option>
                                            <option value="biweekly">Biweekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            First Payment Date <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="date"
                                            wire:model.live="expense_payment_due_date"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        @error('expense_payment_due_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Amount Type Toggle -->
                                <div class="flex items-center space-x-4">
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            type="radio"
                                            wire:model.live="expense_use_custom_amounts"
                                            value="0"
                                            class="border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                        <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">
                                            Equal amounts
                                            @if($expense_total_amount && $expense_total_installments)
                                                ({{ Number::currency($expense_total_amount / $expense_total_installments, config('app.currency'), config('app.locale')) }} each)
                                            @endif
                                        </span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input
                                            type="radio"
                                            wire:model.live="expense_use_custom_amounts"
                                            value="1"
                                            class="border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                        <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Custom amounts</span>
                                    </label>
                                </div>

                                <!-- Payment Schedule Preview -->
                                @if(count($expense_payment_schedule_preview) > 0)
                                    <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4">
                                        <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Payment Schedule Preview</h4>
                                        <div class="max-h-48 overflow-y-auto">
                                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                                <thead>
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">#</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Due Date</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                                    @foreach($expense_payment_schedule_preview as $index => $preview)
                                                        <tr>
                                                            <td class="px-3 py-2 text-sm text-slate-900 dark:text-white">{{ $preview['number'] }}</td>
                                                            <td class="px-3 py-2 text-sm text-slate-900 dark:text-white">{{ $preview['due_date_formatted'] }}</td>
                                                            <td class="px-3 py-2 text-sm text-slate-900 dark:text-white">
                                                                @if($expense_use_custom_amounts)
                                                                    <input
                                                                        type="number"
                                                                        step="0.01"
                                                                        wire:model.live.debounce.500ms="expense_custom_amounts.{{ $index }}"
                                                                        class="w-24 px-2 py-1 text-sm border border-slate-300 dark:border-slate-600 rounded focus:outline-none focus:ring-1 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                                                @else
                                                                    {{ Number::currency($preview['amount'], config('app.currency'), config('app.locale')) }}
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        @if($expense_use_custom_amounts)
                                            @php
                                                $customTotal = array_sum($expense_custom_amounts ?? []);
                                                $expectedTotal = floatval($expense_total_amount);
                                                $diff = round($expectedTotal - $customTotal, 2);
                                            @endphp
                                            <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700 flex justify-between text-sm">
                                                <span class="text-slate-600 dark:text-slate-400">
                                                    Total: {{ Number::currency($customTotal, config('app.currency'), config('app.locale')) }}
                                                </span>
                                                @if($diff != 0)
                                                    <span class="text-red-600 dark:text-red-400 font-medium">
                                                        {{ $diff > 0 ? 'Remaining:' : 'Over by:' }} {{ Number::currency(abs($diff), config('app.currency'), config('app.locale')) }}
                                                    </span>
                                                @else
                                                    <span class="text-green-600 dark:text-green-400 font-medium">
                                                        Amounts match total
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-400">
                                                Total: {{ Number::currency($expense_total_amount, config('app.currency'), config('app.locale')) }}
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="closeExpenseModal">
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary">
                            {{ $expenseModalMode === 'edit' ? 'Update' : 'Add Expense' }}
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </x-ui.modal>
</div>
