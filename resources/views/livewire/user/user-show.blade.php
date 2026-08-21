<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('User Details') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('View user information') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('users.index') }}"
                    icon="arrow-left">
                    {{ __('Back to Users') }}
                </x-ui.button>
                {{-- Their own permissions (F0). Held to `access.view`, not
                     `users.edit`: this hands out abilities. --}}
                @can('access.view')
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('users.access', $user->id) }}"
                        icon="eye">
                        {{ __('Access') }}
                    </x-ui.button>
                @endcan
                <x-ui.button
                    variant="primary"
                    href="{{ route('users.edit', $user->id) }}"
                    icon="edit">
                    {{ __('Edit User') }}
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

    <!-- Error Message -->
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Profile Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Profile Information') }}</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-6 mb-6">
                        <div class="flex-shrink-0">
                            <div class="h-20 w-20 rounded-full bg-[#3F5189] flex items-center justify-center text-white text-2xl font-medium">
                                {{ $user->initials() }}
                            </div>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $user->name }}</h2>
                            <p class="text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Full Name') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $user->name }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Email Address') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $user->email }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Phone Number') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $user->formatted_phone ?? __('Not provided') }}</p>
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Role') }}
                            </label>
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                {{ $user->role ? ucfirst($user->role->name) : __('No Role') }}
                            </span>
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Status') }}
                            </label>
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                                    'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300',
                                    'suspended' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                                ];
                                $statusColor = $statusColors[$user->status->value] ?? $statusColors['inactive'];
                            @endphp
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusColor }}">
                                {{ $user->status->label() }}
                            </span>
                        </div>

                        <!-- Created At -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Joined') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $user->created_at->format('F d, Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Access: what this person can actually reach -->
            @include('livewire.user.partials.access-panel')

            <!-- Account Details -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Account Details') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Email Verified -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Email Verified') }}
                            </label>
                            @if($user->email_verified_at)
                                <p class="text-green-600 dark:text-green-400">
                                    &#10003; {{ __('Verified on :date', ['date' => $user->email_verified_at->format('M d, Y')]) }}
                                </p>
                            @else
                                <p class="text-red-600 dark:text-red-400">{{ __('Not verified') }}</p>
                            @endif
                        </div>

                        <!-- Last Updated -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Last Updated') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $user->updated_at->format('F d, Y \a\t g:i A') }}</p>
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
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Quick Actions') }}</h3>
                </div>
                <div class="p-6 space-y-3">
                    <x-ui.button
                        variant="secondary"
                        class="w-full justify-center"
                        href="{{ route('users.edit', $user->id) }}"
                        icon="edit">
                        {{ __('Edit User') }}
                    </x-ui.button>

                    <x-ui.button
                        variant="secondary"
                        class="w-full justify-center"
                        wire:click="sendPasswordReset"
                        wire:loading.attr="disabled"
                        icon="mail">
                        <span wire:loading.remove>{{ __('Send Password Reset') }}</span>
                        <span wire:loading>{{ __('Sending...') }}</span>
                    </x-ui.button>
                </div>
            </div>

            <!-- Account Stats -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Account Stats') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('User ID') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $user->id }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Account Age') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $user->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
