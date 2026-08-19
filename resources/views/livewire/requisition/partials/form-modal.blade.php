{{--
    Requisition form — full page, shared by the project and job-site levels.
    Expects: $contextName, $showJobSitePicker, $jobSites, $users,
             $catalogSuggestions, $budgetItemSuggestions
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
@endphp

<x-ui.modal name="requisition-form-modal" maxWidth="full" layer="top">
    <form wire:submit="saveRequisition('pending')" class="flex min-h-screen flex-col">
        <!-- Header -->
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $editingRequisitionId ? __('Edit Requisition') : __('New Requisition') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $contextName }}</p>
                </div>
                <button
                    type="button"
                    wire:click="closeFormModal"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- What is being asked for -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Request') }}</h3>

                        <div>
                            <label class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="req_title" placeholder="{{ __('e.g. Rebar for the 3rd floor slab') }}" class="{{ $field }}">
                            @error('req_title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Type') }} <span class="text-red-500">*</span></label>
                                <select wire:model.live="req_type" class="{{ $field }}">
                                    <option value="material">{{ __('Material') }}</option>
                                    <option value="service">{{ __('Service') }}</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $req_type === 'service'
                                        ? __('A service is quoted and then awarded as a contract.')
                                        : __('A material is quoted and then awarded as a purchase order.') }}
                                </p>
                                @error('req_type') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">{{ __('Priority') }} <span class="text-red-500">*</span></label>
                                <select wire:model="req_priority" class="{{ $field }}">
                                    <option value="low">{{ __('Low') }}</option>
                                    <option value="normal">{{ __('Normal') }}</option>
                                    <option value="urgent">{{ __('Urgent') }}</option>
                                </select>
                                @error('req_priority') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Needed By') }}</label>
                                <input type="date" wire:model="req_needed_by" class="{{ $field }}">
                                @error('req_needed_by') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                            @if($showJobSitePicker)
                                <div>
                                    <label class="{{ $label }}">{{ __('Location') }}</label>
                                    <select wire:model="req_job_site_id" class="{{ $field }}">
                                        <option value="">{{ __('Project (General)') }}</option>
                                        @foreach($jobSites as $js)
                                            <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('req_job_site_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Justification') }}</label>
                            <textarea wire:model="req_justification" rows="4" placeholder="{{ __('Why it is needed. This is what the approver reads.') }}" class="{{ $field }}"></textarea>
                            @error('req_justification') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Requested By') }}</h3>

                        <div>
                            <label class="{{ $label }}">{{ __('System User') }}</label>
                            <select wire:model="req_requested_by" class="{{ $field }}">
                                <option value="">{{ __('Not a system user') }}</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('req_requested_by') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Or a Name') }}</label>
                            <input type="text" wire:model="req_requested_by_name" placeholder="{{ __('e.g. the foreman who called it in') }}" class="{{ $field }}">
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Use this when the person on site has no login. The name is what the list and the detail view show.') }}</p>
                            @error('req_requested_by_name') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="{{ $card }} space-y-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Budget Item') }}</h3>

                        @if($req_budget_item_id)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                                <span class="text-sm text-slate-900 dark:text-white">{{ $req_budget_item_label }}</span>
                                <button type="button" wire:click="clearBudgetItem" class="text-xs text-red-600 dark:text-red-400 hover:underline">{{ __('Remove') }}</button>
                            </div>
                        @else
                            <input type="text" wire:model.live.debounce.300ms="budgetItemSearch" placeholder="{{ __('Search a budget code or name...') }}" class="{{ $field }}">
                            @if($budgetItemSuggestions->count() > 0)
                                <ul class="max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700">
                                    @foreach($budgetItemSuggestions as $budgetItem)
                                        <li>
                                            <button type="button" wire:click="selectBudgetItem({{ $budgetItem->id }})" class="w-full px-3 py-2 text-left text-sm text-slate-900 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                <span class="font-medium">{{ $budgetItem->code }}</span> — {{ $budgetItem->name }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif(strlen(trim($budgetItemSearch)) > 0)
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('No budget item matches that search.') }}</p>
                            @else
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Optional. Linking a budget item lets the quotation be compared against what was budgeted.') }}</p>
                            @endif
                        @endif
                    </div>

                    <div class="{{ $card }}">
                        <label class="{{ $label }}">{{ __('Attachments') }}</label>
                        <input
                            type="file"
                            wire:model="req_uploads"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png"
                            class="block w-full text-sm text-slate-500 dark:text-slate-400
                                file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                                file:text-sm file:font-medium file:bg-[#3F5189] file:text-white
                                hover:file:bg-[#4A5A96] file:cursor-pointer">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Specs, drawings or a photo of what is needed. PDF, JPG or PNG, up to 10MB each.') }}</p>
                        <div wire:loading wire:target="req_uploads" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Uploading...') }}</div>
                        @error('req_uploads.*') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- The items -->
                <div class="lg:col-span-3 space-y-4">
                    <div class="{{ $card }}">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Items') }}</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('What the vendors will price. Quantities only — prices come from the quotation.') }}</p>
                            </div>
                            <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="addItemRow">
                                {{ __('Add Line') }}
                            </x-ui.button>
                        </div>

                        <!-- Catalog picker -->
                        <div class="mb-4">
                            <input type="text" wire:model.live.debounce.300ms="catalogSearch" placeholder="{{ __('Add from the catalog — search an item...') }}" class="{{ $field }}">
                            @if($catalogSuggestions->count() > 0)
                                <ul class="mt-2 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700">
                                    @foreach($catalogSuggestions as $catalogItem)
                                        <li>
                                            <button type="button" wire:click="addCatalogItem({{ $catalogItem->id }})" class="w-full px-3 py-2 text-left text-sm text-slate-900 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                {{ $catalogItem->name }}
                                                @if($catalogItem->purchase_unit)
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">({{ $catalogItem->purchase_unit }})</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif(strlen(trim($catalogSearch)) >= 2)
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('No catalog item matches that search. Type the line by hand below.') }}</p>
                            @endif
                        </div>

                        @error('itemRows') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                        <div class="space-y-3">
                            @foreach($itemRows as $index => $row)
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4" wire:key="req-item-{{ $index }}">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-6 gap-3">
                                            <div class="sm:col-span-4">
                                                <label class="{{ $label }}">{{ __('Item') }} <span class="text-red-500">*</span></label>
                                                <input type="text" wire:model.blur="itemRows.{{ $index }}.item_name" placeholder="{{ __('e.g. CA-50 rebar 10mm') }}" class="{{ $field }}">
                                                @error('itemRows.'.$index.'.item_name') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">{{ __('Qty') }} <span class="text-red-500">*</span></label>
                                                <input type="number" step="0.01" min="0" wire:model="itemRows.{{ $index }}.quantity" class="{{ $field }}">
                                                @error('itemRows.'.$index.'.quantity') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="{{ $label }}">{{ __('Unit') }}</label>
                                                <input type="text" wire:model="itemRows.{{ $index }}.unit" placeholder="{{ __('kg, m, un') }}" class="{{ $field }}">
                                                @error('itemRows.'.$index.'.unit') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                            </div>
                                            <div class="sm:col-span-6">
                                                <label class="{{ $label }}">{{ __('Specification') }}</label>
                                                <textarea wire:model="itemRows.{{ $index }}.description" rows="2" placeholder="{{ __('Brand, grade, finish — anything the vendors must match.') }}" class="{{ $field }}"></textarea>
                                                @error('itemRows.'.$index.'.description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="removeItemRow({{ $index }})"
                                            class="mt-7 shrink-0 text-slate-400 hover:text-red-600 dark:hover:text-red-400"
                                            title="{{ __('Remove line') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    @if(($row['item_type'] ?? 'custom') === 'catalog')
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('From the catalog.') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
                            <span>{{ count($itemRows) }} {{ trans_choice('line|lines', count($itemRows)) }}</span>
                            <button type="button" wire:click="addItemRow" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('Add another line') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('A draft stays with you. Submitting sends it to a manager for approval.') }}
                </p>
                <div class="flex items-center gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="closeFormModal">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="button" variant="outline" wire:click="saveRequisition('draft')">{{ __('Save Draft') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save">{{ __('Submit for Approval') }}</x-ui.button>
                </div>
            </div>
        </div>
    </form>
</x-ui.modal>
