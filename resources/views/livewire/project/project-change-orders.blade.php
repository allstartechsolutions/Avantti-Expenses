<x-project-layout :project="$project" active="change-orders" title="{{ __('Change Orders') }}">
    <div class="space-y-6">
        <!-- Header with Search, Filter and Add Button -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 flex flex-col sm:flex-row gap-4">
                <!-- Search Bar -->
                <div class="relative max-w-md">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="changeOrderSearch"
                        placeholder="{{ __('Search change orders...') }}"
                        class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <!-- Location Filter -->
                <select
                    wire:model.live="changeOrderLocationFilter"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="all">{{ __('All Locations') }}</option>
                    <option value="project">{{ __('Project (General)') }}</option>
                    @foreach($jobSites as $js)
                        <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button
                variant="primary"
                icon="plus"
                wire:click="openChangeOrderCreateModal">
                {{ __('Add Change Order') }}
            </x-ui.button>
        </div>

        <!-- Summary Card -->
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-white/80">{{ __('Total Change Orders') }}</p>
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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Title') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('File') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
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
                                                {{ __('Project (General)') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $changeOrder->{{ __('amount') }} < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
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
                                            <button wire:click="deleteChangeOrder({{ $changeOrder->id }})" wire:confirm="{{ __('Are you sure you want to delete this change order?') }}" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
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
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No change orders') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Get started by adding a change order.') }}</p>
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            icon="plus"
                            wire:click="openChangeOrderCreateModal">
                            {{ __('Add Change Order') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Change Order Modal -->
    <x-ui.modal name="change-order-modal" maxWidth="2xl">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                @if($changeOrderModalMode === 'view')
                    {{ __('View Change Order') }}
                @elseif($changeOrderModalMode === 'edit')
                    {{ __('Edit Change Order') }}
                @else
                    {{ __('Add New Change Order') }}
                @endif
            </h3>
        </div>

        <div class="p-6">
            @if($changeOrderModalMode === 'view')
                <!-- View Mode -->
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Title') }}</label>
                            <p class="text-slate-900 dark:text-white">{{ $co_title }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Requested Date') }}</label>
                            <p class="text-slate-900 dark:text-white">{{ $co_requested_date ? \Carbon\Carbon::parse($co_requested_date)->format('M d, Y') : '-' }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Location') }}</label>
                        <p class="text-slate-900 dark:text-white">
                            @if($co_job_site_id)
                                @php $selectedJobSite = $jobSites->find($co_job_site_id); @endphp
                                {{ $selectedJobSite?->job_site_name ?? __('Unknown Job Site') }}
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                    {{ __('Project (General)') }}
                                </span>
                            @endif
                        </p>
                    </div>

                    @if($co_description)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Description') }}</label>
                            <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $co_description }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Amount') }}</label>
                        <p class="font-medium {{ ($co_amount ?: 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ ($co_amount ?: 0) >= 0 ? '+' : '' }}{{ Number::currency($co_amount ?: 0, config('app.currency'), config('app.locale')) }}</p>
                    </div>

                    @if($existingFilePath)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Attached File') }}</label>
                            <a href="{{ route('files.download', ['path' => $existingFilePath]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                {{ __('Download File') }}
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
                                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Created By') }}</label>
                                        <p class="text-slate-900 dark:text-white">{{ $changeOrderRecord->createdBy->name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Created On') }}</label>
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
                        {{ __('Close') }}
                    </x-ui.button>
                </div>
            @else
                <!-- Create/Edit Form -->
                <form wire:submit="saveChangeOrder" class="space-y-6">
                    <!-- Location -->
                    <div>
                        <label for="co_job_site_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Location') }}
                        </label>
                        <select
                            id="co_job_site_id"
                            wire:model="co_job_site_id"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="">{{ __('Project (General)') }}</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Leave as "Project (General)" for project-level change orders') }}</p>
                        @error('co_job_site_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Title and Date -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="co_title" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Title') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="co_title"
                                wire:model="co_title"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Enter change order title') }}"
                            >
                            @error('co_title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="co_requested_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Requested Date') }} <span class="text-red-500">*</span>
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
                            {{ __('Description') }}
                        </label>
                        <textarea
                            id="co_description"
                            wire:model="co_description"
                            rows="4"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Enter description (optional)') }}"
                        ></textarea>
                        @error('co_description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="co_amount" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Amount') }} <span class="text-red-500">*</span>
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
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Use a negative amount for deductive change orders (e.g., -500).') }}</p>
                        @error('co_amount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- File Upload -->
                    <div>
                        <label for="co_file" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Attach File') }}
                        </label>
                        @if($existingFilePath && !$co_file)
                            <div class="mb-2 text-sm text-slate-600 dark:text-slate-400">
                                {{ __('Current file:') }}
                                <a href="{{ route('files.download', ['path' => $existingFilePath]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ __('Download') }}
                                </a>
                            </div>
                        @endif
                        <input
                            type="file"
                            id="co_file"
                            wire:model="co_file"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Maximum file size: 10MB') }}</p>
                        @error('co_file') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                        @if($co_file)
                            <div class="mt-2 text-sm text-green-600 dark:text-green-400">
                                {{ __('New file selected:') }} {{ $co_file->getClientOriginalName() }}
                            </div>
                        @endif
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end space-x-4 pt-4">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="closeChangeOrderModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary">
                            {{ $changeOrderModalMode === 'edit' ? __('Update') : __('Create') }}
                        </x-ui.button>
                    </div>
                </form>
            @endif
        </div>
    </x-ui.modal>
</x-project-layout>
