<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Clients</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage your clients</p>
            </div>
            <div>
                <x-ui.button
                    variant="primary"
                    href="{{ route('clients.create') }}"
                    icon="plus">
                    Add Client
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
                    <label for="search" class="sr-only">Search clients</label>
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
                            placeholder="Search by company, contact, email, or phone..."
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

    <!-- Clients Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($clients->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Company
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Contact
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
                        @foreach($clients as $client)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center">
                                                <span class="text-sm font-medium text-white">{{ $client->initials }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $client->company_name }}
                                            </div>
                                            @if($client->website)
                                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                                    <a href="{{ $client->website }}" target="_blank" class="hover:text-[#3F5189] dark:hover:text-[#4A5A96]">
                                                        {{ $client->website }}
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $client->contact_name }}</div>
                                    @if($client->title)
                                        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $client->title }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $client->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $client->phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        @if($client->city || $client->state)
                                            {{ $client->city }}{{ $client->city && $client->state ? ', ' : '' }}{{ $client->state }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.button
                                            variant="secondary"
                                            size="sm"
                                            href="{{ route('clients.show', $client->id) }}"
                                            icon="eye">
                                            View
                                        </x-ui.button>
                                        <x-ui.button
                                            variant="secondary"
                                            size="sm"
                                            href="{{ route('clients.edit', $client->id) }}"
                                            icon="edit">
                                            Edit
                                        </x-ui.button>
                                        @if($client->projects_count > 0)
                                            <span title="Cannot delete: linked to {{ $client->projects_count }} project(s)">
                                                <x-ui.button
                                                    variant="danger"
                                                    size="sm"
                                                    icon="trash"
                                                    disabled>
                                                    Delete
                                                </x-ui.button>
                                            </span>
                                        @else
                                            <x-ui.button
                                                variant="danger"
                                                size="sm"
                                                wire:click="confirmDeleteClient({{ $client->id }})"
                                                icon="trash">
                                                Delete
                                            </x-ui.button>
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
                {{ $clients->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">
                    @if($search)
                        No clients found
                    @else
                        No clients yet
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($search)
                        Try adjusting your search terms.
                    @else
                        Get started by creating a new client.
                    @endif
                </p>
                @if(!$search)
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            href="{{ route('clients.create') }}"
                            icon="plus">
                            Add Client
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Delete Client Confirmation Modal -->
    @if($showDeleteModal)
        <x-ui.modal name="delete-client-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    Delete Client
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    Are you sure you want to delete <strong>{{ $deleteClientData['name'] ?? '' }}</strong>?
                    This action <strong>cannot be undone</strong>.
                </p>

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDeleteClient"
                        icon="x">
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteClient"
                        icon="trash">
                        Delete Client
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
