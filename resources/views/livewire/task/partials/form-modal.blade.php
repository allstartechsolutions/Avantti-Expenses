{{--
    Task form — full page, shared by My Tasks, the project and job-site task
    pages, and the meeting screens.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $parent = $task_parent_id ? App\Models\Task::find($task_parent_id) : null;
@endphp

<x-ui.modal name="task-form-modal" maxWidth="full" layer="top">
    <form wire:submit="saveTask" class="flex min-h-screen flex-col">
        <!-- Header -->
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $editingTaskId ? __('Edit Task') : ($parent ? __('New Sub-task') : __('New Task')) }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                        @if($parent)
                            {{ __('Under :code — :title', ['code' => $parent->code(), 'title' => $parent->title]) }}
                        @else
                            {{ __('Somebody owns it, and it has a date.') }}
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="closeTaskForm"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- What has to happen -->
                <div class="lg:col-span-3 space-y-4">
                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Task') }}</h3>

                        <div>
                            <label class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="task_title" class="{{ $field }}"
                                   placeholder="{{ __('e.g. Fix the drainage behind the retaining wall') }}">
                            @error('task_title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Description') }}</label>
                            <textarea wire:model="task_description" rows="6" class="{{ $field }}"
                                      placeholder="{{ __('What has to be done, and what counts as done.') }}"></textarea>
                            @error('task_description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Where it belongs -->
                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Where It Belongs') }}</h3>

                        @if($parent)
                            <p class="rounded-lg bg-slate-50 dark:bg-slate-700/40 px-3 py-2 text-sm text-slate-600 dark:text-slate-300">
                                {{ __('A sub-task belongs wherever its parent does.') }}
                                <span class="font-medium">{{ $parent->getScopeLabel() }}</span>
                            </p>

                            {{-- The first sub-task takes the percentage out of the owner's hands. --}}
                            @if(! $parent->hasSubtasks() && $parent->progress > 0)
                                <p class="rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-sm text-amber-800 dark:text-amber-300">
                                    {{ __(':code is at :progress% today. Once it has sub-tasks it takes its percentage from theirs instead, so it will show the average of the sub-tasks.', [
                                        'code' => $parent->code(),
                                        'progress' => $parent->progress,
                                    ]) }}
                                </p>
                            @endif
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">{{ __('Project') }}</label>
                                    <select wire:model.live="task_project_id" class="{{ $field }}" @disabled($this->taskScopeIsFixed())>
                                        <option value="">{{ __('General — no project') }}</option>
                                        @foreach($this->selectableProjects as $project)
                                            <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('task_project_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="{{ $label }}">{{ __('Job Site') }}</label>
                                    <select wire:model="task_job_site_id" class="{{ $field }}" @disabled(! $task_project_id)>
                                        <option value="">{{ $task_project_id ? __('The project as a whole') : __('Choose a project first') }}</option>
                                        @foreach($this->selectableJobSites as $jobSite)
                                            <option value="{{ $jobSite->id }}">{{ $jobSite->job_site_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('task_job_site_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('A task with no project is your own work: it stays off every meeting agenda unless somebody puts it on one.') }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Who and when -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Who Is On It') }}</h3>

                        <div>
                            <label class="{{ $label }}">{{ __('Owner') }} <span class="text-red-500">*</span></label>
                            <select wire:model="task_owner_id" class="{{ $field }}">
                                @foreach($this->assignableUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('The owner is the only person who can say the work is ready.') }}
                            </p>
                            @error('task_owner_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Also Working On It') }}</label>
                            <div class="max-h-56 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-600 divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach($this->assignableUsers as $user)
                                    @continue($user->id === (int) $task_owner_id)
                                    <label class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                        <input type="checkbox" value="{{ $user->id }}" wire:model="task_assignees"
                                               class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                        <span class="text-sm text-slate-700 dark:text-slate-200">{{ $user->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('They can move the progress and add notes. They cannot close it.') }}
                            </p>
                        </div>
                    </div>

                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('When') }}</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Start Date') }}</label>
                                <input type="date" wire:model="task_start_date" class="{{ $field }}">
                                @error('task_start_date') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}">{{ __('Due Date') }}</label>
                                <input type="date" wire:model="task_due_date" class="{{ $field }}">
                                @error('task_due_date') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Priority') }} <span class="text-red-500">*</span></label>
                            <select wire:model="task_priority" class="{{ $field }}">
                                <option value="low">{{ __('Task priority: low') }}</option>
                                <option value="normal">{{ __('Task priority: normal') }}</option>
                                <option value="high">{{ __('Task priority: high') }}</option>
                                <option value="urgent">{{ __('Task priority: urgent') }}</option>
                            </select>
                            @error('task_priority') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Without a due date the task never appears as overdue and nobody is reminded of it.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-end gap-3">
                <x-ui.button type="button" variant="secondary" wire:click="closeTaskForm">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="save">
                    {{ $editingTaskId ? __('Save Changes') : __('Create Task') }}
                </x-ui.button>
            </div>
        </div>
    </form>
</x-ui.modal>
