<x-project-layout :project="$project" active="overview" title="Project Details">
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
</x-project-layout>
