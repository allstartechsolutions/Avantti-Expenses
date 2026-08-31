{{--
    Quotation form — full page, shared by the project and job-site levels.
    Expects: $contextName, $showJobSitePicker, $jobSites, $catalogSuggestions,
             $canAssign, $eligibleWorkers,
             $budgetItemSuggestions, $vendorSuggestions, $quotableRequisitions
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
@endphp

<x-ui.modal name="quotation-form-modal" maxWidth="full" layer="top">
    <form wire:submit="saveQuotation" class="flex min-h-screen flex-col">
        <!-- Header -->
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $editingQuotationId ? __('Edit Quotation') : __('New Quotation') }}
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
                <!-- The round -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Round') }}</h3>

                        <div>
                            <label class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="quo_title" placeholder="{{ __('e.g. Rebar for the 3rd floor slab') }}" class="{{ $field }}">
                            @error('quo_title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('From a Requisition') }}</label>
                            <select wire:model="quo_requisition_id" class="{{ $field }}">
                                <option value="">{{ __('Standalone — no requisition') }}</option>
                                @foreach($quotableRequisitions as $requisition)
                                    <option value="{{ $requisition->id }}">
                                        {{ $requisition->requisition_number }} — {{ $requisition->title }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Only approved requisitions appear here. Use the Quote button on a requisition to copy its items across.') }}
                            </p>
                            @error('quo_requisition_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Type') }} <span class="text-red-500">*</span></label>
                                <select wire:model.live="quo_type" class="{{ $field }}">
                                    <option value="material">{{ __('Material') }}</option>
                                    <option value="service">{{ __('Service') }}</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $quo_type === 'service'
                                        ? __('The winner becomes a contract with a payment schedule.')
                                        : __('The winner becomes a purchase order, which creates the expense on approval.') }}
                                </p>
                                @error('quo_type') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                            @if($showJobSitePicker)
                                <div>
                                    <label class="{{ $label }}">{{ __('Location') }}</label>
                                    <select wire:model="quo_job_site_id" class="{{ $field }}">
                                        <option value="">{{ __('Project (General)') }}</option>
                                        @foreach($jobSites as $js)
                                            <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('quo_job_site_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Needed On Site') }}</label>
                                <x-ui.date-input wire:model="quo_needed_by" class="{{ $field }}" />
                                @error('quo_needed_by') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">{{ __('Responses Due') }}</label>
                                <x-ui.date-input wire:model="quo_responses_due_at" class="{{ $field }}" />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('The deadline given to the vendors.') }}</p>
                                @error('quo_responses_due_at') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Scope Notes') }}</label>
                            <textarea wire:model="quo_description" rows="4" placeholder="{{ __('Conditions every vendor must quote against — delivery, site access, standards.') }}" class="{{ $field }}"></textarea>
                            @error('quo_description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    @if($canAssign)
                        <div class="{{ $card }} space-y-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Who works it') }}</h3>

                            @if($eligibleWorkers->isEmpty())
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Nobody here can work a quotation round yet, so this will start unassigned.') }}
                                </p>
                            @else
                                <div>
                                    <label class="{{ $label }}">{{ __('Owner') }}</label>
                                    <select wire:model="quo_assigned_to" class="{{ $field }}">
                                        <option value="">{{ __('Nobody yet') }}</option>
                                        @foreach($eligibleWorkers as $worker)
                                            <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('One person answerable for getting the prices in. Add more hands from the round itself once it exists.') }}
                                    </p>
                                    @error('quo_assigned_to') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="{{ $card }} space-y-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Budget Item') }}</h3>

                        @if($quo_budget_item_id)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                                <span class="text-sm text-slate-900 dark:text-white">{{ $quo_budget_item_label }}</span>
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
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Optional. Linking a budget item lets the offers be compared against what was budgeted.') }}</p>
                            @endif
                        @endif
                    </div>

                    <div class="{{ $card }}">
                        <label class="{{ $label }}">{{ __('Attachments') }}</label>
                        <x-ui.file-drop
                            wire:model="quo_new_uploads"
                            accept=".pdf,.jpg,.jpeg,.png"
                            :hint="__('Drawings or specs that go out with the request. PDF, JPG or PNG, up to 10MB each.')">

                            @error('quo_uploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @error('quo_new_uploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @error('quo_new_uploads.*') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            @if(count($quo_uploads) > 0)
                                <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                    @foreach($quo_uploads as $index => $file)
                                        <li wire:key="quo_uploads-{{ $index }}" class="px-3 py-2 flex items-center justify-between gap-3">
                                            <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                                {{ $file->getClientOriginalName() }}
                                            </span>
                                            <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                                {{ \App\Services\DocumentSettings::formatBytes($file->getSize()) }}
                                            </span>
                                            <x-ui.icon-button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                type="button"
                                                wire:click="discardQuotationUpload({{ $index }})"
                                                title="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                                aria-label="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                                class="hover:text-red-600 dark:hover:text-red-400" />
                                        </li>
                                    @endforeach
                                </ul>

                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ trans_choice(':count file goes up when this is saved.|:count files go up when this is saved.', count($quo_uploads), ['count' => count($quo_uploads)]) }}
                                </p>
                            @endif
                        </x-ui.file-drop>
                    </div>
                </div>

                <!-- The scope and the vendors -->
                <div class="lg:col-span-3 space-y-4">
                    <div class="{{ $card }}">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Scope') }}</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('One list, priced by every vendor — that is what makes the comparison fair.') }}</p>
                            </div>
                            <x-ui.button type="button" variant="secondary" size="sm" icon="plus" wire:click="addItemRow">
                                {{ __('Add Line') }}
                            </x-ui.button>
                        </div>

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
                                                @php $lastPaid = $catalogItem->priceHistory->first(); @endphp
                                                @if($lastPaid)
                                                    <span class="block text-xs text-green-600 dark:text-green-400">
                                                        {{ __('last paid :amount on :date', [
                                                            'amount' => Number::currency($lastPaid->new_cost, config('app.currency'), config('app.locale')),
                                                            'date' => $lastPaid->changed_at?->appDate(),
                                                        ]) }}
                                                    </span>
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
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4" wire:key="quo-item-{{ $index }}">
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
                                    @if(($row['requisition_item_id'] ?? null))
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Copied from the requisition.') }}</p>
                                    @elseif(($row['item_type'] ?? 'custom') === 'catalog')
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

                    <!-- Invited vendors -->
                    <div class="{{ $card }}">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Invited Vendors') }}</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    {{ __('Three is the Brazilian norm; two is the floor for awarding.') }}
                                </p>
                            </div>
                            <span class="text-sm font-medium {{ count($vendorRows) >= 3 ? 'text-green-600 dark:text-green-400' : (count($vendorRows) >= 2 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400') }}">
                                {{ __(':count invited', ['count' => count($vendorRows)]) }}
                            </span>
                        </div>

                        <div class="mb-4">
                            <input type="text" wire:model.live.debounce.300ms="vendorSearch" placeholder="{{ __('Search a vendor by name...') }}" class="{{ $field }}">
                            <label class="mt-2 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <input type="checkbox" wire:model.live="vendorSearchAll" class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                {{ $quo_type === 'service'
                                    ? __('Search every vendor, not only subcontractors')
                                    : __('Search every vendor, not only suppliers') }}
                            </label>
                            @if($vendorSuggestions->count() > 0)
                                <ul class="mt-2 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-700">
                                    @foreach($vendorSuggestions as $vendor)
                                        <li>
                                            <button type="button" wire:click="addVendorRow({{ $vendor->id }})" class="w-full px-3 py-2 text-left text-sm text-slate-900 dark:text-white hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                {{ $vendor->name }}
                                                @if($vendor->email || $vendor->contact_email)
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">({{ $vendor->email ?: $vendor->contact_email }})</span>
                                                @else
                                                    <span class="text-xs text-amber-600 dark:text-amber-400">{{ __('no e-mail on file') }}</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif(strlen(trim($vendorSearch)) >= 2)
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('No vendor matches that search.') }}</p>
                            @endif
                        </div>

                        @error('vendorRows') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                        @if(count($vendorRows) > 0)
                            <div class="space-y-3">
                                @foreach($vendorRows as $index => $vendorRow)
                                    <div class="flex flex-col sm:flex-row sm:items-end gap-3 rounded-lg border border-slate-200 dark:border-slate-700 p-3" wire:key="quo-vendor-{{ $index }}">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $vendorRow['vendor_name'] }}</p>
                                            @if(($vendorRow['status'] ?? 'invited') !== 'invited')
                                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Already answered — kept on the round.') }}</p>
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <label class="{{ $label }}">{{ __('E-mail for the request') }}</label>
                                            <input type="email" wire:model="vendorRows.{{ $index }}.email" placeholder="{{ __('vendor@example.com') }}" class="{{ $field }}">
                                            @error('vendorRows.'.$index.'.email') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="removeVendorRow({{ $index }})"
                                            class="shrink-0 self-center sm:self-end sm:mb-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400"
                                            title="{{ __('Remove vendor') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-500 dark:text-slate-400 italic">
                                {{ __('No vendor invited yet. Search above — the round cannot go out empty.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('The round is saved as a draft. Send it out from the detail view once the scope and the vendors are right.') }}
                </p>
                <div class="flex items-center gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="closeFormModal">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save">
                        {{ $editingQuotationId ? __('Save Changes') : __('Create Quotation') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </form>
</x-ui.modal>
