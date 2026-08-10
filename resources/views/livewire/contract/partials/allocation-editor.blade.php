@if($allocationBudget && $allocationBudget->items_count > 0)
    <!-- Cost Code Allocation Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Cost Code Allocation') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Optional — split the contract amount across cost codes.') }}
                @if($allocationDefaultItem)
                    {{ __('Unallocated amounts go to') }} {{ $allocationDefaultItem->code }} - {{ $allocationDefaultItem->name }}.
                @else
                    {{ __('Unallocated amounts show as Unassigned.') }}
                @endif
            </p>
        </div>
        <div class="p-6 space-y-4">
            @error('allocations')
                <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                    {{ $message }}
                </div>
            @enderror

            @if(count($allocations) > 0)
                <div class="space-y-2">
                    @foreach($allocations as $index => $allocation)
                        <div class="flex items-center gap-3" wire:key="allocation-{{ $index }}-{{ $allocation['budget_item_id'] ?? 'none' }}">
                            <span class="flex-1 min-w-0 px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-md text-slate-900 dark:text-white truncate">
                                {{ $allocation['code_display'] }}
                            </span>
                            <div class="relative w-40 flex-shrink-0">
                                <span class="absolute left-3 top-2.5 text-slate-500 text-sm">$</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model.live.debounce.500ms="allocations.{{ $index }}.amount"
                                    placeholder="0.00"
                                    class="w-full pl-7 pr-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            </div>
                            <button
                                type="button"
                                wire:click="removeAllocation({{ $index }})"
                                title="{{ __('Remove') }}"
                                class="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        @error('allocations.' . $index . '.amount')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    @endforeach
                </div>
            @endif

            <!-- Add cost code search -->
            <div class="relative" x-data="{ open: false }" @click.away="open = false">
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="allocationSearch"
                        @focus="open = true"
                        @input="open = true"
                        placeholder="{{ __('Search cost code to add...') }}"
                        class="w-full px-3 py-2 border border-dashed border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                </div>
                @if($allocationItems->count() > 0)
                    <div x-show="open" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                        @foreach($allocationItems as $item)
                            <button type="button" wire:click="addAllocation({{ $item->id }})" @click="open = false" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->code }} - {{ $item->name }}</div>
                                @if($item->parent)
                                    <div class="text-xs text-slate-500">{{ $item->parent->code }} - {{ $item->parent->name }}</div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(count($allocations) > 0)
                <!-- Totals -->
                <div class="flex items-center justify-end gap-6 pt-2 border-t border-slate-200 dark:border-slate-700 text-sm">
                    <span class="text-slate-500 dark:text-slate-400">
                        {{ __('Allocated') }}:
                        <span class="font-semibold text-slate-900 dark:text-white">{{ Number::currency($this->allocatedTotal(), config('app.currency'), config('app.locale')) }}</span>
                    </span>
                    <span class="text-slate-500 dark:text-slate-400">
                        {{ __('Remaining') }}:
                        <span class="font-semibold {{ $this->allocationRemainder() < -0.009 ? 'text-red-600 dark:text-red-400' : ($this->allocationRemainder() > 0.009 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400') }}">
                            {{ Number::currency($this->allocationRemainder(), config('app.currency'), config('app.locale')) }}
                        </span>
                    </span>
                </div>
            @endif
        </div>
    </div>
@endif
