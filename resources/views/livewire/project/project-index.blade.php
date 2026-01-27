<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Projects</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage your projects</p>
            </div>
            <div>
                <x-ui.button
                    variant="primary"
                    href="{{ route('projects.create') }}"
                    icon="plus">
                    Add Project
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

    <!-- Search and Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <label for="search" class="sr-only">Search projects</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            class="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md leading-5 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]"
                            placeholder="Search by project name, client, contact..."
                        >
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="statusFilter" class="sr-only">Filter by status</label>
                    <select
                        id="statusFilter"
                        wire:model.live="statusFilter"
                        class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Client Filter -->
                <div>
                    <label for="clientFilter" class="sr-only">Filter by client</label>
                    <select
                        id="clientFilter"
                        wire:model.live="clientFilter"
                        class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                        <option value="">All Clients</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Clear Filters -->
            @if($search || $statusFilter || $clientFilter)
                <div class="mt-4">
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        wire:click="clearFilters"
                        icon="x">
                        Clear Filters
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>

    <!-- Projects Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($projects->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Project
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Client
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Contact
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Amount
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                # Job Sites
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
                        @foreach($projects as $project)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <!-- Project -->
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $project->project_name }}
                                        </div>
                                        @if($project->full_address)
                                            <div class="text-sm text-slate-500 dark:text-slate-400">
                                                {{ Str::limit($project->full_address, 40) }}
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <!-- Client -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center">
                                                <span class="text-xs font-medium text-white">{{ $project->client->initials }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $project->client->company_name }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Contact -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $project->contact_person }}</div>
                                    @if($project->phone)
                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $project->phone }}</div>
                                    @endif
                                </td>

                                <!-- Amount -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ Number::currency($project->initial_amount, config('app.currency'), config('app.locale')) }}
                                    </div>
                                </td>

                                <!-- Job Sites Count -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $project->jobSites->count() }}
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusColors = [
                                            'created' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
                                            'in_progress' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-300',
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                        ];
                                        $statusColor = $statusColors[$project->status->value] ?? $statusColors['created'];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                        {{ $project->status->label() }}
                                    </span>
                                </td>

                                <!-- Created -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    {{ $project->created_at->format('M d, Y') }}
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <x-ui.view-edit-buttons
                                        :viewRoute="route('projects.overview', $project->id)"
                                        :editRoute="route('projects.edit', $project->id)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $projects->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">
                    @if($search || $statusFilter || $clientFilter)
                        No projects found
                    @else
                        No projects yet
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($search || $statusFilter || $clientFilter)
                        Try adjusting your search or filters.
                    @else
                        Get started by creating a new project.
                    @endif
                </p>
                @if(!$search && !$statusFilter && !$clientFilter)
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            href="{{ route('projects.create') }}"
                            icon="plus">
                            Add Project
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
