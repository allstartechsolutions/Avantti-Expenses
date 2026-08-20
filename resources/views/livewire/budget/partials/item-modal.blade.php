{{--
    Add / edit a budget cost code.

    A dialog rather than a sidebar panel: the list stays where it was, the form
    lands over it, and adding a run of codes never scrolls the page around.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2';
    $parentItem = $parentId ? $budget->items->find($parentId) : null;
@endphp

<x-ui.modal name="{{ \App\Livewire\Budget\BudgetShow::FORM_MODAL }}" maxWidth="2xl">
    <div
        x-data="{
            focusFirst() {
                setTimeout(() => this.$el.querySelector('[data-autofocus]')?.focus(), 120);
            },
        }"
        @modal-opened.window="if ($event.detail === '{{ \App\Livewire\Budget\BudgetShow::FORM_MODAL }}') focusFirst()"
        @cost-code-saved.window="focusFirst()">

        <form wire:submit="save">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ $editingItemId ? __('Edit Cost Code') : ($parentId ? __('Add Child Code') : __('Add Cost Code')) }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                        {{ $budget->name }} &middot; {{ $budget->location_name }}
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="closeForm"
                    aria-label="{{ __('Close') }}"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                @if($parentItem)
                    <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ __('Parent Code') }}</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                            {{ $parentItem->code }} - {{ $parentItem->name }}
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Code -->
                    <div>
                        <label for="item-code" class="{{ $label }}">
                            {{ __('Code') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="item-code"
                            data-autofocus
                            wire:model="code"
                            class="{{ $field }} font-mono"
                            placeholder="{{ __('e.g., 01, 01.1, A100') }}">
                        @error('code')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Name -->
                    <div class="sm:col-span-2">
                        <label for="item-name" class="{{ $label }}">
                            {{ __('Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="item-name"
                            wire:model="name"
                            class="{{ $field }}"
                            placeholder="{{ __('e.g., General Requirements') }}">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="item-description" class="{{ $label }}">{{ __('Description') }}</label>
                    <textarea
                        id="item-description"
                        wire:model="description"
                        rows="2"
                        class="{{ $field }}"
                        placeholder="{{ __('Optional description') }}"></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Budgeted Amount -->
                    <div>
                        <label for="item-amount" class="{{ $label }}">
                            {{ __('Budgeted Amount') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="item-amount"
                            wire:model="budgeted_amount"
                            step="0.01"
                            min="0"
                            class="{{ $field }}"
                            placeholder="0.00">
                        @error('budgeted_amount')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Sort Order -->
                    <div>
                        <label for="item-sort-order" class="{{ $label }}">{{ __('Sort Order') }}</label>
                        <input
                            type="number"
                            id="item-sort-order"
                            wire:model="sort_order"
                            min="0"
                            class="{{ $field }}">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Filled in for you — change it only to reorder.') }}
                        </p>
                        @error('sort_order')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 rounded-b-lg flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    wire:click="closeForm">
                    {{ __('Cancel') }}
                </x-ui.button>

                @unless($editingItemId)
                    <x-ui.button
                        type="button"
                        variant="outline"
                        wire:click="save(true)"
                        icon="plus">
                        {{ __('Save & Add Another') }}
                    </x-ui.button>
                @endunless

                <x-ui.button
                    type="submit"
                    variant="primary"
                    icon="check">
                    {{ $editingItemId ? __('Update') : __('Add') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</x-ui.modal>
