{{--
    One line of the minute as an accordion: the header carries enough to run
    the meeting from (who, when, where it stands), and the body opens only for
    the item being taken. Expects: $item, $editable, and the component's own
    $meeting — which is this item's meeting, so it is read from there rather
    than fetched back off every row.

    Alpine state (open / toggle / isOpen) lives on the container in
    meeting-show.blade.php, so "expand all" can reach every row.
--}}
@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $task = $item->task;
    $me = auth()->user();
    $typePalette = [
        'gray' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    ];
    $snapshot = $item->status_at_meeting ?? [];
    // How many notes the task HAS, not how many were loaded: only the newest
    // few are read. Falls back to counting for any caller that loaded them all.
    $shown = App\Livewire\Meeting\MeetingShow::NOTES_SHOWN;
    $noteCount = $task?->notes_count ?? $task?->notes->count() ?? 0;
    // Something already written is worth seeing at a glance while collapsed.
    $hasContent = filled($item->discussion) || filled($item->decision);
@endphp

<div wire:key="minute-item-{{ $item->id }}" class="{{ $item->discussed ? '' : 'opacity-70' }}">
    <!-- Header: always visible -->
    <button type="button"
            x-on:click="toggle({{ $item->id }})"
            class="flex w-full items-start gap-3 px-6 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40">
        <svg class="mt-1 h-4 w-4 shrink-0 text-slate-400 transition-transform"
             :class="isOpen({{ $item->id }}) ? 'rotate-90' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>

        <span class="mt-0.5 w-8 shrink-0 font-mono text-sm text-slate-400 dark:text-slate-500">{{ $item->number() }}</span>

        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typePalette[$item->getTypeColor()] ?? $typePalette['gray'] }}">
                    {{ $item->getTypeLabel() }}
                </span>

                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->title }}</span>

                @if($task)
                    <x-task-status-badge :task="$task" />
                @endif

                @if($item->isCarriedForward())
                    <span class="inline-flex items-center rounded-full bg-[#3F5189]/10 px-2 py-0.5 text-xs text-[#3F5189] dark:text-[#4A5A96]">
                        {{ __('from :number', ['number' => $item->carriedFrom?->meeting?->number]) }}
                    </span>
                @endif

                @unless($item->discussed)
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400">
                        {{ __('not discussed') }}
                    </span>
                @endunless

                @if($hasContent)
                    <svg class="h-3.5 w-3.5 text-green-600 dark:text-green-400" title="{{ __('Recorded') }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                @endif

                @if($item->children->isNotEmpty())
                    <span class="text-xs text-slate-400 dark:text-slate-500">
                        {{ trans_choice(':count sub-item|:count sub-items', $item->children->count(), ['count' => $item->children->count()]) }}
                    </span>
                @endif
            </span>

            <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                <span>{{ $item->getScopeLabel() }}</span>
                @if($task)
                    <span>{{ $task->owner?->name }}</span>
                    @if($task->due_date)
                        <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">
                            {{ $task->due_date->format($dateFormat) }}
                        </span>
                    @endif
                    <span>{{ $meeting->isPublished() ? ($snapshot['progress'] ?? $task->progress) : $task->progress }}%</span>
                @endif
            </span>
        </span>
    </button>

    <!-- Body: only for the item being taken -->
    <div x-show="isOpen({{ $item->id }})" x-cloak class="px-6 pb-4 pl-[4.5rem]">
        <!-- What was said -->
        <div>
            @if($editable)
                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Discussion') }}</label>
                <textarea wire:model.blur="discussion.{{ $item->id }}" rows="2" class="{{ $field }}"
                          placeholder="{{ __('What was said about this.') }}"></textarea>
            @elseif($item->discussion)
                <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $item->discussion }}</p>
            @else
                <p class="text-sm italic text-slate-400 dark:text-slate-500">{{ __('Nothing was recorded for this item.') }}</p>
            @endif
        </div>

        <!-- What was decided -->
        @if($editable || $item->decision)
            <div class="mt-3">
                @if($editable)
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Decision') }}</label>
                    <textarea wire:model.blur="decision.{{ $item->id }}" rows="2" class="{{ $field }}"
                              placeholder="{{ __('What was agreed, in the words the minute should carry.') }}"></textarea>
                @else
                    <div class="rounded-lg border-l-4 border-[#3F5189] bg-[#3F5189]/5 px-3 py-2 dark:bg-[#4A5A96]/10">
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#3F5189] dark:text-[#4A5A96]">{{ __('Decision') }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm font-medium text-slate-800 dark:text-slate-100">{{ $item->decision }}</p>
                    </div>
                @endif
            </div>
        @endif

        <!-- Working the task in front of everyone -->
        @if($task)
            <div class="mt-3 rounded-lg bg-slate-50 dark:bg-slate-700/40 px-3 py-3">
                @if($editable)
                    <div class="flex flex-wrap items-center gap-3">
                        <x-task-progress :task="$task" class="min-w-[10rem] flex-1" />

                        @if($task->canChangeProgress($me))
                            <div class="flex flex-wrap gap-1">
                                @foreach([0, 25, 50, 75, 100] as $step)
                                    <button type="button" wire:click="setProgressFromMeeting({{ $task->id }}, {{ $step }})"
                                            class="rounded border px-2 py-0.5 text-xs transition-colors
                                                {{ $task->progress === $step
                                                    ? 'border-[#3F5189] bg-[#3F5189] text-white'
                                                    : 'border-slate-300 text-slate-600 hover:border-[#3F5189] dark:border-slate-600 dark:text-slate-300' }}">
                                            {{ $step }}%
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if($task->canConfirmCompletion($me))
                            <x-ui.button variant="primary" size="sm" wire:click="confirmFromMeeting({{ $task->id }})">
                                {{ __('Confirm Done') }}
                            </x-ui.button>
                        @elseif($task->status === 'ready')
                            <span class="text-xs text-amber-600 dark:text-amber-400">{{ __('ready — the chair confirms it') }}</span>
                        @endif

                        <x-ui.icon-button variant="ghost" size="sm" icon="eye"
                                          wire:click="viewTask({{ $task->id }})"
                                          title="{{ __('Open task') }}" />
                    </div>

                    <div class="mt-3 flex flex-col sm:flex-row gap-2">
                        <input type="text" wire:model="itemNote.{{ $item->id }}"
                               wire:keydown.enter="addMeetingNote({{ $item->id }}, {{ $task->id }})"
                               placeholder="{{ __('Record a note against this task...') }}"
                               class="flex-1 px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <x-ui.button variant="secondary" size="sm" wire:click="addMeetingNote({{ $item->id }}, {{ $task->id }})">{{ __('Add Note') }}</x-ui.button>
                    </div>
                @else
                    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-300">
                        <span>{{ __('At this meeting:') }}</span>
                        <span class="font-medium">{{ $snapshot['progress'] ?? $task->progress }}%</span>
                        <span>{{ __('owner: :name', ['name' => $snapshot['owner_name'] ?? $task->owner?->name]) }}</span>
                        @if($snapshot['due_date'] ?? null)
                            <span>{{ __('due :date', ['date' => \Illuminate\Support\Carbon::parse($snapshot['due_date'])->format($dateFormat)]) }}</span>
                        @endif
                        @if(($snapshot['status'] ?? null) && $snapshot['status'] !== $task->status)
                            <span class="text-slate-400">{{ __('since then it has moved on') }}</span>
                        @endif
                        <x-ui.icon-button variant="ghost" size="sm" icon="eye"
                                          wire:click="viewTask({{ $task->id }})"
                                          title="{{ __('Open task') }}" />
                    </div>
                @endif

                <!-- What has already been recorded against this task -->
                @if($noteCount > 0)
                    <div class="mt-3 border-t border-slate-200 dark:border-slate-600 pt-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ trans_choice(':count note|:count notes', $noteCount, ['count' => $noteCount]) }}
                        </p>

                        <div class="mt-2 space-y-2">
                            @foreach($task->notes->take($shown) as $note)
                                <div class="text-xs" wire:key="item-note-{{ $note->id }}">
                                    <p class="flex flex-wrap items-center gap-2 text-slate-500 dark:text-slate-400">
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $note->user?->name }}</span>
                                        <span>{{ $note->created_at?->diffForHumans() }}</span>
                                        @if($note->wasWrittenInMeeting())
                                            <span class="rounded-full bg-[#3F5189]/10 px-1.5 py-0.5 font-medium text-[#3F5189] dark:text-[#4A5A96]">
                                                {{ $note->meeting?->number }}
                                            </span>
                                        @endif
                                        @if($note->progress_snapshot !== null)
                                            <span class="text-slate-400">{{ __('at :progress%', ['progress' => $note->progress_snapshot]) }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $note->body }}</p>
                                </div>
                            @endforeach

                            @if($noteCount > $shown)
                                <button type="button" wire:click="viewTask({{ $task->id }})"
                                        class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ __('See all :count notes', ['count' => $noteCount]) }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Sub-items, inside the item they belong to -->
        @if($item->children->isNotEmpty())
            <div class="mt-3 space-y-2 border-l-2 border-slate-200 dark:border-slate-600 pl-4">
                @foreach($item->children as $child)
                    <div wire:key="minute-child-{{ $child->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-slate-400">{{ $child->number() }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs {{ $typePalette[$child->getTypeColor()] ?? $typePalette['gray'] }}">
                                {{ $child->getTypeLabel() }}
                            </span>
                            <span class="text-sm text-slate-800 dark:text-slate-100">{{ $child->title }}</span>
                            @if($child->task)
                                <x-task-status-badge :task="$child->task" />
                                <span class="font-mono text-xs text-slate-400">{{ $child->task->code() }}</span>
                                <x-ui.icon-button variant="ghost" size="sm" icon="eye"
                                                  wire:click="viewTask({{ $child->task->id }})"
                                                  title="{{ __('Open task') }}" />
                            @endif
                        </div>

                        @if($editable)
                            <textarea wire:model.blur="discussion.{{ $child->id }}" rows="2" class="{{ $field }} mt-1"
                                      placeholder="{{ __('What was said about this.') }}"></textarea>
                        @elseif($child->discussion)
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $child->discussion }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <!-- What can be done with this line -->
        @if($editable && $meeting->isDraft())
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="toggleDiscussed({{ $item->id }})"
                        class="text-xs text-slate-500 hover:text-[#3F5189] dark:text-slate-400">
                    {{ $item->discussed ? __('Mark as not discussed') : __('Mark as discussed') }}
                </button>

                <button type="button" wire:click="editItem({{ $item->id }})"
                        class="text-xs text-slate-500 hover:text-[#3F5189] dark:text-slate-400">
                    {{ $task ? __('Edit this item and its task') : __('Edit this item') }}
                </button>

                <button type="button" wire:click="openItemForm({{ $item->id }})"
                        class="text-xs text-slate-500 hover:text-[#3F5189] dark:text-slate-400">
                    {{ __('Add a sub-item') }}
                </button>

                <button type="button"
                        wire:click="removeAgendaItem({{ $item->id }})"
                        wire:confirm="{{ $item->children->isNotEmpty()
                            ? __('Take this off the agenda? Its sub-items go with it. No task is closed or deleted.')
                            : __('Take this off the agenda? The task itself stays open and will be proposed again.') }}"
                        class="text-xs text-slate-500 hover:text-red-600 dark:text-slate-400">
                    {{ __('Take off the agenda') }}
                </button>
            </div>
        @endif

        <!-- Raising or changing a line right here -->
        @if($showItemForm && ($editingItemId === $item->id || (! $editingItemId && $item_parent_id === $item->id)))
            <div class="mt-3">
                @include('livewire.meeting.partials.item-form')
            </div>
        @endif
    </div>
</div>
