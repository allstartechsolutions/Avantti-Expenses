<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Subcontractors') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Manage your subcontractors') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                @admin
                <x-ui.button
                    variant="secondary"
                    href="{{ route('vendors.duplicates') }}">
                    {{ __('Merge Duplicates') }}
                </x-ui.button>
                @endadmin
                <x-ui.button
                    variant="primary"
                    href="{{ route('subcontractors.create') }}"
                    icon="plus">
                    {{ __('Add Subcontractor') }}
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
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <!-- Search -->
                <div class="flex-1 max-w-md">
                    <label for="search" class="sr-only">{{ __('Search subcontractors') }}</label>
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
                            placeholder="{{ __('Search by company, contact, email, or phone...') }}"
                        >
                    </div>
                </div>

                <!-- Clear Filters -->
                @if($search)
                    <x-ui.button
                        variant="secondary"
                        wire:click="$set('search', '')"
                        icon="x">
                        {{ __('Clear Search') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Subcontractors Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($subcontractors->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Company') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Contact') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Email') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Phone') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Location') }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                {{ __('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($subcontractors as $subcontractor)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center">
                                                <span class="text-sm font-medium text-white">{{ $subcontractor->initials }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $subcontractor->company_name }}
                                            </div>
                                            <div class="mt-0.5 flex flex-wrap gap-1">
                                                @if($subcontractor->is_supplier)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ __('Supplier') }}</span>
                                                @endif
                                                @if($subcontractor->is_subcontractor)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Subcontractor') }}</span>
                                                @endif
                                            </div>
                                            @if($subcontractor->website)
                                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                                    <a href="{{ $subcontractor->website }}" target="_blank" class="hover:text-[#3F5189] dark:hover:text-[#4A5A96]">
                                                        {{ $subcontractor->website }}
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $subcontractor->contact_name }}</div>
                                    @if($subcontractor->title)
                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $subcontractor->title }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $subcontractor->contact_email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $subcontractor->formatted_phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        @if($subcontractor->city || $subcontractor->state)
                                            {{ $subcontractor->city }}{{ $subcontractor->city && $subcontractor->state ? ', ' : '' }}{{ $subcontractor->state }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.icon-button
                                            variant="secondary"
                                            size="sm"
                                            icon="eye"
                                            title="{{ __('View') }}"
                                            href="{{ route('subcontractors.show', $subcontractor->id) }}" />
                                        <x-ui.icon-button
                                            variant="secondary"
                                            size="sm"
                                            icon="edit"
                                            title="{{ __('Edit') }}"
                                            href="{{ route('subcontractors.edit', $subcontractor->id) }}" />
                                        @if(auth()->user()->is_admin)
                                            @php $linkedCount = $subcontractor->contracts_count + $subcontractor->payment_batches_count; @endphp
                                            @if($linkedCount > 0)
                                                <span title="Cannot delete: linked to {{ $subcontractor->contracts_count }} contract(s) and {{ $subcontractor->payment_batches_count }} payment batch(es)">
                                                    <x-ui.icon-button variant="danger" size="sm" icon="trash" disabled />
                                                </span>
                                            @else
                                                <x-ui.icon-button
                                                    variant="danger"
                                                    size="sm"
                                                    icon="trash"
                                                    title="{{ __('Delete') }}"
                                                    wire:click="confirmDeleteSubcontractor({{ $subcontractor->id }})" />
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $subcontractors->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">
                    @if($search)
                        {{ __('No subcontractors found') }}
                    @else
                        {{ __('No subcontractors yet') }}
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($search)
                        {{ __('Try adjusting your search terms.') }}
                    @else
                        {{ __('Get started by adding a new subcontractor.') }}
                    @endif
                </p>
                @if(!$search)
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            href="{{ route('subcontractors.create') }}"
                            icon="plus">
                            {{ __('Add Subcontractor') }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <x-ui.modal name="delete-subcontractor-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    {{ __('Delete Subcontractor') }}
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    @if($deleteSubcontractorData['is_dual'] ?? false)
                        {{ __('This company is also a supplier.') }}
                        {{ __('Only the subcontractor classification will be removed — the record, its documents and employees are kept.') }}
                    @else
                        Are you sure you want to delete <strong>{{ $deleteSubcontractorData['name'] ?? '' }}</strong>?
                        This action <strong>{{ __('cannot be undone') }}</strong>.
                    @endif
                </p>

                @if(!($deleteSubcontractorData['is_dual'] ?? false) && (($deleteSubcontractorData['documents'] ?? 0) > 0 || ($deleteSubcontractorData['employees'] ?? 0) > 0))
                    <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">{{ __('The following data will be permanently deleted:') }}</p>
                        <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                            @if(($deleteSubcontractorData['documents'] ?? 0) > 0)
                                <li>{{ $deleteSubcontractorData['documents'] }} document(s)</li>
                            @endif
                            @if(($deleteSubcontractorData['employees'] ?? 0) > 0)
                                <li>{{ $deleteSubcontractorData['employees'] }} employee(s)</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDeleteSubcontractor"
                        icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteSubcontractor"
                        icon="trash">
                        {{ __('Delete Subcontractor') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
