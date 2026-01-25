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

                                <!-- Initial Amount -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        Initial Amount
                                    </label>
                                    <p class="text-slate-900 dark:text-white font-medium">{{ Number::currency($project->initial_amount, config('app.currency'), config('app.locale')) }}</p>
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
                                                    <x-ui.view-edit-buttons
                                                        :viewRoute="route('jobsites.show', $jobSite->id)"
                                                        :editAction="'editJobSite(' . $jobSite->id . ')'" />
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
                    </div>
                    <x-ui.button
                        variant="primary"
                        icon="plus"
                        wire:click="openExpenseCreateModal">
                        Add Expense
                    </x-ui.button>
                </div>

                <!-- Summary Card -->
                <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
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

                <!-- Expenses List -->
                @if($expenses->count() > 0)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Item</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Location</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Unit Price</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total</th>
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
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($expense->jobSite)
                                                    <span class="text-sm text-slate-900 dark:text-white">{{ $expense->jobSite->job_site_name }}</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                                        Project (General)
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                                {{ number_format((float)$expense->quantity, 2) }} {{ $expense->getDisplayUnit() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                                {{ Number::currency($expense->unit_price, config('app.currency'), config('app.locale')) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900 dark:text-white">
                                                {{ Number::currency($expense->total_amount, config('app.currency'), config('app.locale')) }}
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
                                                    <button
                                                        wire:click="openExpenseEditModal({{ $expense->id }})"
                                                        class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                        title="Edit">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </button>
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
                                    wire:click="openExpenseCreateModal">
                                    Add Expense
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

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
                            <p class="text-slate-900 dark:text-white">{{ $expense_date ? \Carbon\Carbon::parse($expense_date)->format('M d, Y') : '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Location</label>
                            <p class="text-slate-900 dark:text-white">
                                @if($expense_job_site_id)
                                    {{ $jobSites->firstWhere('id', $expense_job_site_id)?->job_site_name ?? 'Unknown' }}
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">Project (General)</span>
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
                            <p class="text-slate-900 dark:text-white">{{ $expense_quantity }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Unit Price</label>
                            <p class="text-slate-900 dark:text-white">{{ Number::currency($expense_unit_price ?: 0, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Amount</label>
                            <p class="text-xl font-bold text-slate-900 dark:text-white">{{ Number::currency($expense_total_amount ?: 0, config('app.currency'), config('app.locale')) }}</p>
                        </div>
                    </div>

                    @if($expense_notes)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                            <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $expense_notes }}</p>
                        </div>
                    @endif

                    @if($existingReceiptPath)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Receipt</label>
                            <a href="{{ route('files.show', ['path' => $existingReceiptPath]) }}" target="_blank" class="text-[#3F5189] hover:underline">View Receipt</a>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end space-x-4 mt-6">
                    <x-ui.button type="button" variant="secondary" wire:click="closeExpenseModal">
                        Close
                    </x-ui.button>
                </div>
            @else
                <!-- Create/Edit Mode -->
                <form wire:submit.prevent="saveExpense" class="space-y-4">
                    <!-- Location Selector -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Location</label>
                        <select
                            wire:model="expense_job_site_id"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="">Project (General)</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Select "Project (General)" for project-level expenses or a specific job site.</p>
                    </div>

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
