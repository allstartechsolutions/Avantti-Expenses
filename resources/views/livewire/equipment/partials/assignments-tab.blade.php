@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2';
    $canAssign = auth()->user()->can('equipment.assign');
@endphp
<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Where it is now') }}</h3>
                <p class="text-sm text-slate-700 dark:text-slate-300 mt-1">
                    @if($equipment->isAssigned())
                        @if($equipment->project || $equipment->jobSite)
                            <span class="font-medium">{{ $equipment->project?->project_name }}@if($equipment->jobSite) / {{ $equipment->jobSite->job_site_name }}@endif</span>
                        @endif
                        @if($equipment->responsible)<span class="text-slate-500 dark:text-slate-400"> &bull; {{ __('Responsible') }}: {{ $equipment->responsible->name }}</span>@endif
                        @if($equipment->assigned_at)<span class="text-slate-500 dark:text-slate-400"> &bull; {{ __('since') }} {{ $equipment->assigned_at->appDate() }}</span>@endif
                    @else
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Unassigned — in the yard, or nobody has said where it went.') }}</span>
                    @endif
                </p>
            </div>
            @if($canAssign && ! $equipment->isRetired())
                <div class="flex items-center gap-2">
                    <x-ui.button variant="primary" icon="arrow-right" wire:click="startAssign">{{ $equipment->isAssigned() ? __('Move') : __('Assign') }}</x-ui.button>
                    @if($equipment->isAssigned())
                        <x-ui.button variant="secondary" icon="arrow-left" wire:click="endAssignment" wire:confirm="{{ __('Send it back? It will be unassigned until it is sent somewhere else.') }}">{{ __('Send back') }}</x-ui.button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Where it has been') }}</h3>
        </div>
        @if($assignments->isEmpty())
            <p class="p-6 text-sm text-slate-500 dark:text-slate-400">{{ __('No assignment has been recorded yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Where') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Responsible') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('From') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('To') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Days') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Sent by') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($assignments as $stay)
                            <tr wire:key="assignment-{{ $stay->id }}" class="{{ $stay->isOpen() ? 'bg-green-50/40 dark:bg-green-900/10' : '' }}">
                                <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">
                                    @if($stay->project)
                                        <a href="{{ route('projects.overview', $stay->project_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $stay->project->project_name }}</a>@if($stay->jobSite) / <a href="{{ route('jobsites.overview', $stay->job_site_id) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $stay->jobSite->job_site_name }}</a>@endif
                                    @else
                                        {{ $stay->locationLabel() }}
                                    @endif
                                    @if($stay->isOpen())<span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Now') }}</span>@endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->responsible?->name ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->started_at->appDate() }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->ended_at?->appDate() ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ (int) $stay->started_at->diffInDays($stay->ended_at ?? now()) }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $stay->assignedBy?->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $stay->notes ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($showAssignModal)
        <x-ui.modal name="assign-equipment-modal" :show="true" maxWidth="2xl">
            <form wire:submit="assign">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Assign :name', ['name' => $equipment->name]) }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('A project, one of its job sites, a person — or any combination. The current stay ends the day this one starts.') }}</p>
                    </div>
                    <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="cancelAssign" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="assign-project" class="{{ $label }}">{{ __('Project') }}</label>
                            <select id="assign-project" wire:model.live="assign_project_id" class="{{ $field }}">
                                <option value="">{{ __('None') }}</option>
                                @foreach($this->assignProjects as $p)
                                    <option value="{{ $p->id }}">{{ $p->project_name }}</option>
                                @endforeach
                            </select>
                            @error('assign_project_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="assign-site" class="{{ $label }}">{{ __('Job Site') }}</label>
                            <select id="assign-site" wire:model="assign_job_site_id" class="{{ $field }}" @if($assign_project_id === '') disabled @endif>
                                <option value="">{{ __('Project (General)') }}</option>
                                @foreach($this->assignJobSites as $js)
                                    <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                                @endforeach
                            </select>
                            @error('assign_job_site_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="assign-person" class="{{ $label }}">{{ __('Responsible person') }}</label>
                            <select id="assign-person" wire:model="assign_responsible_user_id" class="{{ $field }}">
                                <option value="">{{ __('Nobody in particular') }}</option>
                                @foreach($this->assignPeople as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                            @error('assign_responsible_user_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="assign-date" class="{{ $label }}">{{ __('From') }} <span class="text-red-500">*</span></label>
                            <x-ui.date-input id="assign-date" wire:model="assign_started_at" class="{{ $field }}" />
                            @error('assign_started_at') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="assign-notes" class="{{ $label }}">{{ __('Notes') }}</label>
                        <input id="assign-notes" type="text" wire:model="assign_notes" maxlength="255" class="{{ $field }}" placeholder="{{ __('Optional — why it went, who drove it there…') }}">
                        @error('assign_notes') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelAssign" icon="x">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save">{{ __('Assign') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
