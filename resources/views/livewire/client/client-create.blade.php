<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Novo Cliente') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Adicionar novo cliente no sistema/p>
            </div>
            <div>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('clients.index') }}"
                    icon="arrow-left">
                    {{ __('Back to Clients') }}
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

    <!-- Create Form -->
    <form wire:submit="createClient" class="space-y-8">
        <!-- Company Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Company Information') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Client company details') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Company Name -->
                <div>
                    <label for="company_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Company Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="company_name"
                        wire:model.live="company_name"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('Enter company name') }}"
                    >
                    @error('company_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Email and Website -->
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
                            placeholder="company@example.com"
                        >
                        @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="website" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Website') }}
                        </label>
                        <input
                            type="url"
                            id="website"
                            wire:model.live="website"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="https://example.com"
                        >
                        @error('website') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Person Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contact Person') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Primary contact information') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Contact Name and Title -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="contact_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Contact Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="contact_name"
                            wire:model.live="contact_name"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Enter contact name') }}"
                        >
                        @error('contact_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Title/Position') }}
                        </label>
                        <input
                            type="text"
                            id="title"
                            wire:model.live="title"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('e.g., Project Manager') }}"
                        >
                        @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Phone -->
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
            </div>
        </div>

        <!-- Address Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Address Information') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Company location details') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Street Address -->
                <div>
                    <label for="street" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Street Address') }}
                    </label>
                    <input
                        type="text"
                        id="street"
                        wire:model.live="street"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('123 Business Street') }}"
                    >
                    @error('street') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- City, State, Postal Code -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="city" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('City') }}
                        </label>
                        <input
                            type="text"
                            id="city"
                            wire:model.live="city"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('City') }}"
                        >
                        @error('city') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="state" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('State') }}
                        </label>
                        <input
                            type="text"
                            id="state"
                            wire:model.live="state"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('State') }}"
                        >
                        @error('state') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Postal Code') }}
                        </label>
                        <input
                            type="text"
                            id="postal_code"
                            wire:model.live="postal_code"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="12345"
                        >
                        @error('postal_code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4 py-6">
            <x-ui.button
                variant="secondary"
                href="{{ route('clients.index') }}">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button
                type="submit"
                variant="primary"
                icon="save"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50">
                <span wire:loading.remove>{{ __('Create Client') }}</span>
                <span wire:loading>{{ __('Creating...') }}</span>
            </x-ui.button>
        </div>
    </form>
</div>
