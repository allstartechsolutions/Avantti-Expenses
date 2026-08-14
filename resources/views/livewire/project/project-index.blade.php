<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Projects') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Manage your projects') }}</p>
            </div>
            <div>
                <x-ui.button
                    variant="primary"
                    href="{{ route('projects.create') }}"
                    icon="plus">
                    {{ __('Add Project') }}
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
                    <label for="search" class="sr-only">{{ __('Search projects') }}</label>
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
                            placeholder="{{ __('Search by project name, client, contact...') }}"
                        >
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="statusFilter" class="sr-only">{{ __('Filter by status') }}</label>
                    <select
                        id="statusFilter"
                        wire:model.live="statusFilter"
                        class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                        <option value="">{{ __('All Statuses') }}</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Client Filter -->
                <div>
                    <label for="clientFilter" class="sr-only">{{ __('Filter by client') }}</label>
                    <select
                        id="clientFilter"
                        wire:model.live="clientFilter"
                        class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]">
                        <option value="">{{ __('All Clients') }}</option>
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
                        {{ __('Clear Filters') }}
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
                                {{ __('Project') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Client') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Contact') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Amount') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('# Job Sites') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Created') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Actions') }}
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
                                            <div class="text-sm font-medium text-slate-900 dark:text-white" title="{{ $project->client->company_name }}">
                                                {{ Str::limit($project->client->company_name, 30) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Contact -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $project->contact_person }}</div>
                                    @if($project->phone)
                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $project->formatted_phone }}</div>
                                    @endif
                                </td>

                                <!-- Amount -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ Number::currency($project->getAdjustedContractValue(), config('app.currency'), config('app.locale')) }}
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
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.icon-button
                                            variant="secondary"
                                            size="sm"
                                            href="{{ route('projects.overview', $project->id) }}"
                                            icon="eye"
                                            title="{{ __('View') }}" />
                                        <x-ui.icon-button
                                            variant="secondary"
                                            size="sm"
                                            href="{{ route('projects.edit', $project->id) }}"
                                            icon="edit"
                                            title="{{ __('Edit') }}" />
                                        <x-ui.icon-button
                                            variant="danger"
                                            size="sm"
                                            wire:click="confirmDeleteProject({{ $project->id }})"
                                            icon="trash"
                                            title="{{ __('Delete') }}" />
                                    </div>
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
                        {{ __('No projects found') }}
                    @else
                        {{ __('No projects yet') }}
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($search || $statusFilter || $clientFilter)
                        {{ __('Try adjusting your search or filters.') }}
                    @else
                        {{ __('Get started by creating a new project.') }}
                    @endif
                </p>
                @if(!$search && !$statusFilter && !$clientFilter)
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            href="{{ route('projects.create') }}"
                            icon="plus">
                            {{ __('Add Project') }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Delete Project Confirmation Modal -->
    @if($showDeleteModal)
        <x-ui.modal name="delete-project-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    {{ __('Delete Project') }}
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    {{ __('Are you sure you want to delete') }} <strong>{{ $deleteProjectData['name'] ?? '' }}</strong>?
                    {{ __('This action') }} <strong>{{ __('cannot be undone') }}</strong>.
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
                            <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">{{ __('The following data will be permanently deleted:') }}</p>
                            <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                                @if(($deleteProjectData['job_sites'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['job_sites'] }} {{ __('Job Site(s)') }}
                                    </li>
                                @endif
                                @if(($deleteProjectData['expenses'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['expenses'] }} {{ __('Expense(s)') }}
                                    </li>
                                @endif
                                @if(($deleteProjectData['change_orders'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['change_orders'] }} {{ __('Change Order(s)') }}
                                    </li>
                                @endif
                                @if(($deleteProjectData['daily_reports'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['daily_reports'] }} {{ __('Daily Report(s)') }}
                                    </li>
                                @endif
                                @if(($deleteProjectData['purchase_orders'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['purchase_orders'] }} {{ __('Purchase Order(s)') }}
                                    </li>
                                @endif
                                @if(($deleteProjectData['budgets'] ?? 0) > 0)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        {{ $deleteProjectData['budgets'] }} {{ __('Budget(s)') }}
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif
                @endif

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDelete"
                        icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteProject"
                        icon="trash">
                        {{ __('Delete Project') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
