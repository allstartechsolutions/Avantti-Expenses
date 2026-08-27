<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Edit User') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Update user information') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('users.show', $user->id) }}"
                    icon="arrow-left">
                    {{ __('Back to User') }}
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
        <!-- Edit Form -->
        <div class="lg:col-span-2">
            <form wire:submit="updateUser" class="space-y-6">
                <!-- Basic Information Card -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Basic Information') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('User details and contact information') }}</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Full Name') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="name"
                                wire:model.live="name"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Enter full name') }}"
                            >
                            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email and Phone -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Email Address') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    wire:model.live="email"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="user@example.com"
                                >
                                @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Phone Number') }}
                                </label>
                                <input
                                    type="tel"
                                    id="phone"
                                    wire:model.live="phone" x-data x-phone-mask
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="+1 (555) 123-4567"
                                >
                                @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            {{-- The firm somebody belongs to, when it is not
                                 this one. Blank for staff; an external
                                 projetista signs as their practice. --}}
                            <div>
                                <label for="company_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Company') }}
                                </label>
                                <input
                                    type="text"
                                    id="company_name"
                                    wire:model="company_name"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="{{ __('Their firm, if they are not staff here') }}"
                                >
                                @error('company_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role and Status Card -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Role & Status') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Assign role and set account status') }}</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Role and Status -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="role_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Role') }} <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="role_id"
                                    wire:model.live="role_id"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                >
                                    <option value="">{{ __('Select a role') }}</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}">{{ $role->getLabel() }}</option>
                                    @endforeach
                                </select>
                                @error('role_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Status') }} <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="status"
                                    wire:model.live="status"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                >
                                    @foreach($statuses as $statusOption)
                                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                                    @endforeach
                                </select>
                                @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        {{-- Which projects this person can reach. Normally it
                             follows the role; this is the override for one
                             person, and it says what the role currently says
                             so the choice is not made blind. --}}
                        <div class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-700">
                            <label for="accessScope" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Which projects and job sites can they see?') }}
                            </label>

                            @if($user->is_guest)
                                <div class="px-3 py-2 rounded-lg bg-purple-50 border border-purple-200 text-purple-800 text-sm dark:bg-purple-900/20 dark:border-purple-800 dark:text-purple-300">
                                    {{ __('This is a guest: they only ever see the projects they were added to, and that cannot be changed here.') }}
                                </div>
                            @else
                                <select id="accessScope" wire:model.live="accessScope"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="">{{ __('Follow their role (:scope)', ['scope' => __($user->role?->access_scope?->label() ?? __('Every project and job site'))]) }}</option>
                                    <option value="company">{{ __('Every project and job site') }}</option>
                                    <option value="assigned">{{ __('Only the ones they are added to') }}</option>
                                </select>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Leave it following the role unless this one person needs to differ from everybody else holding it.') }}
                                </p>

                                @unless(\App\Services\AbilityCatalog::isSwept('project'))
                                    <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                                        {{ __('Recorded but not enforced yet: the project screens have not been converted, so every project is still listed to everybody.') }}
                                    </p>
                                @endunless
                                @error('accessScope') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 py-6">
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('users.show', $user->id) }}">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        icon="save"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50">
                        <span wire:loading.remove>{{ __('Update User') }}</span>
                        <span wire:loading>{{ __('Updating...') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <!-- User Info -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Current User') }}</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="flex-shrink-0">
                            <div class="h-16 w-16 rounded-full bg-[#3F5189] flex items-center justify-center text-white text-xl font-medium">
                                {{ $user->initials() }}
                            </div>
                        </div>
                        <div>
                            <h4 class="text-lg font-medium text-slate-900 dark:text-white">{{ $user->name }}</h4>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('User ID:') }}</span>
                            <span class="text-slate-900 dark:text-white">#{{ $user->id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Joined:') }}</span>
                            <span class="text-slate-900 dark:text-white">{{ $user->created_at->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Reset -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Password') }}</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        {{ __("Send a password reset link to the user's email address.") }}
                    </p>
                    <x-ui.button
                        variant="secondary"
                        class="w-full justify-center"
                        wire:click="sendPasswordReset"
                        wire:loading.attr="disabled"
                        icon="mail">
                        <span wire:loading.remove wire:target="sendPasswordReset">{{ __('Send Password Reset') }}</span>
                        <span wire:loading wire:target="sendPasswordReset">{{ __('Sending...') }}</span>
                    </x-ui.button>
                </div>
            </div>

            <!-- Warning -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                            {{ __('Changes will affect user access') }}
                        </h3>
                        <p class="mt-2 text-sm text-yellow-700 dark:text-yellow-400">
                            {{ __("Changing the role or status will immediately update the user's access permissions.") }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
