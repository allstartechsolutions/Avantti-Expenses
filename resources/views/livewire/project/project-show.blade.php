<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Project Details</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $project->project_name }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('projects.index') }}"
                    icon="arrow-left">
                    Back to Projects
                </x-ui.button>
                <x-ui.button
                    variant="primary"
                    href="{{ route('projects.edit', $project->id) }}"
                    icon="edit">
                    Edit Project
                </x-ui.button>
                <x-ui.button
                    variant="danger"
                    wire:click="confirmDeleteProject"
                    icon="trash">
                    Delete
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
                    wire:click="setActiveTab('jobsites')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'jobsites' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Job Sites
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
                    wire:click="setActiveTab('change-orders')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'change-orders' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Change Orders
                </button>
                <button
                    wire:click="setActiveTab('daily-reports')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'daily-reports' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
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
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Project Information Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Project Information</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Project Name -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Project Name
                                    </label>
                                    <p class="text-slate-900 dark:text-white font-medium">{{ $project->project_name }}</p>
                                </div>

                                <!-- Client -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Client
                                    </label>
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-full flex items-center justify-center mr-2">
                                            <span class="text-xs font-medium text-white">{{ $project->client->initials }}</span>
                                        </div>
                                        <a href="{{ route('clients.show', $project->client->id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            {{ $project->client->company_name }}
                                        </a>
                                    </div>
                                </div>

                                <!-- Amount -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Amount') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white font-medium">{{ Number::currency($project->getAdjustedContractValue(), config('app.currency'), config('app.locale')) }}</p>
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
                                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                        ];
                                        $statusColor = $statusColors[$project->status->value] ?? $statusColors['created'];
                                    @endphp
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusColor }}">
                                        {{ $project->status->label() }}
                                    </span>
                                </div>

                                <!-- Created At -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Created On
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->created_at->format('F d, Y') }}</p>
                                </div>

                                <!-- Created By -->
                                @if($project->createdBy)
                                    <div>
                                        <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                            Created By
                                        </label>
                                        <p class="text-slate-900 dark:text-white">{{ $project->createdBy->name }}</p>
                                    </div>
                                @endif
                            </div>

                            <!-- Description -->
                            @if($project->description)
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Description
                                    </label>
                                    <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $project->description }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Contact Information Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contact Information</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Contact Person -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Contact Person
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->contact_person }}</p>
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Phone Number
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->phone ?? 'Not provided' }}</p>
                                </div>

                                <!-- Email -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Email Address
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->email }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Project Address</h3>
                        </div>
                        <div class="p-6">
                            @if($project->full_address)
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Full Address
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->full_address }}</p>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Street -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Street Address
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->street ?? 'Not provided' }}</p>
                                </div>

                                <!-- Address Line 2 -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Address Line 2
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->address_2 ?? 'Not provided' }}</p>
                                </div>

                                @if(config('app.country') === 'BR')
                                <!-- Neighborhood (Brazil only) -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Neighborhood (Bairro)
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->neighborhood ?? 'Not provided' }}</p>
                                </div>
                                @endif

                                <!-- City -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        City
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->city ?? 'Not provided' }}</p>
                                </div>

                                <!-- State -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        State
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->state ?? 'Not provided' }}</p>
                                </div>

                                <!-- Postal Code -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ config('app.country') === 'BR' ? 'CEP' : 'Postal Code' }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $project->postal_code ?? 'Not provided' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Actions -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Quick Actions</h3>
                        </div>
                        <div class="p-6 space-y-3">
                            <x-ui.button
                                variant="secondary"
                                class="w-full justify-center"
                                href="{{ route('projects.edit', $project->id) }}"
                                icon="edit">
                                Edit Project
                            </x-ui.button>

                            @if($project->email)
                                <a href="mailto:{{ $project->email }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    Send Email
                                </a>
                            @endif

                            @if($project->phone)
                                <a href="tel:{{ $project->phone }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    Call Contact
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Project Stats -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Project Information</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500 dark:text-slate-400">Project ID</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $project->id }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500 dark:text-slate-400">Created</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $project->created_at->diffForHumans() }}</span>
                            </div>
                            @if($project->created_at != $project->updated_at)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">Last Updated</span>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $project->updated_at->diffForHumans() }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Job Sites Tab -->
        @if($activeTab === 'jobsites')
            <div class="space-y-6">
                <!-- Header with Search and Add Button -->
                @if(!$showJobSiteForm)
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">Job Sites ({{ $jobSites->count() }})</h3>
                            <!-- Search Bar -->
                            <div class="relative max-w-md">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="jobSiteSearch"
                                    placeholder="Search job sites..."
                                    class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500"
                                >
                                <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <x-ui.button
                            variant="primary"
                            wire:click="openJobSiteForm"
                            icon="plus">
                            Add Job Site
                        </x-ui.button>
                    </div>
                @endif

                <!-- Job Site Form -->
                @if($showJobSiteForm)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700"
                        x-data="jobSiteAddressAutocomplete({
                            country: '{{ config('app.country') }}',
                            streetInputId: 'jobsite-street'
                        })"
                        x-init="init()">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                {{ $editingJobSite ? 'Edit Job Site' : 'Add New Job Site' }}
                            </h3>
                        </div>
                        <div class="p-6">
                            <form wire:submit="saveJobSite" class="space-y-6">
                                <!-- Job Site Name and Amount -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="job_site_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Job Site Name <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="job_site_name"
                                            wire:model.live="job_site_name"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="Enter job site name"
                                        >
                                        @error('job_site_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label for="job_amount" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Job Amount <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-2 text-slate-500 dark:text-slate-400">$</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                id="job_amount"
                                                wire:model.live="job_amount"
                                                class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                                placeholder="0.00"
                                            >
                                        </div>
                                        @error('job_amount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Contact Person, Phone, Email -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label for="contact_person" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Contact Person <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="contact_person"
                                            wire:model.live="contact_person"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="Contact name"
                                        >
                                        @error('contact_person') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Phone
                                        </label>
                                        <input
                                            type="tel"
                                            id="phone"
                                            wire:model.live="phone"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="+1 (555) 123-4567"
                                        >
                                        @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            Email <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="email"
                                            id="email"
                                            wire:model.live="email"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="email@example.com"
                                        >
                                        @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Address with Autocomplete -->
                                <div>
                                    <label for="jobsite-street" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Street Address
                                    </label>
                                    <input
                                        type="text"
                                        id="jobsite-street"
                                        x-ref="streetInput"
                                        wire:model.live="street"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="Start typing an address..."
                                        autocomplete="off"
                                    >
                                    @error('street') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <!-- Address Line 2 -->
                                <div>
                                    <label for="address_2" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Address Line 2
                                    </label>
                                    <input
                                        type="text"
                                        id="address_2"
                                        wire:model.live="address_2"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="Suite, Apt, Unit, etc."
                                    >
                                    @error('address_2') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                @if(config('app.country') === 'BR')
                                <!-- Neighborhood (Brazil only) -->
                                <div>
                                    <label for="neighborhood" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Neighborhood (Bairro)
                                    </label>
                                    <input
                                        type="text"
                                        id="neighborhood"
                                        wire:model.live="neighborhood"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="Bairro"
                                    >
                                    @error('neighborhood') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                                @endif

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label for="city" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            City
                                        </label>
                                        <input
                                            type="text"
                                            id="city"
                                            wire:model.live="city"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="City"
                                        >
                                        @error('city') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label for="state" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            State
                                        </label>
                                        <input
                                            type="text"
                                            id="state"
                                            wire:model.live="state"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="State"
                                        >
                                        @error('state') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label for="postal_code" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            {{ config('app.country') === 'BR' ? 'CEP' : 'Postal Code' }}
                                        </label>
                                        <input
                                            type="text"
                                            id="postal_code"
                                            wire:model.live="postal_code"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="{{ config('app.country') === 'BR' ? '00000-000' : '12345' }}"
                                        >
                                        @error('postal_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Hidden lat/long fields -->
                                <input type="hidden" wire:model="latitude">
                                <input type="hidden" wire:model="longitude">

                                <!-- Status -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Status <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        id="status"
                                        wire:model.live="status"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        @foreach($statuses as $statusOption)
                                            <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <!-- Form Actions -->
                                <div class="flex items-center justify-end space-x-4 pt-4">
                                    <x-ui.button
                                        type="button"
                                        variant="secondary"
                                        wire:click="cancelJobSiteForm">
                                        Cancel
                                    </x-ui.button>
                                    <x-ui.button
                                        type="submit"
                                        variant="primary"
                                        icon="save"
                                        wire:loading.attr="disabled">
                                        <span wire:loading.remove>{{ $editingJobSite ? 'Update' : 'Create' }} Job Site</span>
                                        <span wire:loading>Saving...</span>
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Job Sites Table -->
                @if(!$showJobSiteForm)
                    @if($jobSites->count() > 0)
                        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                                Job Site Name
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                                Contact Person
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                                Location
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                                Amount
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                                Status
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
                                        @foreach($jobSites as $jobSite)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                        {{ $jobSite->job_site_name }}
                                                    </div>
                                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                                        {{ $jobSite->email }}
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-slate-900 dark:text-white">{{ $jobSite->contact_person }}</div>
                                                    @if($jobSite->phone)
                                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $jobSite->phone }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-slate-900 dark:text-white">
                                                        @if($jobSite->city || $jobSite->state)
                                                            {{ $jobSite->city }}{{ $jobSite->city && $jobSite->state ? ', ' : '' }}{{ $jobSite->state }}
                                                        @else
                                                            <span class="text-slate-400">Not specified</span>
                                                        @endif
                                                    </div>
                                                    @if($jobSite->street)
                                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $jobSite->street }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                                        {{ Number::currency($jobSite->job_amount, config('app.currency'), config('app.locale')) }}
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @php
                                                        $statusColors = [
                                                            'created' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
                                                            'in_progress' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-300',
                                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                                            'on_hold' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300',
                                                        ];
                                                        $statusColor = $statusColors[$jobSite->status->value] ?? $statusColors['created'];
                                                    @endphp
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                                        {{ $jobSite->status->label() }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-slate-900 dark:text-white">
                                                        {{ $jobSite->created_at->format('M d, Y') }}
                                                    </div>
                                                    @if($jobSite->createdBy)
                                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                                            by {{ $jobSite->createdBy->name }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div class="flex items-center justify-end space-x-2">
                                                        <x-ui.button
                                                            variant="secondary"
                                                            size="sm"
                                                            href="{{ route('jobsites.overview', $jobSite->id) }}"
                                                            icon="eye">
                                                            View
                                                        </x-ui.button>
                                                        <x-ui.button
                                                            variant="secondary"
                                                            size="sm"
                                                            wire:click="editJobSite({{ $jobSite->id }})"
                                                            icon="edit">
                                                            Edit
                                                        </x-ui.button>
                                                        <x-ui.button
                                                            variant="danger"
                                                            size="sm"
                                                            wire:click="confirmDeleteJobSite({{ $jobSite->id }})"
                                                            icon="trash">
                                                            Delete
                                                        </x-ui.button>
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No Job Sites</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a job site for this project.</p>
                                <div class="mt-6">
                                    <x-ui.button
                                        variant="primary"
                                        wire:click="openJobSiteForm"
                                        icon="plus">
                                        Add Job Site
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
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
                        <!-- Location Filter -->
                        <select
                            wire:model.live="expenseLocationFilter"
                            class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="all">All Locations</option>
                            <option value="project">Project (General)</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
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
                        href="{{ route('expenses.project.create', $project) }}">
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
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Supplier / Items</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Location</th>
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
                                                            {{ $expense->supplier?->name ?? 'No Supplier' }}
                                                        </div>
                                                        @if($expense->items->count() > 0)
                                                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                                                {{ $expense->items->count() }} {{ Str::plural('item', $expense->items->count()) }}
                                                            </span>
                                                        @elseif($expense->item_name)
                                                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $expense->item_name }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($expense->jobSite)
                                                    <span class="text-sm text-slate-900 dark:text-white">{{ $expense->jobSite->job_site_name }}</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                                        Project (General)
                                                    </span>
                                                @endif
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
                                    href="{{ route('expenses.project.create', $project) }}">
                                    Add Expense
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Change Orders Tab -->
        @if($activeTab === 'change-orders')
            <div class="space-y-6">
                <!-- Header with Search, Filter and Add Button -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 flex flex-col sm:flex-row gap-4">
                        <!-- Search Bar -->
                        <div class="relative max-w-md">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="changeOrderSearch"
                                placeholder="Search change orders..."
                                class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <!-- Location Filter -->
                        <select
                            wire:model.live="changeOrderLocationFilter"
                            class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="all">All Locations</option>
                            <option value="project">Project (General)</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-ui.button
                        variant="primary"
                        icon="plus"
                        wire:click="openChangeOrderCreateModal">
                        Add Change Order
                    </x-ui.button>
                </div>

                <!-- Summary Card -->
                <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80">Total Change Orders</p>
                            <p class="text-3xl font-bold mt-1">{{ Number::currency($totalChangeOrdersAmount, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                        <div class="bg-white/10 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-white/80">{{ $changeOrders->count() }} {{ Str::plural('change order', $changeOrders->count()) }} recorded</p>
                </div>

                <!-- Change Orders List -->
                @if($changeOrders->count() > 0)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Title</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Location</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Amount</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">File</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($changeOrders as $changeOrder)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                                {{ $changeOrder->requested_date->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $changeOrder->title }}
                                                </div>
                                                @if($changeOrder->description)
                                                    <div class="text-sm text-slate-500 dark:text-slate-400 truncate max-w-xs">
                                                        {{ Str::limit($changeOrder->description, 50) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($changeOrder->jobSite)
                                                    <span class="text-sm text-slate-900 dark:text-white">{{ $changeOrder->jobSite->job_site_name }}</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                                        Project (General)
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $changeOrder->amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                                {{ $changeOrder->amount >= 0 ? '+' : '' }}{{ Number::currency($changeOrder->amount, config('app.currency'), config('app.locale')) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($changeOrder->file_path)
                                                    <a href="{{ Storage::url($changeOrder->file_path) }}" target="_blank" class="text-[#3F5189] hover:text-[#4A5A96] dark:text-[#4A5A96] dark:hover:text-[#5A6AA6]">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                        </svg>
                                                    </a>
                                                @else
                                                    <span class="text-slate-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <button wire:click="openChangeOrderViewModal({{ $changeOrder->id }})" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </button>
                                                    <button wire:click="openChangeOrderEditModal({{ $changeOrder->id }})" class="text-[#3F5189] hover:text-[#4A5A96] dark:text-[#4A5A96] dark:hover:text-[#5A6AA6]">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </button>
                                                    <button wire:click="deleteChangeOrder({{ $changeOrder->id }})" wire:confirm="Are you sure you want to delete this change order?" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No change orders</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by adding a change order.</p>
                            <div class="mt-6">
                                <x-ui.button
                                    variant="primary"
                                    icon="plus"
                                    wire:click="openChangeOrderCreateModal">
                                    Add Change Order
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Daily Reports Tab -->
        @if($activeTab === 'daily-reports')
            <div class="space-y-6">
                <!-- Header with Search, Filter and Add Button -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 flex flex-col sm:flex-row gap-4">
                        <!-- Search Bar -->
                        <div class="relative max-w-md">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="dailyReportSearch"
                                placeholder="Search daily reports..."
                                class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <!-- Location Filter -->
                        <select
                            wire:model.live="dailyReportLocationFilter"
                            class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="all">All Locations</option>
                            <option value="project">Project (General)</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-ui.button
                        variant="primary"
                        icon="plus"
                        href="{{ route('dailyreports.project.create', $project) }}">
                        Add Daily Report
                    </x-ui.button>
                </div>

                <!-- Summary Card -->
                <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-white/80">Total Daily Reports</p>
                            <p class="text-3xl font-bold mt-1">{{ $dailyReports->count() }}</p>
                        </div>
                        <div class="bg-white/10 rounded-full p-4">
                            <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-white/80">{{ $dailyReports->count() }} {{ Str::plural('report', $dailyReports->count()) }} recorded</p>
                </div>

                <!-- Daily Reports List -->
                @if($dailyReports->count() > 0)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Location</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Prepared By</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tasks</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($dailyReports as $report)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                                {{ $report->report_date->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($report->jobSite)
                                                    <span class="text-sm text-slate-900 dark:text-white">{{ $report->jobSite->job_site_name }}</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                                        Project (General)
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                                {{ $report->preparedBy?->name ?? 'Unknown' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                                {{ $report->tasks->count() }} {{ Str::plural('task', $report->tasks->count()) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($report->locked_at)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300">
                                                        <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                        </svg>
                                                        Locked
                                                    </span>
                                                @elseif($report->isEditable())
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300">
                                                        Editable
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                                        Read Only
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <!-- View PDF -->
                                                    <a href="{{ route('dailyreports.pdf.view', $report) }}" target="_blank" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white" title="View PDF">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </a>
                                                    <!-- Download PDF -->
                                                    <a href="{{ route('dailyreports.pdf.download', $report) }}" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white" title="Download PDF">
                                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                        </svg>
                                                    </a>
                                                    <!-- Edit -->
                                                    @if($report->isEditable() || auth()->user()->is_admin)
                                                        @if($report->jobSite)
                                                            <a href="{{ route('dailyreports.edit', ['jobSite' => $report->jobSite, 'dailyReport' => $report]) }}" class="text-[#3F5189] hover:text-[#4A5A96] dark:text-[#4A5A96] dark:hover:text-[#5A6AA6]" title="Edit">
                                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                </svg>
                                                            </a>
                                                        @else
                                                            <a href="{{ route('dailyreports.project.edit', ['project' => $project, 'dailyReport' => $report]) }}" class="text-[#3F5189] hover:text-[#4A5A96] dark:text-[#4A5A96] dark:hover:text-[#5A6AA6]" title="Edit">
                                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                                </svg>
                                                            </a>
                                                        @endif
                                                    @endif
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No daily reports</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a daily report.</p>
                            <div class="mt-6">
                                <x-ui.button
                                    variant="primary"
                                    icon="plus"
                                    href="{{ route('dailyreports.project.create', $project) }}">
                                    Add Daily Report
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Budget Tab -->
        @if($activeTab === 'budget')
            <div class="space-y-6">
                <!-- Project Budget Section -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Project Budget</h3>
                        @if(!$projectBudget)
                            <x-ui.button
                                variant="primary"
                                size="sm"
                                href="{{ route('projects.budgets.create', $project->id) }}"
                                icon="plus">
                                Create Budget
                            </x-ui.button>
                        @endif
                    </div>

                    <div class="p-6">
                        @if($projectBudget)
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-[#3F5189]/10 to-[#5A6FA8]/10 dark:from-[#3F5189]/20 dark:to-[#5A6FA8]/20 rounded-lg">
                                <div>
                                    <h4 class="font-semibold text-slate-900 dark:text-white">{{ $projectBudget->name }}</h4>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                        {{ $projectBudget->items_count }} cost codes
                                        @if($projectBudget->sourceTemplate)
                                            &bull; Template: {{ $projectBudget->sourceTemplate->name }}
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ Number::currency($projectBudget->total_amount, config('app.currency'), config('app.locale')) }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-2">
                                        <x-ui.button
                                            variant="secondary"
                                            size="sm"
                                            href="{{ route('budgets.show', $projectBudget->id) }}"
                                            icon="eye">
                                            View
                                        </x-ui.button>
                                        <x-ui.button
                                            variant="ghost"
                                            size="sm"
                                            href="{{ route('budgets.edit', $projectBudget->id) }}"
                                            icon="edit">
                                            Edit
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                <h4 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No project budget</h4>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a budget to track cost allocation for this project.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Job Site Budgets Section -->
                @if($jobSites->count() > 0)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Job Site Budgets</h3>
                        </div>

                        <div class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($jobSites as $jobSite)
                                @php
                                    $jobSiteBudget = $jobSiteBudgets->firstWhere('job_site_id', $jobSite->id);
                                @endphp
                                <div class="p-4 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-slate-100 dark:bg-slate-700 rounded-lg">
                                            <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <a href="{{ route('jobsites.overview', $jobSite->id) }}" class="font-medium text-slate-900 dark:text-white hover:text-[#3F5189]">
                                                {{ $jobSite->job_site_name }}
                                            </a>
                                            @if($jobSiteBudget)
                                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                                    {{ $jobSiteBudget->name }} &bull; {{ $jobSiteBudget->items_count }} cost codes
                                                </p>
                                            @else
                                                <p class="text-sm text-slate-400 dark:text-slate-500">No budget</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        @if($jobSiteBudget)
                                            <span class="font-semibold text-slate-900 dark:text-white">
                                                {{ Number::currency($jobSiteBudget->total_amount, config('app.currency'), config('app.locale')) }}
                                            </span>
                                            <x-ui.button
                                                variant="ghost"
                                                size="sm"
                                                href="{{ route('budgets.show', $jobSiteBudget->id) }}"
                                                icon="eye">
                                            </x-ui.button>
                                        @else
                                            <x-ui.button
                                                variant="secondary"
                                                size="sm"
                                                href="{{ route('job-sites.budgets.create', $jobSite->id) }}"
                                                icon="plus">
                                                Create
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>


    @include('livewire.project.partials.expense-modal')

    <!-- Change Order Modal -->
    <x-ui.modal name="change-order-modal" maxWidth="2xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                @if($changeOrderModalMode === 'view')
                    View Change Order
                @elseif($changeOrderModalMode === 'edit')
                    Edit Change Order
                @else
                    Add New Change Order
                @endif
            </h3>
        </div>

        <div class="p-6">
            @if($changeOrderModalMode === 'view')
                <!-- View Mode -->
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Title</label>
                            <p class="text-slate-900 dark:text-white">{{ $co_title }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Requested Date</label>
                            <p class="text-slate-900 dark:text-white">{{ $co_requested_date ? \Carbon\Carbon::parse($co_requested_date)->format('M d, Y') : '-' }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Location</label>
                        <p class="text-slate-900 dark:text-white">
                            @if($co_job_site_id)
                                @php $selectedJobSite = $jobSites->find($co_job_site_id); @endphp
                                {{ $selectedJobSite?->job_site_name ?? 'Unknown Job Site' }}
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                    Project (General)
                                </span>
                            @endif
                        </p>
                    </div>

                    @if($co_description)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
                            <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $co_description }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount</label>
                        <p class="font-medium {{ ($co_amount ?: 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ ($co_amount ?: 0) >= 0 ? '+' : '' }}{{ Number::currency($co_amount ?: 0, config('app.currency'), config('app.locale')) }}</p>
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
                            $changeOrderRecord = \App\Models\ChangeOrder::with('createdBy')->find($editingChangeOrder);
                        @endphp
                        @if($changeOrderRecord && $changeOrderRecord->createdBy)
                            <div class="pt-4 mt-4 border-t border-slate-200 dark:border-slate-700">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Created By</label>
                                        <p class="text-slate-900 dark:text-white">{{ $changeOrderRecord->createdBy->name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Created On</label>
                                        <p class="text-slate-900 dark:text-white">{{ $changeOrderRecord->created_at->format('M d, Y g:i A') }}</p>
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
                        wire:click="closeChangeOrderModal">
                        Close
                    </x-ui.button>
                </div>
            @else
                <!-- Create/Edit Form -->
                <form wire:submit="saveChangeOrder" class="space-y-6">
                    <!-- Location -->
                    <div>
                        <label for="co_job_site_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Location
                        </label>
                        <select
                            id="co_job_site_id"
                            wire:model="co_job_site_id"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="">Project (General)</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Leave as "Project (General)" for project-level change orders</p>
                        @error('co_job_site_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Title and Date -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="co_title" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Title <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="co_title"
                                wire:model="co_title"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Enter change order title"
                            >
                            @error('co_title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="co_requested_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Requested Date <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                id="co_requested_date"
                                wire:model="co_requested_date"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            >
                            @error('co_requested_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="co_description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Description
                        </label>
                        <textarea
                            id="co_description"
                            wire:model="co_description"
                            rows="4"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="Enter description (optional)"
                        ></textarea>
                        @error('co_description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="co_amount" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Amount <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-slate-500 dark:text-slate-400">$</span>
                            <input
                                type="number"
                                step="0.01"
                                id="co_amount"
                                wire:model="co_amount"
                                class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="0.00"
                            >
                        </div>
                        @error('co_amount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- File Upload -->
                    <div>
                        <label for="co_file" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Attach File
                        </label>
                        @if($existingFilePath && !$co_file)
                            <div class="mb-2 text-sm text-slate-600 dark:text-slate-400">
                                Current file:
                                <a href="{{ route('files.download', ['path' => $existingFilePath]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    Download
                                </a>
                            </div>
                        @endif
                        <input
                            type="file"
                            id="co_file"
                            wire:model="co_file"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Maximum file size: 10MB</p>
                        @error('co_file') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                        @if($co_file)
                            <div class="mt-2 text-sm text-green-600 dark:text-green-400">
                                New file selected: {{ $co_file->getClientOriginalName() }}
                            </div>
                        @endif
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="closeChangeOrderModal">
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary">
                            {{ $changeOrderModalMode === 'edit' ? 'Update' : 'Create' }}
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </x-ui.modal>

    <!-- Delete Project Confirmation Modal -->
    @if($showDeleteProjectModal)
        <x-ui.modal name="delete-project-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    Delete Project
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    Are you sure you want to delete <strong>{{ $deleteProjectData['name'] ?? $project->project_name }}</strong>?
                    This action <strong>cannot be undone</strong>.
                </p>

                @if(!empty($deleteProjectData))
                    @php
                        $hasRelated = ($deleteProjectData['job_sites'] ?? 0) > 0
                            || ($deleteProjectData['expenses'] ?? 0) > 0
                            || ($deleteProjectData['change_orders'] ?? 0) > 0
                            || ($deleteProjectData['daily_reports'] ?? 0) > 0
                            || ($deleteProjectData['purchase_orders'] ?? 0) > 0
                            || ($deleteProjectData['budgets'] ?? 0) > 0;
                    @endphp

                    @if($hasRelated)
                        <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                            <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">The following data will be permanently deleted:</p>
                            <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                                @if(($deleteProjectData['job_sites'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['job_sites'] }} Job Site(s)
                                    </li>
                                @endif
                                @if(($deleteProjectData['expenses'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['expenses'] }} Expense(s)
                                    </li>
                                @endif
                                @if(($deleteProjectData['change_orders'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['change_orders'] }} Change Order(s)
                                    </li>
                                @endif
                                @if(($deleteProjectData['daily_reports'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['daily_reports'] }} Daily Report(s)
                                    </li>
                                @endif
                                @if(($deleteProjectData['purchase_orders'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['purchase_orders'] }} Purchase Order(s)
                                    </li>
                                @endif
                                @if(($deleteProjectData['budgets'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['budgets'] }} Budget(s)
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif
                @endif

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDeleteProject"
                        icon="x">
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteProject"
                        icon="trash">
                        Delete Project
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif

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
                    Delete Job Site
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    Are you sure you want to delete <strong>{{ $deleteJobSiteData['name'] ?? '' }}</strong>?
                    This action <strong>cannot be undone</strong>.
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
                            <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">The following data will be permanently deleted:</p>
                            <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                                @if(($deleteJobSiteData['expenses'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['expenses'] }} Expense(s)
                                    </li>
                                @endif
                                @if(($deleteJobSiteData['change_orders'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['change_orders'] }} Change Order(s)
                                    </li>
                                @endif
                                @if(($deleteJobSiteData['daily_reports'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['daily_reports'] }} Daily Report(s)
                                    </li>
                                @endif
                                @if(($deleteJobSiteData['budgets'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteJobSiteData['budgets'] }} Budget(s)
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
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteJobSite"
                        icon="trash">
                        Delete Job Site
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('jobSiteAddressAutocomplete', (config) => ({
        autocomplete: null,
        country: config.country || 'US',

        async init() {
            if (!window.google || !window.google.maps) {
                return;
            }

            try {
                const { Autocomplete } = await google.maps.importLibrary("places");

                this.autocomplete = new Autocomplete(this.$refs.streetInput, {
                    componentRestrictions: { country: this.country.toLowerCase() },
                    fields: ['address_components', 'geometry', 'formatted_address'],
                    types: ['address']
                });

                this.autocomplete.addListener('place_changed', () => {
                    this.handlePlaceSelect();
                });
            } catch (error) {
                console.error('Error loading Google Places:', error);
            }
        },

        handlePlaceSelect() {
            const place = this.autocomplete.getPlace();

            if (!place.geometry) {
                return;
            }

            let streetNumber = '';
            let route = '';
            let city = '';
            let state = '';
            let postalCode = '';
            let neighborhood = '';

            for (const component of place.address_components) {
                const type = component.types[0];

                switch (type) {
                    case 'street_number':
                        streetNumber = component.long_name;
                        break;
                    case 'route':
                        route = component.long_name;
                        break;
                    case 'locality':
                        city = component.long_name;
                        break;
                    case 'administrative_area_level_2':
                        if (!city) city = component.long_name;
                        break;
                    case 'administrative_area_level_1':
                        state = component.short_name;
                        break;
                    case 'postal_code':
                        postalCode = component.long_name;
                        break;
                    case 'sublocality_level_1':
                    case 'sublocality':
                        neighborhood = component.long_name;
                        break;
                }
            }

            const street = streetNumber ? `${streetNumber} ${route}` : route;

            @this.set('street', street);
            @this.set('city', city);
            @this.set('state', state);
            @this.set('postal_code', postalCode);
            @this.set('neighborhood', neighborhood);
            @this.set('latitude', place.geometry.location.lat());
            @this.set('longitude', place.geometry.location.lng());
        }
    }));
});
</script>
@endpush
