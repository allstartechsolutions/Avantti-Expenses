<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Supplier</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Update supplier information</p>
            </div>
            <div>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('suppliers.index') }}"
                    icon="arrow-left">
                    Back to Suppliers
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <form wire:submit="updateSupplier" class="space-y-8">
        <!-- Supplier Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Supplier Information</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Basic supplier details</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Supplier Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Supplier Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        wire:model.live="name"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Enter supplier name"
                    >
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Email and Phone -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Email Address
                        </label>
                        <input
                            type="email"
                            id="email"
                            wire:model.live="email"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="supplier@example.com"
                        >
                        @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Phone Number
                        </label>
                        <input
                            type="tel"
                            id="phone"
                            wire:model.live="phone"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ config('app.country') === 'BR' ? '(11) 99999-9999' : '+1 (555) 123-4567' }}"
                        >
                        @error('phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Description
                    </label>
                    <textarea
                        id="description"
                        wire:model.live="description"
                        rows="3"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Notes about this supplier..."
                    ></textarea>
                    @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <!-- Address Information Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Address Information</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Supplier location details</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Street Address -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <label for="street" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Street Address
                        </label>
                        <input
                            type="text"
                            id="street"
                            wire:model.live="street"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ config('app.country') === 'BR' ? 'Rua Example, 123' : '123 Main Street' }}"
                        >
                        @error('street') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="address_2" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ config('app.country') === 'BR' ? 'Complement' : 'Address Line 2' }}
                        </label>
                        <input
                            type="text"
                            id="address_2"
                            wire:model.live="address_2"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="Suite, Apt, Unit, etc."
                        >
                        @error('address_2') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if(config('app.country') === 'BR')
                <!-- Neighborhood (Brazil only) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="neighborhood" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Neighborhood (Bairro)
                        </label>
                        <input
                            type="text"
                            id="neighborhood"
                            wire:model.live="neighborhood"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="Bairro"
                        >
                        @error('neighborhood') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
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
                </div>
                @else
                <!-- City (US) -->
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
                @endif

                <!-- State and Postal Code -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="state" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ config('app.country') === 'BR' ? 'State (UF)' : 'State' }}
                        </label>
                        <input
                            type="text"
                            id="state"
                            wire:model.live="state"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ config('app.country') === 'BR' ? 'SP' : 'CA' }}"
                            @if(config('app.country') === 'BR') maxlength="2" @endif
                        >
                        @error('state') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ config('app.country') === 'BR' ? 'CEP' : 'ZIP Code' }}
                        </label>
                        <input
                            type="text"
                            id="postal_code"
                            wire:model.live="postal_code"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ config('app.country') === 'BR' ? '01234-567' : '12345' }}"
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
                href="{{ route('suppliers.index') }}">
                Cancel
            </x-ui.button>
            <x-ui.button
                type="submit"
                variant="primary"
                icon="save"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50">
                <span wire:loading.remove>Update Supplier</span>
                <span wire:loading>Updating...</span>
            </x-ui.button>
        </div>
    </form>
</div>
