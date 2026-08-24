{{--
    Add / edit one expense line, including its cost code.
    Shared by ExpenseCreate and ExpenseEdit.
    Expects: $budgetItems, $catalogItems, $amountsLocked
--}}
    <!-- Add/Edit Item Modal -->
    @if($showItemModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: true }" x-show="show">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeItemModal"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-lg shadow-xl" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                            {{ $editingItemIndex !== null ? __('Edit Item') : __('Add Item') }}
                        </h2>
                        <button type="button" wire:click="closeItemModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Cost Code Search -->
                        <div class="relative">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Cost Code') }}</label>
                            <div class="relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="budgetItemSearch"
                                    placeholder="{{ __('Search cost code...') }}"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @if($item_budget_item_id)
                                    <button type="button" wire:click="clearBudgetItem" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    </button>
                                @endif
                            </div>
                            @if($budgetItems->count() > 0)
                                <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                    @foreach($budgetItems as $bi)
                                        <button type="button" wire:click="selectBudgetItem({{ $bi->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $bi->code }} - {{ $bi->name }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            <p class="mt-1 text-xs text-slate-500">{{ __('Leave empty to use "Miscellaneous" (auto-created)') }}</p>
                        </div>

                        <!-- Item Type Toggle -->
                        <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-lg">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ $item_is_custom ? __('Custom Item') : __('From Catalog') }}
                            </span>
                            <button
                                type="button"
                                wire:click="toggleCustomItem"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $item_is_custom ? 'bg-[#3F5189]' : 'bg-slate-200' }}">
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $item_is_custom ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>

                        @if(!$item_is_custom)
                            <!-- Catalog Search -->
                            <div class="relative">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Search Catalog') }}</label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="catalogItemSearch"
                                        placeholder="{{ __('Type to search catalog...') }}"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @if($item_catalog_item_id)
                                        <button type="button" wire:click="clearCatalogItem" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    @endif
                                </div>
                                @if($catalogItems->count() > 0)
                                    <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                        @foreach($catalogItems as $ci)
                                            <button type="button" wire:click="selectCatalogItem({{ $ci->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $ci->name }}</div>
                                                <div class="text-xs text-slate-500">{{ Number::currency($ci->current_cost, config('app.currency'), config('app.locale')) }} / {{ $ci->usage_unit ?? $ci->purchase_unit ?? __('unit') }}</div>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Item Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Item Name') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model="item_name"
                                placeholder="{{ __('Enter item name') }}"
                                {{ !$item_is_custom && $item_catalog_item_id ? 'readonly' : '' }}
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white {{ !$item_is_custom && $item_catalog_item_id ? 'bg-slate-100 dark:bg-slate-900' : '' }}">
                            @error('item_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Description') }}</label>
                            <textarea
                                wire:model="item_description"
                                rows="2"
                                placeholder="{{ __('Optional description...') }}"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"></textarea>
                        </div>

                        <!-- Quantity, Unit, Price -->
                        <div class="grid grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Quantity') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    wire:model.live="item_quantity"
                                    @disabled($amountsLocked)
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('item_quantity') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Unit') }}</label>
                                <input
                                    type="text"
                                    wire:model="item_unit"
                                    placeholder="{{ __('Each, Hour...') }}"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Unit Price') }} <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-slate-500">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        wire:model.live="item_unit_price"
                                        @disabled($amountsLocked)
                                        class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                </div>
                                @error('item_unit_price') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Total') }}</label>
                                <div class="px-3 py-2 bg-slate-100 dark:bg-slate-900 rounded-md text-slate-900 dark:text-white font-semibold">
                                    {{ Number::currency($item_total, config('app.currency'), config('app.locale')) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeItemModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="primary" wire:click="saveItem">
                            {{ $editingItemIndex !== null ? __('Update Item') : __('Add Item') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
