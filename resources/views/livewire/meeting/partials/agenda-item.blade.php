{{-- One line of the agenda being built. Expects: $item, $depth, $canUp, $canDown --}}
@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
    // The arrows stop at the edge of the location block: a line cannot change
    // project by being moved, so the whole block moves from its heading instead.
    $canUp = $canUp ?? true;
    $canDown = $canDown ?? true;
    $task = $item->task;
    $typePalette = [
        'gray' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    ];
@endphp

<div class="flex items-start gap-3 px-6 py-3 transition-colors {{ $depth > 0 ? 'pl-14 bg-slate-50/60 dark:bg-slate-700/20' : '' }}"
     wire:key="item-{{ $item->id }}"
     @if($depth === 0)
         data-agenda-row="{{ $item->id }}"
         draggable="true"
         x-on:dragstart="start({{ $item->id }})"
         x-on:dragend="end()"
         x-on:dragover.prevent="enter({{ $item->id }})"
         x-on:dragleave="over === {{ $item->id }} && (over = null)"
         x-on:drop.prevent="drop({{ $item->id }})"
         :class="{
             'opacity-40': dragging === {{ $item->id }},
             'border-t-2 border-[#3F5189]': over === {{ $item->id }},
         }"
     @endif>
    @if($depth === 0)
        <span class="mt-1 shrink-0 cursor-grab text-slate-300 hover:text-slate-500 dark:text-slate-600 dark:hover:text-slate-400"
              title="{{ __('Drag to reorder') }}">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <circle cx="7" cy="5" r="1.5"/><circle cx="13" cy="5" r="1.5"/>
                <circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>
                <circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/>
            </svg>
        </span>
    @endif

    <span class="mt-0.5 {{ $depth === 0 ? 'w-8' : 'w-10' }} shrink-0 font-mono text-sm text-slate-400 dark:text-slate-500">{{ $item->number() }}</span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $typePalette[$item->getTypeColor()] ?? $typePalette['gray'] }}">
                {{ $item->getTypeLabel() }}
            </span>

            @if($task)
                <button type="button" wire:click="viewTask({{ $task->id }})"
                        class="font-mono text-xs text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]">
                    {{ $task->code() }}
                </button>
            @endif

            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->title }}</span>

            @if($task)
                <x-task-status-badge :task="$task" />
                <x-task-priority-badge :task="$task" />
            @endif

            @if($item->type === 'action' && $task && ! $task->due_date)
                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                      title="{{ __('The minute cannot be published until this has a date.') }}">
                    {{ __('no date') }}
                </span>
            @endif

            @if($item->isCarriedForward())
                <span class="inline-flex items-center gap-1 rounded-full bg-[#3F5189]/10 px-2 py-0.5 text-xs text-[#3F5189] dark:text-[#4A5A96]"
                      title="{{ __('Continued from an earlier meeting') }}">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
                    </svg>
                    {{ $item->carriedFrom?->meeting?->number }}
                </span>
            @endif
        </div>

        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
            @if($task)
                <span>{{ $task->owner?->name }}</span>

                @if($task->due_date)
                    <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">
                        {{ $task->due_date->format($dateFormat) }}
                        @if($task->isOverdue())
                            · {{ trans_choice(':count day late|:count days late', $task->daysOverdue(), ['count' => $task->daysOverdue()]) }}
                        @endif
                    </span>
                @endif
            @endif
        </div>

        @if($task)
            <x-task-progress :task="$task" class="mt-2 max-w-xs" />
        @endif
    </div>

    <!-- What can be done with this line -->
    <div class="flex shrink-0 items-center gap-1">
        <button type="button" wire:click="editItem({{ $item->id }})"
                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-[#3F5189] dark:hover:bg-slate-700"
                title="{{ $task ? __('Edit this item and its task') : __('Edit this item') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
        </button>

        @if($depth === 0)
            <button type="button" wire:click="openItemForm({{ $item->id }})"
                    class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-[#3F5189] dark:hover:bg-slate-700"
                    title="{{ __('Add a sub-item') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
        @endif

        <button type="button" wire:click="moveItem({{ $item->id }}, 'up')"
                @disabled(! $canUp)
                class="rounded p-1 text-slate-400 enabled:hover:bg-slate-100 enabled:hover:text-slate-700 disabled:opacity-30 dark:enabled:hover:bg-slate-700"
                title="{{ $canUp ? __('Move up') : __('Already first in this location — move the whole location from its heading.') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>

        <button type="button" wire:click="moveItem({{ $item->id }}, 'down')"
                @disabled(! $canDown)
                class="rounded p-1 text-slate-400 enabled:hover:bg-slate-100 enabled:hover:text-slate-700 disabled:opacity-30 dark:enabled:hover:bg-slate-700"
                title="{{ $canDown ? __('Move down') : __('Already last in this location — move the whole location from its heading.') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <button type="button"
                wire:click="removeItem({{ $item->id }})"
                wire:confirm="{{ $item->children()->exists()
                    ? __('Take this off the agenda? Its sub-items go with it. No task is closed or deleted.')
                    : __('Take this off the agenda? The task itself stays open and will be proposed again.') }}"
                class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                title="{{ __('Take off the agenda') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
