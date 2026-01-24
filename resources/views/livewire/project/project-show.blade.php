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
    </div>
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
