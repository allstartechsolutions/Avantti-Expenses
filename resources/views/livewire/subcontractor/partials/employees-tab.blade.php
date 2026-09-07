{{--
    Employees tab of the subcontractor page.
    Expects: $employees (with person.employees.subcontractor, linkedBy, contracts_count),
             $linkedEmployees, and the component's employee form state.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2';
    $th = 'px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $canEdit = auth()->user()->can('vendors.edit');
    $canLink = auth()->user()->can('people.link');
    $canSeePeople = auth()->user()->can('people.view');
    $suggestions = $this->employeeSuggestions;
@endphp

<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Employees') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ trans_choice(':count employee|:count employees', $employees->count(), ['count' => $employees->count()]) }}
                    @if($linkedEmployees > 0)
                        · {{ trans_choice(':count also known at another company|:count also known at other companies', $linkedEmployees, ['count' => $linkedEmployees]) }}
                    @endif
                </p>
            </div>
            @if($canEdit)
                @if($showEmployeeForm)
                    <x-ui.button variant="secondary" wire:click="cancelEmployeeForm" icon="x">{{ __('Cancel') }}</x-ui.button>
                @else
                    <x-ui.button variant="primary" wire:click="startEmployee" icon="plus">{{ __('Add Employee') }}</x-ui.button>
                @endif
            @endif
        </div>

        <!-- Add / Edit Employee Form -->
        @if($showEmployeeForm)
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                <form wire:submit="saveEmployee" class="space-y-4">
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ $editing_employee_id ? __('Edit Employee') : __('New Employee') }}
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Name -->
                        <div>
                            <label for="employee_name" class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
                            <input type="text" id="employee_name" wire:model.live.debounce.400ms="employee_name" class="{{ $field }}" placeholder="{{ __('Employee full name') }}">
                            @error('employee_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Title -->
                        <div>
                            <label for="employee_title" class="{{ $label }}">{{ __('Title/Position') }}</label>
                            <input type="text" id="employee_title" wire:model="employee_title" class="{{ $field }}" placeholder="{{ __('e.g. Project Manager') }}">
                            @error('employee_title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="employee_phone" class="{{ $label }}">{{ __('Phone') }}</label>
                            <input type="text" id="employee_phone" wire:model.live.debounce.400ms="employee_phone" x-data x-phone-mask class="{{ $field }}" placeholder="{{ __('Phone number') }}">
                            @error('employee_phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="employee_email" class="{{ $label }}">{{ __('Email') }}</label>
                            <input type="email" id="employee_email" wire:model.live.debounce.400ms="employee_email" class="{{ $field }}" placeholder="email@example.com">
                            @error('employee_email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Tax id -->
                        <div>
                            <label for="employee_tax_id" class="{{ $label }}">{{ __('Tax ID') }}</label>
                            <input type="text" id="employee_tax_id" wire:model.live.debounce.400ms="employee_tax_id" class="{{ $field }}" placeholder="{{ config('app.country') === 'BR' ? __('CPF as presented to this company') : __('SSN or ITIN as presented to this company') }}">
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('The number this person gave this company. It may differ from what they gave another one — that is recorded, not corrected.') }}</p>
                            @error('employee_tax_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Period -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Started') }}</label>
                                <x-ui.date-input wire:model="employee_started_at" />
                                @error('employee_started_at') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">{{ __('Ended') }}</label>
                                <x-ui.date-input wire:model="employee_ended_at" />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Leave empty while they are still here.') }}</p>
                                @error('employee_ended_at') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="employee_notes" class="{{ $label }}">{{ __('Notes') }}</label>
                        <textarea id="employee_notes" wire:model="employee_notes" rows="2" class="{{ $field }}" placeholder="{{ __('Optional notes about this employee...') }}"></textarea>
                        @error('employee_notes') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Lookalikes at other companies -->
                    @if($suggestions->isNotEmpty())
                        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4 space-y-3">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-900 dark:text-amber-200">{{ __('Same person as someone already in the system?') }}</p>
                                    <p class="text-xs text-amber-800 dark:text-amber-300">{{ __('These records at other companies share details with what you typed. Pick one to link them as the same person — the records stay separate, one per company.') }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                @foreach($suggestions->take(6) as $match)
                                    @php $candidate = $match['employee']; $chosen = $employee_link_to === $candidate->id; @endphp
                                    <button type="button"
                                        wire:click="chooseEmployeeLink({{ $candidate->id }})"
                                        class="w-full text-left rounded-md border px-3 py-2 flex flex-col sm:flex-row sm:items-center gap-2 transition {{ $chosen ? 'border-[#3F5189] bg-white dark:bg-slate-800 ring-2 ring-[#3F5189]' : 'border-amber-200 dark:border-amber-800 bg-white/60 dark:bg-slate-800/60 hover:bg-white dark:hover:bg-slate-800' }}">
                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border {{ $chosen ? 'border-[#3F5189] bg-[#3F5189] text-white' : 'border-slate-300 dark:border-slate-600' }}">
                                                @if($chosen)<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>@endif
                                            </span>
                                            <div class="min-w-0">
                                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $candidate->name }}</span>
                                                @if($candidate->title)<span class="text-sm text-slate-500 dark:text-slate-400"> · {{ $candidate->title }}</span>@endif
                                                <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">
                                                    {{ __('at :company', ['company' => $candidate->subcontractor?->company_name]) }}
                                                    @if($candidate->person && $candidate->siblings()->isNotEmpty())
                                                        · {{ __('also at :companies', ['companies' => $candidate->siblings()->map(fn ($s) => $s->subcontractor?->company_name)->filter()->join(', ')]) }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($match['reasons'] as $reason)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $reason === 'tax_id' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">
                                                    {{ \App\Models\SubcontractorEmployee::matchReasonLabel($reason) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                            @if($employee_link_to)
                                <div>
                                    <label for="employee_link_reason" class="{{ $label }}">{{ __('Why are these the same person?') }}</label>
                                    <input type="text" id="employee_link_reason" wire:model="employee_link_reason" class="{{ $field }}" placeholder="{{ __('e.g. Same foreman, confirmed on site') }}">
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Optional, but it is what the next person will read when they wonder who decided this.') }}</p>
                                    @error('employee_link_reason') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            @endif
                            @error('employee_link_to') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <!-- Submit -->
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                        <x-ui.button type="button" variant="secondary" wire:click="cancelEmployeeForm">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon="save" wire:loading.attr="disabled" wire:loading.class="opacity-50" wire:target="saveEmployee">
                            <span wire:loading.remove wire:target="saveEmployee">
                                @if($editing_employee_id)
                                    {{ $employee_link_to ? __('Save and Link') : __('Save Changes') }}
                                @else
                                    {{ $employee_link_to ? __('Add and Link') : __('Add Employee') }}
                                @endif
                            </span>
                            <span wire:loading wire:target="saveEmployee">{{ __('Saving...') }}</span>
                        </x-ui.button>
                    </div>
                </form>
            </div>
        @endif

        <!-- Employees List -->
        <div class="p-6">
            @if($employees->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead>
                            <tr>
                                <th class="{{ $th }}">{{ __('Name') }}</th>
                                <th class="{{ $th }}">{{ __('Title') }}</th>
                                <th class="{{ $th }}">{{ __('Phone') }}</th>
                                <th class="{{ $th }}">{{ __('Email') }}</th>
                                <th class="{{ $th }}">{{ __('Tax ID') }}</th>
                                <th class="{{ $th }}">{{ __('Period') }}</th>
                                <th class="{{ $th }}">{{ __('Also at') }}</th>
                                <th class="{{ $th }}">{{ __('Contracts') }}</th>
                                <th class="{{ $th }}">{{ __('Notes') }}</th>
                                <th class="{{ $th }} text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($employees as $employee)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-4 py-4">
                                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $employee->name }}</span>
                                        @if(! $employee->isCurrent())
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Former') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4"><span class="text-sm text-slate-900 dark:text-white">{{ $employee->title ?? '—' }}</span></td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if($employee->phone)
                                            <a href="tel:{{ $employee->phone }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $employee->formatted_phone }}</a>
                                        @else
                                            <span class="text-sm text-slate-500 dark:text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        @if($employee->email)
                                            <a href="mailto:{{ $employee->email }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $employee->email }}</a>
                                        @else
                                            <span class="text-sm text-slate-500 dark:text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap"><span class="text-sm text-slate-900 dark:text-white font-mono">{{ $employee->tax_id ?? '—' }}</span></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                        @if($employee->started_at || $employee->ended_at)
                                            {{ $employee->started_at?->appDate() ?? '…' }} – {{ $employee->ended_at?->appDate() ?? __('present') }}
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        @if($employee->isLinked())
                                            @php $siblings = $employee->siblings(); @endphp
                                            @if($canSeePeople)
                                                <a href="{{ route('people.show', $employee->person) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            @else
                                                <span class="text-slate-900 dark:text-white">
                                            @endif
                                                @if($siblings->isNotEmpty())
                                                    {{ $siblings->map(fn ($s) => $s->subcontractor?->company_name)->filter()->join(', ') }}
                                                @else
                                                    {{ __('Linked') }}
                                                @endif
                                            @if($canSeePeople)</a>@else</span>@endif
                                            @if($employee->person->distinctTaxIds()->count() > 1)
                                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300" title="{{ __('This person has presented more than one tax id.') }}">{{ __('Tax ids differ') }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-900 dark:text-white">{{ $employee->contracts_count }}</td>
                                    <td class="px-4 py-4">
                                        <span class="text-sm text-slate-500 dark:text-slate-400" @if($employee->notes) title="{{ $employee->notes }}" @endif>{{ $employee->notes ? \Illuminate\Support\Str::limit($employee->notes, 40) : '—' }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1">
                                            @if($canEdit)
                                                <x-ui.icon-button icon="edit" variant="ghost" size="sm" wire:click="editEmployee({{ $employee->id }})" title="{{ __('Edit') }}" />
                                            @endif
                                            @if($canLink)
                                                <x-ui.button variant="outline" size="sm" wire:click="startLink({{ $employee->id }})">{{ __('Link') }}</x-ui.button>
                                                @if($employee->isLinked())
                                                    <x-ui.button variant="ghost" size="sm" wire:click="unlinkEmployee({{ $employee->id }})" wire:confirm="{{ __('Unlink this record from the person? The other companies\' records are kept; only this one stops being counted as the same person.') }}">{{ __('Unlink') }}</x-ui.button>
                                                @endif
                                            @endif
                                            @if($canEdit)
                                                <x-ui.icon-button icon="trash" variant="danger" size="sm" wire:click="deleteEmployee({{ $employee->id }})" wire:confirm="{{ __('Are you sure you want to delete this employee? Any contracts linked to them will be unlinked.') }}" title="{{ __('Delete') }}" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No employees yet') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Add employees to keep track of this subcontractor\'s contacts.') }}
                        @if($canLink)
                            {{ __('If somebody moved here from another company, the form will notice and offer to link the two records.') }}
                        @endif
                    </p>
                    @if($canEdit && !$showEmployeeForm)
                        <div class="mt-6">
                            <x-ui.button variant="primary" wire:click="startEmployee" icon="plus">{{ __('Add Employee') }}</x-ui.button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
