<x-jobsite-layout :jobSite="$jobSite" active="overview" title="Job Site Details">
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
                            <a href="{{ route('projects.overview', $jobSite->project->id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
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
                                href="{{ route('jobsites.expenses', $jobSite) }}"
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
                                href="{{ route('jobsites.change-orders', $jobSite) }}"
                                class="w-full">
                                View Change Orders
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-jobsite-layout>
