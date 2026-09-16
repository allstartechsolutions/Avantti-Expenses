@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2';
    $err = 'text-sm text-red-600 dark:text-red-400';
@endphp
@if($showFindingModal)
    <x-ui.modal name="finding-modal" :show="true" maxWidth="2xl">
        <form wire:submit="saveFinding">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Record a finding') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Something encountered during a maintenance. It stays open until a later maintenance, or a note, resolves it.') }}</p>
                </div>
                <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="cancelFinding" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label for="f-maintenance" class="{{ $label }}">{{ __('Found during') }} <span class="text-red-500">*</span></label>
                        <select id="f-maintenance" wire:model="f_maintenance_id" class="{{ $field }}">
                            <option value="">{{ __('Select a maintenance…') }}</option>
                            @foreach($findingTargets as $t)
                                <option value="{{ $t->id }}">{{ $t->title }} — {{ \App\Models\EquipmentMaintenance::statusLabel($t->status) }}@if($t->completed_date) ({{ $t->completed_date->appDate() }})@endif</option>
                            @endforeach
                        </select>
                        @error('f_maintenance_id') <span class="{{ $err }}">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="f-severity" class="{{ $label }}">{{ __('Severity') }}</label>
                        <select id="f-severity" wire:model="f_severity" class="{{ $field }}">
                            @foreach(\App\Models\EquipmentFinding::SEVERITIES as $s)
                                <option value="{{ $s }}">{{ \App\Models\EquipmentFinding::severityLabel($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="f-description" class="{{ $label }}">{{ __('What was found') }} <span class="text-red-500">*</span></label>
                    <textarea id="f-description" wire:model="f_description" rows="3" class="{{ $field }}"></textarea>
                    @error('f_description') <span class="{{ $err }}">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="{{ $label }}">{{ __('Photo') }}</label>
                    <x-ui.file-drop wire:model="f_photo" :multiple="false" accept=".jpg,.jpeg,.png,.webp" :label="__('Drop a photo here, or')" :hint="__('JPG, PNG or WebP, up to 10MB.')">
                        @error('f_photo') <p class="{{ $err }}">{{ $message }}</p> @enderror
                        @if($f_photo)
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">{{ $f_photo->getClientOriginalName() }}</span>
                                <x-ui.icon-button variant="ghost" size="sm" icon="trash" type="button" wire:click="clearFindingPhoto" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}" />
                            </div>
                        @endif
                    </x-ui.file-drop>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                <x-ui.button type="button" variant="secondary" wire:click="cancelFinding" icon="x">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="save" wire:loading.attr="disabled" wire:target="saveFinding,f_photo">{{ __('Record finding') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif

@if($showResolveModal)
    <x-ui.modal name="resolve-finding-modal" :show="true" maxWidth="lg">
        <form wire:submit="saveResolve" class="p-6 space-y-4">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Resolve finding') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Fixed outside a maintenance, or no longer an issue. To close it as part of a service, tick it when completing that maintenance instead.') }}</p>
            <div>
                <label for="r-notes" class="{{ $label }}">{{ __('How it was resolved') }} <span class="text-red-500">*</span></label>
                <textarea id="r-notes" wire:model="r_notes" rows="3" class="{{ $field }}"></textarea>
                @error('r_notes') <span class="{{ $err }}">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <x-ui.button type="button" variant="secondary" wire:click="cancelResolve" icon="x">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="check">{{ __('Resolve') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif
