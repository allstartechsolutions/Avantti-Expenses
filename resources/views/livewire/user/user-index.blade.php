<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Users</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage system users</p>
            </div>
            <div>
                <x-ui.button
                    variant="primary"
                    href="{{ route('users.create') }}"
                    icon="plus">
                    Add User
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

    <!-- Filters Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div class="md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Search
                    </label>
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Search by name, email, or phone..."
                    >
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="statusFilter" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Status
                    </label>
                    <select
                        id="statusFilter"
                        wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                    >
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <!-- Role Filter -->
                <div>
                    <label for="roleFilter" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Role
                    </label>
                    <select
                        id="roleFilter"
                        wire:model.live="roleFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                    >
                        <option value="">All Roles</option>
                        @foreach(\App\Models\Role::all() as $role)
                            <option value="{{ $role->id }}">{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Clear Filters -->
            @if($search || $statusFilter || $roleFilter)
                <div class="mt-4">
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        wire:click="clearFilters">
                        Clear Filters
                    </x-ui.button>
                </div>
            @endif
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-600">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                            User
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                            Contact
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                            Role
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                            Joined
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <!-- User -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-[#3F5189] flex items-center justify-center text-white font-medium">
                                            {{ $user->initials() }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $user->name }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-900 dark:text-white">{{ $user->email }}</div>
                                @if($user->phone)
                                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ $user->phone }}</div>
                                @endif
                            </td>

                            <!-- Role -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                    {{ $user->role ? ucfirst($user->role->name) : 'No Role' }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $statusColors = [
                                        'active' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                        'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300',
                                        'suspended' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                    ];
                                    $statusColor = $statusColors[$user->status->value] ?? $statusColors['inactive'];
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                    {{ $user->status->label() }}
                                </span>
                            </td>

                            <!-- Joined -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                {{ $user->created_at->format('M d, Y') }}
                            </td>

                            <!-- Actions -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <x-ui.view-edit-buttons
                                    :viewRoute="route('users.show', $user->id)"
                                    :editRoute="route('users.edit', $user->id)" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="h-12 w-12 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                    <p class="text-slate-500 dark:text-slate-400 text-sm">
                                        @if($search || $statusFilter || $roleFilter)
                                            No users found matching your filters.
                                        @else
                                            No users yet. Create your first user to get started.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter && !$roleFilter)
                                        <div class="mt-4">
                                            <x-ui.button
                                                variant="primary"
                                                href="{{ route('users.create') }}"
                                                icon="plus">
                                                Add User
                                            </x-ui.button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- Results Info -->
    <div class="mt-4 text-sm text-slate-500 dark:text-slate-400">
        Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users
    </div>
</div>
