<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('My Profile') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Manage your account information and password') }}</p>
            </div>
            <div>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('dashboard') }}"
                    icon="arrow-left">
                    {{ __('Back to Dashboard') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <div class="space-y-8">
        <!-- Profile Information Card -->
        <form wire:submit="updateProfile">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Profile Information') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Update your name, email address and phone number') }}</p>
                </div>
                <div class="p-6 space-y-6">
                    @if (session()->has('profile-message'))
                        <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
                            {{ session('profile-message') }}
                        </div>
                    @endif

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            wire:model="name"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Your name') }}"
                        >
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Email and Phone -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Email') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                id="email"
                                wire:model="email"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="you@example.com"
                            >
                            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Phone') }}
                            </label>
                            <input
                                type="tel"
                                id="phone"
                                wire:model="phone" x-data x-phone-mask
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="+1 (555) 123-4567"
                            >
                            @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        icon="save"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50">
                        <span wire:loading.remove wire:target="updateProfile">{{ __('Save') }}</span>
                        <span wire:loading wire:target="updateProfile">{{ __('Saving...') }}</span>
                    </x-ui.button>
                </div>
            </div>
        </form>

        <!-- Change Password Card -->
        <form wire:submit="updatePassword"
              x-data="{
                  newPassword: '',
                  currentPassword: '',
                  confirmPassword: '',
                  get hasMinLength() { return this.newPassword.length >= 8 },
                  get hasLowercase() { return /[a-z]/.test(this.newPassword) },
                  get hasUppercase() { return /[A-Z]/.test(this.newPassword) },
                  get hasSymbol() { return /[^A-Za-z0-9]/.test(this.newPassword) },
                  get notSameAsOld() { return this.newPassword.length > 0 && this.newPassword !== this.currentPassword },
                  get passwordsMatch() { return this.confirmPassword.length > 0 && this.newPassword === this.confirmPassword },
                  get allPassed() { return this.hasMinLength && this.hasLowercase && this.hasUppercase && this.hasSymbol && this.notSameAsOld && this.passwordsMatch },
              }">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Change Password') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Ensure your account is using a secure password') }}</p>
                </div>
                <div class="p-6 space-y-6">
                    @if (session()->has('password-message'))
                        <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
                            {{ session('password-message') }}
                        </div>
                    @endif

                    <!-- Current Password -->
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Current Password') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            id="current_password"
                            wire:model="current_password"
                            x-model="currentPassword"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Enter current password') }}"
                        >
                        @error('current_password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- New Password and Confirmation -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('New Password') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="password"
                                id="password"
                                wire:model="password"
                                x-model="newPassword"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Enter new password') }}"
                            >
                            @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Confirm New Password') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="password"
                                id="password_confirmation"
                                wire:model="password_confirmation"
                                x-model="confirmPassword"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Confirm new password') }}"
                            >
                            @error('password_confirmation') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Password Requirements Checklist -->
                    <div x-show="newPassword.length > 0" x-transition class="rounded-lg border border-slate-200 dark:border-slate-700 p-4">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">{{ __('Password requirements:') }}</p>
                        <ul class="space-y-2">
                            <li class="flex items-center text-sm" :class="hasMinLength ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                <svg x-show="hasMinLength" class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="!hasMinLength" class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                                {{ __('At least 8 characters') }}
                            </li>
                            <li class="flex items-center text-sm" :class="hasLowercase ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                <svg x-show="hasLowercase" class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="!hasLowercase" class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                                {{ __('At least one lowercase letter') }}
                            </li>
                            <li class="flex items-center text-sm" :class="hasUppercase ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                <svg x-show="hasUppercase" class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="!hasUppercase" class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                                {{ __('At least one uppercase letter') }}
                            </li>
                            <li class="flex items-center text-sm" :class="hasSymbol ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                <svg x-show="hasSymbol" class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="!hasSymbol" class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                                {{ __('At least one symbol (!@#$%...)') }}
                            </li>
                            <li class="flex items-center text-sm" :class="notSameAsOld ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                <svg x-show="notSameAsOld" class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="!notSameAsOld" class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                                {{ __('Different from current password') }}
                            </li>
                            <li class="flex items-center text-sm" :class="passwordsMatch ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500'">
                                <svg x-show="passwordsMatch" class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="!passwordsMatch" class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>
                                {{ __('Passwords match') }}
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        icon="save"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        x-bind:disabled="!allPassed && newPassword.length > 0"
                        x-bind:class="{ 'opacity-50 cursor-not-allowed': !allPassed && newPassword.length > 0 }">
                        <span wire:loading.remove wire:target="updatePassword">{{ __('Update Password') }}</span>
                        <span wire:loading wire:target="updatePassword">{{ __('Updating...') }}</span>
                    </x-ui.button>
                </div>
            </div>
        </form>
    </div>
</div>
