<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Edit Budget') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $budget->location_name }} &bull;
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $budget->project->project_name }}</span>
                </p>
            </div>
            <x-ui.button
                variant="secondary"
                href="{{ route('budgets.show', $budget->id) }}"
                icon="arrow-left">
                {{ __('Back to Budget') }}
            </x-ui.button>
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
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Budget Details') }}</h3>
                </div>
                <form wire:submit="save" class="p-6 space-y-6">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Budget Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            wire:model="name"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('e.g., Main Budget, Phase 1 Budget') }}">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Notes') }}
                        </label>
                        <textarea
                            id="notes"
                            wire:model="notes"
                            rows="4"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Optional notes about this budget') }}"></textarea>
                        @error('notes')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            href="{{ route('budgets.show', $budget->id) }}">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            icon="check">
                            {{ __('Save Changes') }}
                        </x-ui.button>
                    </div>
                </form>
            </div>

            <!-- Import from Template -->
            <div class="mt-6 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Import Cost Codes') }}</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                        {{ __('Import cost codes from a template. You can merge with existing codes or replace them entirely.') }}
                    </p>
                    @can('cost-codes.view')
                        <x-ui.button
                            variant="secondary"
                            wire:click="openImportModal"
                            icon="upload">
                            {{ __('Import from Template') }}
                        </x-ui.button>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ __('You do not have access to the cost code templates.') }}
                        </p>
                    @endcan
                </div>
            </div>

            <!-- Danger Zone -->
            @can('budget.delete', $budget)
                @unless($budget->isLocked())
                <div class="mt-6 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-red-200 dark:border-red-800">
                    <div class="px-6 py-4 border-b border-red-200 dark:border-red-800">
                        <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">{{ __('Danger Zone') }}</h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                            {{ __('Deleting this budget will remove all associated cost codes. This action cannot be undone.') }}
                        </p>
                        <x-ui.button
                            variant="danger"
                            wire:click="confirmDelete"
                            icon="trash">
                            {{ __('Delete Budget') }}
                        </x-ui.button>
                    </div>
                </div>
                @endunless
            @endcan
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Budget Summary -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Budget Summary') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Total Amount') }}</span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">
                            {{ Number::currency($budget->total_amount, config('app.currency'), config('app.locale')) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Cost Codes') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->items_count }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Location') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->location_name }}</span>
                    </div>
                    @if($budget->sourceTemplate)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Template') }}</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->sourceTemplate->name }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Created') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    @if($showImportModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeImportModal"></div>

                <!-- Center spacer for sm:block -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal panel -->
                <div class="relative inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Import from Template') }}</h3>
                        <button wire:click="closeImportModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <!-- Template Selection -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Select Template') }} <span class="text-red-500">*</span>
                            </label>
                            <select
                                wire:model="importTemplateId"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="">{{ __('Select a template...') }}</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}">
                                        {{ $template->name }}
                                        ({{ $template->costCodes->count() }} cost codes)
                                        {{ $template->is_default ? '★ Default' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('importTemplateId')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Import Mode -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Import Mode') }}
                            </label>
                            <select
                                wire:model="importMode"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="merge">{{ __('Merge (update existing, add new)') }}</option>
                                <option value="replace">{{ __('Replace (delete all existing first)') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                @if($importMode === 'merge')
                                    Existing codes will be updated (keeping amounts), new codes will be added with $0.00.
                                @else
                                    {{ __('All existing cost codes and amounts will be deleted before import.') }}
                                @endif
                            </p>
                        </div>

                        <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <p class="text-sm text-amber-800 dark:text-amber-300">
                                <strong>{{ __('Note:') }}</strong> Imported cost codes will have $0.00 budgeted amounts. You'll need to set the amounts after import.
                            </p>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end gap-3">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="closeImportModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            variant="primary"
                            wire:click="importTemplate"
                            icon="check">
                            {{ __('Import Cost Codes') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteConfirmation)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="cancelDelete"></div>

                <!-- Center spacer for sm:block -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal panel -->
                <div class="relative inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/20 flex items-center justify-center">
                                <x-ui.icon name="alert-triangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Delete Budget') }}</h3>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                    Are you sure you want to delete <strong>"{{ $budget->name }}"</strong>? This will permanently remove the budget and all {{ $budget->items_count }} cost codes. This action cannot be undone.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end gap-3">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="cancelDelete">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            variant="danger"
                            wire:click="deleteBudget"
                            icon="trash">
                            {{ __('Delete Budget') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
