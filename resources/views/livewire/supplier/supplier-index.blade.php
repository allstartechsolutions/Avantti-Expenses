<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Suppliers</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage your suppliers</p>
            </div>
            <div class="flex items-center space-x-3">
                @admin
                @if(\App\Models\ModuleAccess::isEnabled('projects'))
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('vendors.duplicates') }}">
                        {{ __('Merge Duplicates') }}
                    </x-ui.button>
                @endif
                @endadmin
                <x-ui.button
                    variant="primary"
                    href="{{ route('suppliers.create') }}"
                    icon="plus">
                    Add Supplier
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

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Search and Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <!-- Search -->
                <div class="flex-1 max-w-md">
                    <label for="search" class="sr-only">Search suppliers</label>
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
                            placeholder="Search by name, email, phone, or city..."
                        >
                    </div>
                </div>

                <!-- Clear Filters -->
                @if($search)
                    <x-ui.button
                        variant="secondary"
                        wire:click="$set('search', '')"
                        icon="x">
                        Clear Search
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Suppliers Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($suppliers->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Supplier
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Email
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Phone
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Location
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($suppliers as $supplier)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center">
                                                <span class="text-sm font-medium text-white">{{ strtoupper(substr($supplier->name, 0, 2)) }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $supplier->name }}
                                            </div>
                                            <div class="mt-0.5 flex flex-wrap gap-1">
                                                @if($supplier->is_supplier)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ __('Supplier') }}</span>
                                                @endif
                                                @if($supplier->is_subcontractor)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Subcontractor') }}</span>
                                                @endif
                                            </div>
                                            @if($supplier->description)
                                                <div class="text-sm text-slate-500 dark:text-slate-400 truncate max-w-xs">
                                                    {{ Str::limit($supplier->description, 50) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $supplier->email ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $supplier->formatted_phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        @if($supplier->city || $supplier->state)
                                            {{ $supplier->city }}{{ $supplier->city && $supplier->state ? ', ' : '' }}{{ $supplier->state }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.view-edit-buttons
                                            :viewRoute="route('suppliers.show', $supplier->id)"
                                            :editRoute="route('suppliers.edit', $supplier->id)" />
                                        @admin
                                        <x-ui.button
                                            variant="danger"
                                            size="sm"
                                            wire:click="deleteSupplier({{ $supplier->id }})"
                                            wire:confirm="{{ $supplier->is_subcontractor ? __('This company is also a subcontractor. Only the supplier classification will be removed — the record is kept. Continue?') : __('Are you sure you want to delete this supplier?') }}"
                                            icon="trash">
                                            Delete
                                        </x-ui.button>
                                        @endadmin
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $suppliers->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">
                    @if($search)
                        No suppliers found
                    @else
                        No suppliers yet
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($search)
                        Try adjusting your search terms.
                    @else
                        Get started by creating a new supplier.
                    @endif
                </p>
                @if(!$search)
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            href="{{ route('suppliers.create') }}"
                            icon="plus">
                            Add Supplier
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
