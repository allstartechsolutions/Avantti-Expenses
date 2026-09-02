{{-- One task in a list. Expects: $task (with owner, assignees, project, jobSite and the counts) --}}
@php
    $due = $task->due_date;
    $daysOverdue = $task->daysOverdue();
    $daysUntil = $task->daysUntilDue();
@endphp

<div
    wire:key="task-row-{{ $task->id }}"
    wire:click="viewTask({{ $task->id }})"
    class="group flex flex-col gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/40 sm:flex-row sm:items-center"
>
    <!-- What it is -->
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-xs text-slate-400 dark:text-slate-500">{{ $task->code() }}</span>

            <span class="font-medium text-slate-900 dark:text-white {{ $task->isClosed() ? 'line-through opacity-60' : '' }}">
                {{ $task->title }}
            </span>

            <x-task-status-badge :task="$task" />
            <x-task-priority-badge :task="$task" />

            @if($task->isSubtask())
                <span class="inline-flex items-center gap-1 text-xs text-slate-400 dark:text-slate-500" title="{{ __('Sub-task') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    {{ __('sub-task') }}
                </span>
            @endif
        </div>

        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
            <span class="truncate">{{ $task->getScopeLabel() }}</span>

            @if($task->subtasks_count)
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.5 21h9a2.5 2.5 0 002.5-2.5v-13A2.5 2.5 0 0016.5 3h-9A2.5 2.5 0 005 5.5v13A2.5 2.5 0 007.5 21z"/>
                    </svg>
                    {{ trans_choice(':count sub-task|:count sub-tasks', $task->subtasks_count, ['count' => $task->subtasks_count]) }}
                </span>
            @endif

            @if($task->notes_count)
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    {{ $task->notes_count }}
                </span>
            @endif

            @if($task->meeting_items_count)
                <span class="inline-flex items-center gap-1 text-[#3F5189] dark:text-[#4A5A96]" title="{{ __('Discussed in a meeting') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    {{ trans_choice(':count meeting|:count meetings', $task->meeting_items_count, ['count' => $task->meeting_items_count]) }}
                </span>
            @endif
        </div>
    </div>

    <!-- Who and when -->
    <div class="flex items-center gap-4 sm:w-64 sm:shrink-0">
        <div class="flex items-center gap-2 min-w-0" title="{{ __('Owner') }}: {{ $task->owner?->name }}">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#3F5189] text-[10px] font-semibold text-white">
                {{ $task->owner?->initials() }}
            </span>
            <span class="truncate text-xs text-slate-600 dark:text-slate-300">{{ $task->owner?->name }}</span>
            @if($task->assignees->isNotEmpty())
                <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">+{{ $task->assignees->count() }}</span>
            @endif
        </div>

        <div class="ml-auto text-right sm:ml-0">
            @if($due)
                <p class="text-xs {{ $task->isOverdue() ? 'font-semibold text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-300' }}">
                    {{ $due->appDate() }}
                </p>
                <p class="text-[11px] {{ $task->isOverdue() ? 'text-red-500 dark:text-red-400' : 'text-slate-400 dark:text-slate-500' }}">
                    @if($task->isOverdue())
                        {{ trans_choice(':count day late|:count days late', $daysOverdue, ['count' => $daysOverdue]) }}
                    @elseif($task->isClosed())
                        {{ $task->getStatusLabel() }}
                    @elseif($daysUntil === 0)
                        {{ __('today') }}
                    @elseif($daysUntil !== null)
                        {{ trans_choice('in :count day|in :count days', $daysUntil, ['count' => $daysUntil]) }}
                    @endif
                </p>
            @else
                <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('No due date') }}</p>
            @endif
        </div>
    </div>

    <!-- Where it stands, and the one action this person can take from here -->
    <div class="flex items-center gap-3 sm:w-56 sm:shrink-0">
        <x-task-progress :task="$task" class="flex-1" />

        @if($task->canMarkReady(auth()->user()))
            <button type="button"
                    wire:click.stop="markTaskReady({{ $task->id }})"
                    class="shrink-0 rounded-lg border border-green-600 px-2 py-1 text-xs font-medium text-green-700 transition-colors hover:bg-green-600 hover:text-white dark:border-green-500 dark:text-green-400"
                    title="{{ __('Only you can say this is ready') }}">
                {{ __('Ready') }}
            </button>
        @elseif($task->canConfirmCompletion(auth()->user()))
            <button type="button"
                    wire:click.stop="confirmTaskCompletion({{ $task->id }})"
                    class="shrink-0 rounded-lg border border-[#3F5189] px-2 py-1 text-xs font-medium text-[#3F5189] transition-colors hover:bg-[#3F5189] hover:text-white dark:border-[#4A5A96] dark:text-[#4A5A96]"
                    title="{{ __('Confirm this is done') }}">
                {{ __('Confirm') }}
            </button>
        @endif
    </div>
</div>
