<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Category</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Update category details</p>
            </div>
            <x-ui.button
                variant="secondary"
                href="{{ route('catalog.categories.index') }}"
                icon="arrow-left">
                Back to Categories
            </x-ui.button>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <form wire:submit="save" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        wire:model="name"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="Enter category name">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Applicable Types -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Applicable Types <span class="text-red-500">*</span>
                    </label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center">
                            <input
                                type="checkbox"
                                wire:model="applicable_types"
                                value="product"
                                class="w-4 h-4 text-[#3F5189] border-slate-300 rounded focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700">
                            <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Products</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input
                                type="checkbox"
                                wire:model="applicable_types"
                                value="service"
                                class="w-4 h-4 text-[#3F5189] border-slate-300 rounded focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700">
                            <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Services</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input
                                type="checkbox"
                                wire:model="applicable_types"
                                value="rental"
                                class="w-4 h-4 text-[#3F5189] border-slate-300 rounded focus:ring-[#3F5189] dark:border-slate-600 dark:bg-slate-700">
                            <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Rentals</span>
                        </label>
                    </div>
                    @error('applicable_types')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Parent Category -->
                <div>
                    <label for="parent_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Parent Category
                    </label>
                    <select
                        id="parent_id"
                        wire:model="parent_id"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">None (Root Category)</option>
                        @foreach($parentCategories as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                        @endforeach
                    </select>
                    @error('parent_id')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Display Order -->
                <div>
                    <label for="display_order" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Display Order
                    </label>
                    <input
                        type="number"
                        id="display_order"
                        wire:model="display_order"
                        min="0"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="0">
                    @error('display_order')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Status
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-[#3F5189]/20 dark:peer-focus:ring-[#3F5189]/40 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-[#3F5189]"></div>
                        <span class="ms-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </label>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    href="{{ route('catalog.categories.index') }}">
                    Cancel
                </x-ui.button>
                <x-ui.button
                    type="submit"
                    variant="primary"
                    icon="check">
                    Save Changes
                </x-ui.button>
            </div>
        </form>
    </div>
</div>
