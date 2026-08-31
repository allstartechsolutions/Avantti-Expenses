@php
    $isBR = config('app.country') === 'BR';
    $input = 'mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
    $backUrl = $jobSite
        ? route('jobsites.rfis', $jobSite)
        : route('projects.rfis', $project);
@endphp

<div>
    <x-ui.breadcrumb :items="[
        ['label' => __('Projects'), 'url' => route('projects.index')],
        ['label' => $project->project_name, 'url' => route('projects.overview', $project)],
        ['label' => __('RFIs'), 'url' => $backUrl],
        ['label' => $this->isEditing ? $rfi->number : __('collaboration.label.new_rfi')],
    ]" />

    <form wire:submit="save" class="max-w-5xl space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <h1 class="font-semibold text-slate-900 dark:text-white">
                    {{ $this->isEditing ? __('collaboration.label.edit_rfi', ['number' => $rfi->number]) : __('collaboration.label.new_rfi') }}
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.formal_question_put_designer_owner') }}
                </p>

                @if($this->isEditing && $rfi->isClosed())
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                        {{ __('collaboration.help.rfi_closed_subject_question_frozen') }}
                    </div>
                @endif
            </div>

            <div class="px-5 py-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                @if(! $jobSite)
                    <div>
                        <label class="{{ $label }}">{{ __('Location') }}</label>
                        <select wire:model="job_site_id" class="{{ $input }}">
                            <option value="">{{ __('Project (General)') }}</option>
                            @foreach($jobSites as $site)
                                <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                            @endforeach
                        </select>
                        @error('job_site_id') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="{{ $label }}">{{ __('Priority') }}</label>
                    <select wire:model="priority" class="{{ $input }}">
                        @foreach(\App\Models\Rfi::priorityOptions() as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('priority') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $label }}">{{ __('Subject') }}</label>
                    <input type="text" wire:model="subject" class="{{ $input }}"
                        placeholder="{{ __('collaboration.label.what_question_about_line') }}">
                    @error('subject') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="{{ $label }}">{{ __('collaboration.label.question') }}</label>
                    <textarea wire:model="question" rows="6" class="{{ $input }}"
                        placeholder="{{ __('collaboration.help.set_out_question_fully_what') }}"></textarea>
                    @error('question') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('collaboration.label.discipline') }}</label>
                    <select wire:model="discipline" class="{{ $input }}">
                        <option value="">{{ __('Not set') }}</option>
                        @foreach($disciplines as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('discipline') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                {{-- Both columns exist on every install; the screen shows the one
                     this country uses. Presentation, never business logic. --}}
                @if($isBR)
                    <div>
                        <label class="{{ $label }}">{{ __('collaboration.label.prancha_revisao') }}</label>
                        <input type="text" wire:model="drawing_ref" class="{{ $input }}" placeholder="{{ __('collaboration.placeholder.e_g_arq_04') }}">
                        @error('drawing_ref') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label class="{{ $label }}">{{ __('collaboration.label.spec_section') }}</label>
                        <input type="text" wire:model="spec_section" class="{{ $input }}" placeholder="{{ __('collaboration.placeholder.e_g_08_41') }}">
                        @error('spec_section') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="{{ $label }}">{{ __('collaboration.label.waiting_2') }}</label>
                    <select wire:model="ball_in_court_id" class="{{ $input }}">
                        <option value="">{{ __('collaboration.label.nobody') }}</option>
                        @foreach($assignableUsers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @if(empty($assignableUsers))
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('collaboration.help.nobody_been_added_project_there') }}
                        </p>
                    @endif
                    @error('ball_in_court_id') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('collaboration.label.due') }}</label>
                    <x-ui.date-input wire:model="due_date" class="{{ $input }}" />
                    @error('due_date') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Impact. Only offered to somebody who may see it — otherwise saving
             the form would silently clear flags they were never shown. --}}
        @if($this->canSeeImpact)
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.impact') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.help.flag_question_may_cost_money') }}
                    </p>
                </div>

                <div class="px-5 py-5 space-y-5">
                    <div>
                        <x-ui.toggle wire:model.live="cost_impact" :checked="$cost_impact"
                            label="{{ __('collaboration.label.may_cost_impact') }}" />

                        @if($cost_impact)
                            <div class="mt-3 max-w-xs">
                                <label class="{{ $label }}">{{ __('collaboration.label.estimated_cost_impact') }}</label>
                                <input type="number" step="0.01" wire:model="cost_impact_amount" class="{{ $input }}">
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('collaboration.help.rough_figure_enough_change_order') }}
                                </p>
                                @error('cost_impact_amount') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <div>
                        <x-ui.toggle wire:model.live="schedule_impact" :checked="$schedule_impact"
                            label="{{ __('collaboration.label.may_schedule_impact') }}" />

                        @if($schedule_impact)
                            <div class="mt-3 max-w-xs">
                                <label class="{{ $label }}">{{ __('collaboration.label.days') }}</label>
                                <input type="number" min="1" wire:model="schedule_impact_days" class="{{ $input }}">
                                @error('schedule_impact_days') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Distribution: repeating rows, with the shortcut a person would
             otherwise do by hand a dozen times. --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Distribution') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.help.who_gets_copy_somebody_login') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    @if(! empty($assignableUsers))
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="addEveryoneOnProject">
                            {{ __('collaboration.label.add_everyone_project') }}
                        </x-ui.button>
                    @endif
                    <x-ui.button variant="secondary" size="sm" type="button" icon="plus" wire:click="addDistributionRow">
                        {{ __('collaboration.label.add_row') }}
                    </x-ui.button>
                </div>
            </div>

            <div class="px-5 py-4 space-y-3">
                @foreach($distributionRows as $index => $row)
                    <div wire:key="dist-{{ $index }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        <div class="md:col-span-4">
                            <select wire:model="distributionRows.{{ $index }}.user_id" class="{{ $input }} mt-0">
                                <option value="">{{ __('collaboration.label.someone_without_login') }}</option>
                                @foreach($assignableUsers as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Choosing somebody who already has a login used to
                             grey out two empty boxes, which reads as a form
                             that lost the data. Show what was chosen. --}}
                        @php $chosen = $row['user_id'] !== '' ? ($chosenUsers[$row['user_id']] ?? null) : null; @endphp

                        @if($chosen)
                            <div class="md:col-span-6 flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-700/40 text-sm">
                                <span class="text-slate-900 dark:text-white">{{ $chosen['name'] }}</span>
                                <span class="text-slate-500 dark:text-slate-400 truncate">{{ $chosen['email'] }}</span>
                            </div>
                        @else
                            <div class="md:col-span-3">
                                <input type="text" wire:model="distributionRows.{{ $index }}.external_name"
                                    class="{{ $input }} mt-0" placeholder="{{ __('Name') }}">
                                @error('distributionRows.'.$index.'.external_name') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-3">
                                <input type="email" wire:model="distributionRows.{{ $index }}.external_email"
                                    class="{{ $input }} mt-0" placeholder="{{ __('E-mail') }}">
                                @error('distributionRows.'.$index.'.external_email') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div class="md:col-span-1">
                            <select wire:model="distributionRows.{{ $index }}.role" class="{{ $input }} mt-0">
                                <option value="">—</option>
                                @foreach($roleOptions as $value => $text)
                                    <option value="{{ $value }}">{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-1 flex justify-end items-center md:h-[38px]">
                            <x-ui.icon-button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                type="button"
                                wire:click="removeDistributionRow({{ $index }})"
                                title="{{ __('Remove this row') }}"
                                aria-label="{{ __('Remove this row') }}"
                                class="hover:text-red-600 dark:hover:text-red-400" />
                        </div>
                    </div>
                @endforeach

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.rows_naming_nobody_reachable_dropped') }}
                </p>
            </div>
        </div>

        {{-- Attachments, in the same step. A person raising an RFI has the
             drawing in front of them now. --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Attachments') }}</h2>
            </div>

            <div class="px-5 py-5 space-y-3">
                <x-ui.file-drop wire:model="newUploads">
                    {{-- Three keys, three different refusals: `uploads.*` from
                         save(), `newUploads` from this form's own size check,
                         and `newUploads.*` from Livewire's temporary-upload
                         rules, which reject a file before it ever reaches PHP. --}}
                    @error('uploads.*') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    @error('newUploads') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    @error('newUploads.*') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                    @if(count($uploads) > 0)
                        <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                            @foreach($uploads as $index => $upload)
                                <li wire:key="upload-{{ $index }}" class="px-3 py-2 flex items-center justify-between gap-3">
                                    <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                        {{ $upload->getClientOriginalName() }}
                                    </span>
                                    <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                        {{ \App\Services\DocumentSettings::formatBytes($upload->getSize()) }}
                                    </span>
                                    <x-ui.icon-button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        type="button"
                                        wire:click="discardUpload({{ $index }})"
                                        title="{{ __('Remove :file', ['file' => $upload->getClientOriginalName()]) }}"
                                        aria-label="{{ __('Remove :file', ['file' => $upload->getClientOriginalName()]) }}"
                                        class="hover:text-red-600 dark:hover:text-red-400" />
                                </li>
                            @endforeach
                        </ul>

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ trans_choice(':count file goes up when this is saved.|:count files go up when this is saved.', count($uploads), ['count' => count($uploads)]) }}
                        </p>
                    @endif
                </x-ui.file-drop>

                @if($this->isEditing && $rfi->availableFiles()->exists())
                    <div>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.already_attached') }}</p>
                        <ul class="mt-1 text-sm text-slate-600 dark:text-slate-300 list-disc list-inside">
                            @foreach($rfi->availableFiles()->get() as $file)
                                <li class="truncate">{{ $file->original_name }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2 pb-8">
            <x-ui.button variant="secondary" :href="$this->isEditing ? route('rfis.show', $rfi) : $backUrl">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button variant="primary" type="submit" icon="save"
                wire:loading.attr="disabled" wire:target="save,newUploads">
                {{ $this->isEditing ? __('collaboration.label.save_changes') : __('collaboration.label.raise_rfi') }}
            </x-ui.button>
        </div>
    </form>
</div>
