{{--
    Task detail — full page. Everything the record knows: the work, the people,
    the dates, the sub-tasks and their arithmetic, every note, every file, every
    meeting that discussed it, and the whole audit trail.
--}}
@php
    $task = $this->viewingTask;
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
    $stampFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A';
    $me = auth()->user();
@endphp

<x-ui.modal name="task-detail-modal" maxWidth="full">
    @if($task)
        <div class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm text-slate-400 dark:text-slate-500">{{ $task->code() }}</span>
                                <x-task-status-badge :task="$task" size="md" />
                                <x-task-priority-badge :task="$task" />
                                @if($task->isOverdue())
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                        {{ trans_choice(':count day late|:count days late', $task->daysOverdue(), ['count' => $task->daysOverdue()]) }}
                                    </span>
                                @endif
                            </div>

                            <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-white">{{ $task->title }}</h2>

                            <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                                {{ $task->getScopeLabel() }}
                                @if($task->isSubtask())
                                    · {{ __('sub-task of :code', ['code' => $task->parent?->code()]) }}
                                @endif
                            </p>
                        </div>

                        <button type="button" wire:click="closeTaskDetail"
                                class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" title="{{ __('Close') }}">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- What this person can actually do -->
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if($task->canMarkReady($me))
                            <x-ui.button variant="success" size="sm" wire:click="markTaskReady({{ $task->id }})">
                                {{ __('Mark Ready') }}
                            </x-ui.button>
                        @elseif($task->status === 'ready' && $task->owner_id === $me?->id)
                            <span class="rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-300">
                                {{ __('You marked this ready — it is waiting for confirmation.') }}
                            </span>
                        @elseif($task->isOpen() && $task->owner_id !== $me?->id && $task->status !== 'ready')
                            <span class="rounded-lg bg-slate-50 dark:bg-slate-700/40 px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Only :owner can mark this ready.', ['owner' => $task->owner?->name]) }}
                            </span>
                        @endif

                        @if($task->canConfirmCompletion($me))
                            <x-ui.button variant="primary" size="sm" wire:click="confirmTaskCompletion({{ $task->id }})">
                                {{ __('Confirm Completion') }}
                            </x-ui.button>
                        @endif

                        @if($task->canReopen($me))
                            <x-ui.button variant="warning" size="sm" wire:click="promptReason('reopen')">
                                {{ __('Reopen') }}
                            </x-ui.button>
                        @endif

                        @if($task->isOpen() && $task->status !== 'blocked' && ($task->canEdit($me) || $task->owner_id === $me?->id))
                            <x-ui.button variant="secondary" size="sm" wire:click="promptReason('block')">
                                {{ __('Block') }}
                            </x-ui.button>
                        @endif

                        @if($task->status === 'blocked' && ($task->canEdit($me) || $task->owner_id === $me?->id))
                            <x-ui.button variant="secondary" size="sm" wire:click="unblockTask({{ $task->id }})">
                                {{ __('Unblock') }}
                            </x-ui.button>
                        @endif

                        @if($task->canEdit($me))
                            <x-ui.button variant="secondary" size="sm" icon="edit" wire:click="editTask({{ $task->id }})">
                                {{ __('Edit') }}
                            </x-ui.button>
                        @endif

                        @if($task->isOpen() && ! $task->isSubtask())
                            <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="openTaskForm({{ $task->id }})">
                                {{ __('Add Sub-task') }}
                            </x-ui.button>
                        @endif

                        @if($task->canCancel($me))
                            <x-ui.button variant="ghost" size="sm" wire:click="promptReason('cancel')"
                                         class="!text-red-600 dark:!text-red-400">
                                {{ __('Cancel Task') }}
                            </x-ui.button>
                        @endif

                        {{-- Task::canDelete() carries the whole rule now (F2): the
                             grant, and never anything a published minute mentions. --}}
                        @if($me?->can('tasks.delete'))
                            @if($task->canDelete($me))
                                <x-ui.button variant="ghost" size="sm" icon="trash"
                                             wire:click="deleteTask({{ $task->id }})"
                                             wire:confirm="{{ __('Delete :code for good? Its notes, files and sub-tasks go with it, and its lines come off any agenda still being prepared. Cancelling is usually the better answer.', ['code' => $task->code()]) }}"
                                             class="!text-red-600 dark:!text-red-400">
                                    {{ __('Delete') }}
                                </x-ui.button>
                            @else
                                <span class="rounded-lg bg-slate-50 dark:bg-slate-700/40 px-3 py-1.5 text-xs text-slate-500 dark:text-slate-400"
                                      title="{{ $task->publishedMinutes()->pluck('number')->implode(', ') }}">
                                    {{ __('In a published minute — cancel it rather than delete it.') }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
                @if (session()->has('error'))
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                        {{ session('error') }}
                    </div>
                @endif

                @if($task->status === 'blocked' && $task->blocked_reason)
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-900/20">
                        <p class="text-sm font-medium text-red-800 dark:text-red-300">{{ __('Blocked') }}</p>
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $task->blocked_reason }}</p>
                    </div>
                @endif

                @if($task->status === 'cancelled' && $task->cancel_reason)
                    <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-700/40">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ __('Cancelled by :name on :date', [
                                'name' => $task->cancelledBy?->name,
                                'date' => $task->cancelled_at?->format($stampFormat),
                            ]) }}
                        </p>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $task->cancel_reason }}</p>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- The work -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Progress -->
                        <div class="{{ $card }} p-5">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Progress') }}</h3>
                                <span class="text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $task->progress }}%</span>
                            </div>

                            <x-task-progress :task="$task" :showLabel="false" class="mt-3" />

                            @if($task->isProgressDerived())
                                @php
                                    $counted = $task->subtasks->where('status', '!=', 'cancelled');
                                    $excluded = $task->subtasks->count() - $counted->count();
                                @endphp
                                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('The average of :count sub-tasks, computed by the system.', ['count' => $counted->count()]) }}
                                    @if($excluded)
                                        {{ trans_choice(':count cancelled sub-task is left out.|:count cancelled sub-tasks are left out.', $excluded, ['count' => $excluded]) }}
                                    @endif
                                </p>
                                <p class="mt-1 font-mono text-xs text-slate-400 dark:text-slate-500">
                                    ({{ $counted->map(fn ($sub) => $sub->status === 'completed' ? 100 : $sub->progress)->implode(' + ') }}) ÷ {{ $counted->count() }} = {{ $task->progress }}%
                                </p>
                            @elseif($task->canChangeProgress($me))
                                <div class="mt-4 space-y-3">
                                    <input type="range" min="0" max="100" step="5" value="{{ $task->progress }}"
                                           x-on:change="$wire.setTaskProgress({{ $task->id }}, parseInt($event.target.value))"
                                           class="w-full accent-[#3F5189]">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach([0, 25, 50, 75, 100] as $step)
                                            <button type="button" wire:click="setTaskProgress({{ $task->id }}, {{ $step }})"
                                                    class="rounded-lg border px-3 py-1 text-xs font-medium transition-colors
                                                        {{ $task->progress === $step
                                                            ? 'border-[#3F5189] bg-[#3F5189] text-white'
                                                            : 'border-slate-300 text-slate-600 hover:border-[#3F5189] hover:text-[#3F5189] dark:border-slate-600 dark:text-slate-300' }}">
                                                {{ $step }}%
                                            </button>
                                        @endforeach
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Reaching 100% does not close the task — :owner still has to mark it ready.', ['owner' => $task->owner?->name]) }}
                                    </p>
                                </div>
                            @else
                                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $task->isClosed()
                                        ? __('The task is closed, so the figure is final.')
                                        : __('Only the owner and the people assigned can move this.') }}
                                </p>
                            @endif
                        </div>

                        <!-- Description -->
                        <div class="{{ $card }} p-5">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Description') }}</h3>
                            @if($task->description)
                                <p class="mt-3 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $task->description }}</p>
                            @else
                                <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">
                                    {{ __('No description was written. Whoever picks this up has only the title to go on.') }}
                                    @if($task->canEdit($me))
                                        <button type="button" wire:click="editTask({{ $task->id }})" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('Add one') }}</button>
                                    @endif
                                </p>
                            @endif
                        </div>

                        <!-- Sub-tasks -->
                        <div class="{{ $card }}">
                            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ __('Sub-tasks') }}
                                    @if($task->subtasks->isNotEmpty())
                                        <span class="ml-1 text-slate-400">({{ $task->subtasks->where('status', 'completed')->count() }}/{{ $task->subtasks->count() }})</span>
                                    @endif
                                </h3>
                                @if($task->isOpen() && ! $task->isSubtask())
                                    <x-ui.button variant="ghost" size="sm" icon="plus" wire:click="openTaskForm({{ $task->id }})">{{ __('Add') }}</x-ui.button>
                                @endif
                            </div>

                            @if($task->subtasks->isEmpty())
                                <p class="px-5 py-6 text-sm text-slate-400 dark:text-slate-500">
                                    {{ $task->isSubtask()
                                        ? __('A sub-task cannot have sub-tasks of its own.')
                                        : __('No sub-tasks. Add them and this task takes its percentage from theirs instead of a typed number.') }}
                                </p>
                            @else
                                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($task->subtasks as $sub)
                                        <div class="flex items-center gap-3 px-5 py-3" wire:key="sub-{{ $sub->id }}">
                                            <button type="button" wire:click="viewTask({{ $sub->id }})"
                                                    class="min-w-0 flex-1 text-left">
                                                <span class="font-mono text-xs text-slate-400">{{ $sub->code() }}</span>
                                                <span class="ml-2 text-sm text-slate-800 dark:text-slate-100 {{ $sub->isClosed() ? 'line-through opacity-60' : '' }}">{{ $sub->title }}</span>
                                                <span class="ml-2 text-xs text-slate-400">{{ $sub->owner?->name }}</span>
                                            </button>
                                            <x-task-status-badge :task="$sub" />
                                            <x-task-progress :task="$sub" class="w-32" />
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Notes -->
                        <div class="{{ $card }}">
                            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ __('Notes') }} <span class="ml-1 text-slate-400">({{ $task->notes->count() }})</span>
                                </h3>
                            </div>

                            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                                <textarea wire:model="newNoteBody" rows="3"
                                          placeholder="{{ __('What happened, what was agreed, what is still missing...') }}"
                                          class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500"></textarea>
                                <div class="mt-2 flex justify-end">
                                    <x-ui.button variant="primary" size="sm" wire:click="addTaskNote">{{ __('Add Note') }}</x-ui.button>
                                </div>
                            </div>

                            @if($task->notes->isEmpty())
                                <p class="px-5 py-6 text-sm text-slate-400 dark:text-slate-500">
                                    {{ __('Nothing has been recorded yet. Notes are what the next meeting reads.') }}
                                </p>
                            @else
                                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($task->notes as $note)
                                        <div class="px-5 py-4" wire:key="note-{{ $note->id }}">
                                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 dark:bg-slate-600 text-[10px] font-semibold text-slate-700 dark:text-slate-200">
                                                    {{ $note->user?->initials() }}
                                                </span>
                                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ $note->user?->name }}</span>
                                                <span>{{ $note->created_at?->format($stampFormat) }}</span>

                                                @if($note->wasWrittenInMeeting())
                                                    <span class="inline-flex items-center rounded-full bg-[#3F5189]/10 px-2 py-0.5 font-medium text-[#3F5189] dark:text-[#4A5A96]">
                                                        {{ $note->meeting?->number }}
                                                    </span>
                                                @endif

                                                @if($note->progress_snapshot !== null)
                                                    <span class="text-slate-400">{{ __('at :progress%', ['progress' => $note->progress_snapshot]) }}</span>
                                                @endif

                                                @if($note->wasEdited())
                                                    <span class="italic text-slate-400">{{ __('edited') }}</span>
                                                @endif
                                            </div>

                                            <p class="mt-2 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $note->body }}</p>

                                            @if($note->availableFiles->isNotEmpty())
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach($note->availableFiles as $file)
                                                        <button type="button" wire:click="downloadTaskFile({{ $file->id }})"
                                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-600 px-2 py-1 text-xs text-slate-600 dark:text-slate-300 hover:border-[#3F5189]">
                                                            {{ $file->original_name }}
                                                            <span class="text-slate-400">{{ $file->formattedSize() }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- A photo or a marked-up drawing usually belongs with the
                                                 note that explains it, not loose on the task. --}}
                                            <div x-data="{ attaching: false }" class="mt-2">
                                                <button type="button" x-show="! attaching" x-on:click="attaching = true"
                                                        class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                                    </svg>
                                                    {{ __('Attach a file to this note') }}
                                                </button>

                                                <div x-show="attaching" x-cloak class="mt-2">
                                                    <x-ui.file-uploader
                                                        target-type="task_note"
                                                        :target-id="$note->id"
                                                        completed="taskFileUploaded"
                                                        :label="__('Drop files for this note, or')"
                                                        wire:key="uploader-note-{{ $note->id }}" />
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Files -->
                        <div class="{{ $card }}">
                            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ __('Files') }} <span class="ml-1 text-slate-400">({{ $task->availableFiles->count() }})</span>
                                </h3>
                            </div>

                            <div class="px-5 py-4">
                                <x-ui.file-uploader
                                    target-type="task"
                                    :target-id="$task->id"
                                    completed="taskFileUploaded"
                                    wire:key="uploader-task-{{ $task->id }}" />
                            </div>

                            @if($task->availableFiles->isNotEmpty())
                                <div class="divide-y divide-slate-200 dark:divide-slate-700 border-t border-slate-200 dark:border-slate-700">
                                    @foreach($task->availableFiles as $file)
                                        <div class="flex items-center gap-3 px-5 py-3" wire:key="file-{{ $file->id }}">
                                            <div class="min-w-0 flex-1">
                                                <button type="button" wire:click="downloadTaskFile({{ $file->id }})"
                                                        class="truncate text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                                    {{ $file->original_name }}
                                                </button>
                                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                                    {{ $file->formattedSize() }} · {{ $file->uploadedBy?->name }} · {{ $file->created_at?->format($stampFormat) }}
                                                </p>
                                            </div>

                                            {{-- Matches ManagesTasks::deleteTaskFile(): your own file, or
                                                 somebody who may change this task (F2). --}}
                                            @if($file->uploaded_by === $me?->id || $this->canEditTask($task))
                                                <button type="button"
                                                        wire:click="deleteTaskFile({{ $file->id }})"
                                                        wire:confirm="{{ __('Remove this file? It is deleted from storage and cannot be brought back.') }}"
                                                        class="shrink-0 text-slate-400 hover:text-red-600" title="{{ __('Remove') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- The facts -->
                    <div class="space-y-6">
                        <div class="{{ $card }} p-5 space-y-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('People') }}</h3>

                            <div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Owner') }}</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#3F5189] text-xs font-semibold text-white">
                                        {{ $task->owner?->initials() }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $task->owner?->name }}</p>
                                        <p class="truncate text-xs text-slate-400">{{ $task->owner?->email }}</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Also Working On It') }}</p>
                                @if($task->assignees->isEmpty())
                                    <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">{{ __('Nobody else — the owner is on their own.') }}</p>
                                @else
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        @foreach($task->assignees as $assignee)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-1 text-xs text-slate-700 dark:text-slate-200">
                                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-300 dark:bg-slate-600 text-[9px] font-semibold">
                                                    {{ $assignee->initials() }}
                                                </span>
                                                {{ $assignee->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="{{ $card }} p-5 space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Dates') }}</h3>

                            @foreach([
                                ['label' => __('Created'), 'value' => $task->created_at?->format($stampFormat)],
                                ['label' => __('Start Date'), 'value' => $task->start_date?->appDate()],
                                ['label' => __('Due Date'), 'value' => $task->due_date?->appDate()],
                                ['label' => __('Marked Ready'), 'value' => $task->ready_at?->format($stampFormat)],
                                ['label' => __('Completed'), 'value' => $task->completed_at?->format($stampFormat)],
                            ] as $row)
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="text-slate-500 dark:text-slate-400">{{ $row['label'] }}</span>
                                    <span class="text-right font-medium text-slate-800 dark:text-slate-100">
                                        {{ $row['value'] ?? '—' }}
                                    </span>
                                </div>
                            @endforeach

                            @if($task->ready_by)
                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ __('Declared ready by :name', ['name' => $task->readyBy?->name]) }}
                                </p>
                            @endif
                            @if($task->completed_by)
                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ __('Confirmed by :name', ['name' => $task->completedBy?->name]) }}
                                </p>
                            @endif
                        </div>

                        <!-- Where it came from, and every meeting since -->
                        <div class="{{ $card }} p-5">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Discussed In') }}</h3>

                            @if($task->meetingItems->isEmpty())
                                <p class="mt-3 text-sm text-slate-400 dark:text-slate-500">
                                    {{ __('No meeting has discussed this task, so it stays off the agenda until somebody puts it on one.') }}
                                </p>
                            @else
                                <div class="mt-3 space-y-3">
                                    @foreach($task->meetingItems->sortBy(fn ($item) => $item->meeting?->meeting_date) as $item)
                                        <div class="border-l-2 border-[#3F5189]/30 pl-3" wire:key="mitem-{{ $item->id }}">
                                            <p class="text-xs font-medium text-[#3F5189] dark:text-[#4A5A96]">
                                                {{ $item->meeting?->number }}
                                                <span class="text-slate-400">{{ $item->meeting?->meeting_date?->appDate() }}</span>
                                            </p>
                                            @if($item->discussion)
                                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $item->discussion }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Activity -->
                        <div class="{{ $card }} p-5">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Activity') }}</h3>

                            <div class="mt-3 space-y-3 max-h-96 overflow-y-auto pr-1">
                                @foreach($task->activities as $activity)
                                    <div class="text-xs" wire:key="act-{{ $activity->id }}">
                                        <p class="text-slate-700 dark:text-slate-200">
                                            <span class="font-medium">{{ $activity->getActionLabel() }}</span>
                                            @if($activity->old_value || $activity->new_value)
                                                <span class="text-slate-500 dark:text-slate-400">
                                                    {{ $activity->old_value ?? '—' }} → {{ $activity->new_value ?? '—' }}
                                                </span>
                                            @endif
                                        </p>
                                        <p class="text-slate-400 dark:text-slate-500">
                                            {{ $activity->user?->name }} · {{ $activity->created_at?->format($stampFormat) }}
                                            @if($activity->meeting)
                                                · {{ $activity->meeting->number }}
                                            @endif
                                        </p>
                                        @if($activity->notes)
                                            <p class="mt-0.5 italic text-slate-500 dark:text-slate-400">{{ $activity->notes }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Audit -->
                        <div class="px-1 text-xs text-slate-400 dark:text-slate-500 space-y-1">
                            <p>{{ __('Raised by :name on :date', [
                                'name' => $task->createdBy?->name ?? __('unknown'),
                                'date' => $task->created_at?->format($stampFormat),
                            ]) }}</p>
                            @if($task->originMeeting)
                                <p>{{ __('First raised at :number', ['number' => $task->originMeeting->number]) }}</p>
                            @endif
                            <p>{{ __('Last updated by :name on :date', [
                                'name' => $task->updatedBy?->name ?? __('unknown'),
                                'date' => $task->updated_at?->format($stampFormat),
                            ]) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex justify-end">
                    <x-ui.button variant="secondary" wire:click="closeTaskDetail">{{ __('Close') }}</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</x-ui.modal>
