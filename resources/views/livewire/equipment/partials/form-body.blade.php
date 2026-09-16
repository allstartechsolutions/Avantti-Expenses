{{--
    The equipment form body, shared by EquipmentCreate and EquipmentEdit.
    Expects: $options (types, ownerships, meterTypes, statuses, platePlaceholder),
             $supplierResults, $supplierCount, $editing (bool)
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2';
    $err = 'text-red-500 text-sm';
@endphp

<!-- Identity -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Identity') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('What it is and how you tell it apart from the others.') }}</p>
    </div>
    <div class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <label for="eq-name" class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input id="eq-name" type="text" wire:model="name" maxlength="255" class="{{ $field }}" placeholder="{{ __('e.g. Excavator CAT 320, Ford F-250 #2') }}">
                @error('name') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-type" class="{{ $label }}">{{ __('Type') }} <span class="text-red-500">*</span></label>
                <select id="eq-type" wire:model.live="equipment_type" class="{{ $field }}">
                    @foreach($options['types'] as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
                @error('equipment_type') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label for="eq-tag" class="{{ $label }}">{{ __('Asset tag') }}</label>
                <input id="eq-tag" type="text" wire:model="asset_tag" maxlength="60" class="{{ $field }}" placeholder="{{ __('Your own number') }}">
                @error('asset_tag') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-make" class="{{ $label }}">{{ __('Make') }}</label>
                <input id="eq-make" type="text" wire:model="make" maxlength="100" class="{{ $field }}">
                @error('make') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-model" class="{{ $label }}">{{ __('Model') }}</label>
                <input id="eq-model" type="text" wire:model="model" maxlength="100" class="{{ $field }}">
                @error('model') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-year" class="{{ $label }}">{{ __('Year') }}</label>
                <input id="eq-year" type="number" inputmode="numeric" min="1900" max="{{ now()->year + 1 }}" wire:model="year" class="{{ $field }}">
                @error('year') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="eq-serial" class="{{ $label }}">{{ __('Serial number') }}</label>
                <input id="eq-serial" type="text" wire:model="serial_number" maxlength="100" class="{{ $field }} font-mono">
                @error('serial_number') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-plate" class="{{ $label }}">{{ __('Plate') }}</label>
                <input id="eq-plate" type="text" wire:model="plate" maxlength="20" class="{{ $field }} font-mono uppercase" placeholder="{{ $options['platePlaceholder'] }}">
                @error('plate') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-vin" class="{{ $label }}">{{ __('VIN / chassis') }}</label>
                <input id="eq-vin" type="text" wire:model="vin" maxlength="40" class="{{ $field }} font-mono uppercase">
                @error('vin') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
</div>

<!-- Ownership and purchase -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Ownership and purchase') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Who it was bought from, for how much, and until when it is covered.') }}</p>
    </div>
    <div class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="eq-ownership" class="{{ $label }}">{{ __('Ownership') }} <span class="text-red-500">*</span></label>
                <select id="eq-ownership" wire:model="ownership" class="{{ $field }}">
                    @foreach($options['ownerships'] as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
                @error('ownership') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-ui.search-select
                    wire:model.live.debounce.300ms="supplierSearch"
                    :label="__('Supplier')"
                    :placeholder="__('Search by name…')"
                    :hint="__('Optional — who it was bought or rented from.')"
                    :results="$supplierResults"
                    :selectedId="$supplier_id"
                    :selectedLabel="$supplierSearch"
                    select="selectSupplier"
                    clear="clearSupplier"
                    :search="$supplierSearch"
                    :minChars="2"
                    :total="$supplierCount"
                    error="supplier_id"
                    :empty="__('No supplier matches. Check the spelling, or register the vendor first.')"
                    :unavailable="$supplierCount === 0 ? __('No suppliers are registered yet.') : null" />
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="eq-purchase-date" class="{{ $label }}">{{ __('Purchase date') }}</label>
                <x-ui.date-input id="eq-purchase-date" wire:model="purchase_date" class="{{ $field }}" />
                @error('purchase_date') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-purchase-cost" class="{{ $label }}">{{ __('Purchase cost') }}</label>
                <input id="eq-purchase-cost" type="number" step="0.01" min="0" inputmode="decimal" wire:model="purchase_cost" class="{{ $field }}" placeholder="0.00">
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Counted into the total cost of ownership.') }}</p>
                @error('purchase_cost') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="eq-warranty" class="{{ $label }}">{{ __('Warranty until') }}</label>
                <x-ui.date-input id="eq-warranty" wire:model="warranty_until" class="{{ $field }}" />
                @error('warranty_until') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
</div>

<!-- Meter and status -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Meter and status') }}</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('A meter lets maintenance be scheduled by distance or hours as well as by date.') }}</p>
    </div>
    <div class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="eq-meter" class="{{ $label }}">{{ __('Meter') }} <span class="text-red-500">*</span></label>
                <select id="eq-meter" wire:model.live="meter_type" class="{{ $field }}">
                    @foreach($options['meterTypes'] as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
                @error('meter_type') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            @if(! $editing)
                <div>
                    <label for="eq-initial-meter" class="{{ $label }}">{{ __('Starting reading') }}</label>
                    <input id="eq-initial-meter" type="number" step="0.1" min="0" inputmode="decimal" wire:model="initial_meter" class="{{ $field }}" @if($meter_type === 'none') disabled @endif>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('What the meter shows today. Later readings are logged on the equipment page.') }}</p>
                    @error('initial_meter') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="eq-initial-meter-at" class="{{ $label }}">{{ __('Reading date') }}</label>
                    <x-ui.date-input id="eq-initial-meter-at" wire:model="initial_meter_at" class="{{ $field }}" :disabled="$meter_type === 'none'" />
                    @error('initial_meter_at') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            @else
                <div>
                    <label for="eq-status" class="{{ $label }}">{{ __('Status') }} <span class="text-red-500">*</span></label>
                    <select id="eq-status" wire:model="status" class="{{ $field }}">
                        @foreach($options['statuses'] as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Retired and sold equipment leaves the lists and the reminders but keeps its history.') }}</p>
                    @error('status') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Photo and notes -->
<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Photo and notes') }}</h3>
    </div>
    <div class="p-6 space-y-6">
        <div>
            <label class="{{ $label }}">{{ __('Photo') }}</label>
            @if($editing && $equipment->photo_path && ! $removePhoto && ! $photo)
                <div class="mb-3 flex items-center gap-4">
                    <img src="{{ route('files.show', ['path' => $equipment->photo_path]) }}" alt="{{ $equipment->name }}" class="h-24 w-24 rounded-lg object-cover border border-slate-200 dark:border-slate-700">
                    <x-ui.button type="button" variant="secondary" size="sm" icon="trash" wire:click="$set('removePhoto', true)">{{ __('Remove photo') }}</x-ui.button>
                </div>
            @elseif($editing && $removePhoto)
                <p class="mb-3 text-sm text-amber-700 dark:text-amber-300">{{ __('The photo will be removed when you save.') }} <button type="button" class="underline" wire:click="$set('removePhoto', false)">{{ __('Keep it') }}</button></p>
            @endif
            <x-ui.file-drop
                wire:model="photo"
                :multiple="false"
                accept=".jpg,.jpeg,.png,.webp"
                :label="__('Drop a photo here, or')"
                :hint="__('JPG, PNG or WebP, up to 10MB.')">
                @error('photo') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @if($photo)
                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                        <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">{{ $photo->getClientOriginalName() }}</span>
                        <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">{{ \App\Services\DocumentSettings::formatBytes($photo->getSize()) }}</span>
                        <x-ui.icon-button variant="ghost" size="sm" icon="trash" type="button" wire:click="clearPhoto" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}" />
                    </div>
                @endif
            </x-ui.file-drop>
        </div>
        <div>
            <label for="eq-notes" class="{{ $label }}">{{ __('Notes') }}</label>
            <textarea id="eq-notes" wire:model="notes" rows="3" class="{{ $field }}" placeholder="{{ __('Anything the next person should know — attachments, quirks, where the keys are.') }}"></textarea>
            @error('notes') <span class="{{ $err }}">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
