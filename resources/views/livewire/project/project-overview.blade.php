<x-project-layout :project="$project" active="overview" :title="__('Project Details')">
    <x-slot:actions>
        <x-ui.button
            variant="danger"
            wire:click="confirmDeleteProject"
            icon="trash">
            {{ __('Delete') }}
        </x-ui.button>
    </x-slot:actions>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Project Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Project Information') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Project Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Project Name') }}
                            </label>
                            <p class="text-slate-900 dark:text-white font-medium">{{ $project->project_name }}</p>
                        </div>

                        <!-- Client -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Client') }}
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
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                {{ __('Base') }}: {{ Number::currency($project->getContractValue(), config('app.currency'), config('app.locale')) }}
                                @if ($project->amount_source?->value === 'from_jobsites')
                                    · {{ __('from job sites') }}
                                @endif
                            </p>
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
                                {{ __('Created On') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->created_at->format('F d, Y') }}</p>
                        </div>

                        <!-- Created By -->
                        @if($project->createdBy)
                            <div>
                                <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    {{ __('Created By') }}
                                </label>
                                <p class="text-slate-900 dark:text-white">{{ $project->createdBy->name }}</p>
                            </div>
                        @endif

                        <!-- Project Manager -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Project Manager') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->projectManager?->name ?? __('Not assigned') }}</p>
                        </div>
                    </div>

                    <!-- Description -->
                    @if($project->description)
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Description') }}
                            </label>
                            <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $project->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contact Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contact Information') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Person -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Contact Person') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->contact_person }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Phone Number') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->formatted_phone ?? __('Not provided') }}</p>
                        </div>

                        <!-- Email -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Email Address') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->email }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Project Address') }}</h3>
                </div>
                <div class="p-6">
                    @if($project->full_address)
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Full Address') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->full_address }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Street -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Street Address') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->street ?? __('Not provided') }}</p>
                        </div>

                        <!-- Address Line 2 -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Address Line 2') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->address_2 ?? __('Not provided') }}</p>
                        </div>

                        @if(config('app.country') === 'BR')
                        <!-- Neighborhood (Brazil only) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Neighborhood (Bairro)') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->neighborhood ?? __('Not provided') }}</p>
                        </div>
                        @endif

                        <!-- City -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('City') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->city ?? __('Not provided') }}</p>
                        </div>

                        <!-- State -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('State') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->state ?? __('Not provided') }}</p>
                        </div>

                        <!-- Postal Code -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Postal Code') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $project->postal_code ?? __('Not provided') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Open action items (meetings module) -->
            <x-open-tasks-card :project="$project" />

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Quick Actions') }}</h3>
                </div>
                <div class="p-6 space-y-3">
                    <x-ui.button
                        variant="secondary"
                        class="w-full justify-center"
                        href="{{ route('projects.edit', $project->id) }}"
                        icon="edit">
                        {{ __('Edit Project') }}
                    </x-ui.button>

                    @if($project->email)
                        <a href="mailto:{{ $project->email }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            {{ __('Send Email') }}
                        </a>
                    @endif

                    @if($project->phone)
                        <a href="tel:{{ $project->phone }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            {{ __('Call Contact') }}
                        </a>
                    @endif
                </div>
            </div>

            <!-- Project Stats -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Project Information') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Project ID') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $project->id }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Created') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $project->created_at->diffForHumans() }}</span>
                    </div>
                    @if($project->created_at != $project->updated_at)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Last Updated') }}</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $project->updated_at->diffForHumans() }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

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
                    {{ __('Delete Project') }}
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    {{ __('Are you sure you want to delete') }} <strong>{{ $deleteProjectData['name'] ?? $project->project_name }}</strong>?
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
                        wire:click="cancelDeleteProject"
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
</x-project-layout>
