<x-project-layout :project="$project" active="jobsites" title="Job Sites">
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

                        <!-- Supervisor and Status -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="supervisor_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Supervisor') }}
                                </label>
                                <select
                                    id="supervisor_id"
                                    wire:model.live="supervisor_id"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="">{{ __('Select a supervisor') }}</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                @error('supervisor_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

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
                        </div>

                        <!-- Supervisor Change Note (only when editing and supervisor changed) -->
                        @if($editingJobSite)
                            <div
                                x-data="{ show: false, originalSupervisor: @js($editingJobSite ? \App\Models\JobSite::find($editingJobSite)?->supervisor_id : null) }"
                                x-effect="show = ($wire.supervisor_id || '') != (originalSupervisor || '')"
                                x-show="show"
                                x-cloak
                            >
                                <label for="supervisor_change_note" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Change Note') }}
                                </label>
                                <textarea
                                    id="supervisor_change_note"
                                    wire:model="supervisor_change_note"
                                    rows="2"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="{{ __('Optional reason for this change...') }}"
                                ></textarea>
                            </div>
                        @endif

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
                                        {{ __('Supervisor') }}
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
                                                {{ $jobSite->supervisor?->name ?? '-' }}
                                            </div>
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
</x-project-layout>
