<div>
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $company ? 'Company Info' : 'Setup Company' }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ $company ? 'Update your company information' : 'Configure your company details' }}
                    </p>
                </div>
                <div>
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('dashboard') }}"
                        icon="arrow-left">
                        Back to Dashboard
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

        <!-- Company Form -->
        <form wire:submit="saveCompany" class="space-y-8">
            <!-- Basic Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Basic Information</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Company name and primary details</p>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Company Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Company Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            wire:model.live="name"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="Enter company name"
                        >
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Email and Website -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Email
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
                                Website
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

            <!-- Address Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Address Information</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Company location details</p>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Street Address -->
                    <div>
                        <label for="street" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Street Address
                        </label>
                        <input
                            type="text"
                            id="street"
                            wire:model.live="street"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="123 Business Street"
                        >
                        @error('street') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- City, State, Postal Code -->
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
                                State/Province
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
                                Postal Code
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

            <!-- Contact Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Contact Information</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Phone numbers and contact details</p>
                </div>
                <div class="p-6 space-y-6">
                    <!-- Phone Numbers -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                            <label for="mobile" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Mobile
                            </label>
                            <input
                                type="tel"
                                id="mobile"
                                wire:model.live="mobile"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="+1 (555) 987-6543"
                            >
                            @error('mobile') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="fax" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Fax
                            </label>
                            <input
                                type="tel"
                                id="fax"
                                wire:model.live="fax"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="+1 (555) 123-4568"
                            >
                            @error('fax') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logo Upload Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Company Logo</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Upload a logo image (max 2MB)</p>
                </div>
                <div class="p-6">
                    <div x-data="{ dragOver: false }"
                         class="relative">

                        @if($existingLogo && !$logoPreview)
                            <!-- Existing Logo -->
                            <div class="mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <img src="{{ asset('storage/' . $existingLogo) }}"
                                             alt="Current logo"
                                             class="w-20 h-20 object-contain bg-slate-50 dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600 p-2">
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">Current Logo</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Upload a new image to replace</p>
                                    </div>
                                    <div>
                                        <x-ui.button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            wire:click="removeExistingLogo">
                                            Remove
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($logoPreview)
                            <!-- New Logo Preview -->
                            <div class="mb-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <img src="{{ $logoPreview }}"
                                             alt="Logo preview"
                                             class="w-20 h-20 object-contain bg-slate-50 dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600 p-2">
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">New Logo Preview</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Image ready for upload</p>
                                    </div>
                                    <div>
                                        <x-ui.button
                                            type="button"
                                            variant="danger"
                                            size="sm"
                                            wire:click="removeLogo">
                                            Remove
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- File Upload Area -->
                        <div @dragover.prevent="dragOver = true"
                             @dragleave.prevent="dragOver = false"
                             @drop.prevent="dragOver = false; $refs.logoInput.files = $event.dataTransfer.files; $refs.logoInput.dispatchEvent(new Event('change', { bubbles: true }));"
                             :class="dragOver ? 'border-[#3F5189] bg-blue-50 dark:bg-blue-900/20' : 'border-slate-300 dark:border-slate-600'"
                             class="border-2 border-dashed rounded-lg p-8 text-center transition-colors">

                            <svg class="mx-auto h-12 w-12 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <div class="mt-4">
                                <label for="logo" class="cursor-pointer">
                                    <span class="mt-2 block text-sm font-medium text-slate-900 dark:text-white">
                                        Click to upload or drag and drop
                                    </span>
                                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                                        PNG, JPG, GIF up to 2MB
                                    </span>
                                </label>
                                <input
                                    type="file"
                                    id="logo"
                                    x-ref="logoInput"
                                    wire:model="logo"
                                    class="sr-only"
                                    accept="image/*"
                                >
                            </div>
                        </div>

                        @error('logo')
                            <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-4 py-6">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('dashboard') }}">
                    Cancel
                </x-ui.button>
                <x-ui.button
                    type="submit"
                    variant="primary"
                    icon="save"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50">
                    <span wire:loading.remove>{{ $company ? 'Update Company' : 'Save Company' }}</span>
                    <span wire:loading>{{ $company ? 'Updating...' : 'Saving...' }}</span>
                </x-ui.button>
            </div>
        </form>
</div>
