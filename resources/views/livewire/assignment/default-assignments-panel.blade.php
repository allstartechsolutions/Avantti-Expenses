<div>
    @php
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
        $editable = $this->canEdit();
    @endphp

    @if (session()->has('assignment-defaults-message'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('assignment-defaults-message') }}
        </div>
    @endif

    <div class="{{ $card }}">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Default assignments') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                @if($contextType === \App\Models\DefaultAssignment::CONTEXT_GLOBAL)
                    {{ __('Who work falls to across the whole system. A project or a job site can name somebody else, and whoever approves the requisition can always pick a different person.') }}
                @elseif($contextType === \App\Models\DefaultAssignment::CONTEXT_PROJECT)
                    {{ __('Who work falls to on this project. Leave one blank to follow the system-wide setting; a job site under this project can still name somebody of its own.') }}
                @else
                    {{ __('Who work falls to on this job site. Leave one blank to follow the project.') }}
                @endif
            </p>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach(\App\Models\DefaultAssignment::ROLE_KEYS as $roleKey)
                @php
                    $row = $this->rows[$roleKey] ?? null;
                    $inheritedUser = $this->inherited[$roleKey] ?? null;
                    $candidates = $this->candidatesFor($roleKey);
                @endphp

                <div class="px-6 py-5" wire:key="default-{{ $roleKey }}">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 lg:pr-6">
                            <p class="font-medium text-slate-900 dark:text-white">
                                {{ \App\Models\DefaultAssignment::roleLabel($roleKey) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ \App\Models\DefaultAssignment::roleDescription($roleKey) }}
                            </p>

                            @if($row?->setBy)
                                <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                    {{ __('Last changed by :name on :date', [
                                        'name' => $row->setBy->name,
                                        'date' => $row->updated_at?->appDateTime(),
                                    ]) }}
                                </p>
                            @endif
                        </div>

                        <div class="w-full lg:w-96 shrink-0">
                            @if($candidates->isEmpty())
                                {{-- Designed, not blank: say what is missing and what to do. --}}
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-900/20">
                                    @php
                                        $needed = $roleKey === \App\Models\DefaultAssignment::REQUISITION_APPROVER
                                            ? __('the "Approve or reject" permission on Requisitions')
                                            : __('the "Create" permission on Quotations');
                                    @endphp
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">
                                        {{ __('Nobody here holds what this needs yet.') }}
                                    </p>
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                        @if($contextType === \App\Models\DefaultAssignment::CONTEXT_GLOBAL)
                                            {{ __('Give somebody :permission and they will appear here.', ['permission' => $needed]) }}
                                        @else
                                            {{ __('Add somebody to this team with :permission and they will appear here.', ['permission' => $needed]) }}
                                        @endif
                                    </p>
                                </div>
                            @else
                                <label for="default-{{ $roleKey }}" class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                    {{ __('Assigned to') }}
                                </label>

                                <div class="flex items-end gap-2">
                                    <select
                                        id="default-{{ $roleKey }}"
                                        wire:model="choices.{{ $roleKey }}"
                                        @disabled(! $editable)
                                        class="{{ $field }} disabled:opacity-60 disabled:cursor-not-allowed">
                                        <option value="">
                                            @if($roleKey === \App\Models\DefaultAssignment::REQUISITION_APPROVER && $contextType === \App\Models\DefaultAssignment::CONTEXT_GLOBAL)
                                                {{ __('Anybody who may approve') }}
                                            @elseif($contextType === \App\Models\DefaultAssignment::CONTEXT_GLOBAL)
                                                {{ __('Nobody — leave it unassigned') }}
                                            @else
                                                {{ __('Follow the level above') }}
                                            @endif
                                        </option>
                                        @foreach($candidates as $candidate)
                                            <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                        @endforeach
                                    </select>

                                    @if($editable)
                                        <x-ui.button variant="primary" wire:click="save('{{ $roleKey }}')" icon="save">
                                            {{ __('Save') }}
                                        </x-ui.button>
                                    @endif
                                </div>

                                @error('choices.'.$roleKey)
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                {{-- What actually happens today, spelled out rather than left to be inferred. --}}
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    @if(($choices[$roleKey] ?? '') !== '')
                                        {{ __('Set here.') }}
                                    @elseif($inheritedUser)
                                        {{ __('Follows :name, set higher up.', ['name' => $inheritedUser->name]) }}
                                    @elseif($roleKey === \App\Models\DefaultAssignment::REQUISITION_APPROVER)
                                        {{ __('Nobody is named, so a submitted requisition is e-mailed to everybody who may approve it here.') }}
                                    @elseif($contextType === \App\Models\DefaultAssignment::CONTEXT_GLOBAL)
                                        {{ __('Nothing is set, so requisitions arrive unassigned and wait in the unassigned list.') }}
                                    @else
                                        {{ __('Nothing is set here or above, so requisitions arrive unassigned and wait in the unassigned list.') }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @unless($editable)
            <div class="px-6 py-3 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('You can see these but not change them.') }}
                </p>
            </div>
        @endunless
    </div>
</div>
