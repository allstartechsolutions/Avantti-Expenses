{{-- One tickable line in the carry-forward panel. Expects: $task, $indent --}}
@php
    $history = app(App\Services\MeetingAgendaService::class)->history($task);
    $indent = $indent ?? false;
@endphp

<label class="mt-2 flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-slate-50 dark:hover:bg-slate-700/40 {{ $indent ? 'ml-5 border-l-2 border-slate-200 pl-3 dark:border-slate-600' : '' }}"
       wire:key="carry-{{ $task->id }}">
    <input type="checkbox"
           wire:click="toggleCarry({{ $task->id }})"
           @checked(in_array($task->id, $carrySelected, true))
           class="mt-1 rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">

    <span class="min-w-0 flex-1">
        <span class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-xs text-slate-400">{{ $task->code() }}</span>
            <span class="text-sm text-slate-800 dark:text-slate-100">{{ $task->title }}</span>
            <x-task-status-badge :task="$task" />
        </span>

        <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
            <span>{{ $task->owner?->name }}</span>
            @if($task->due_date)
                <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">
                    {{ $task->due_date->appDate() }}
                    @if($task->isOverdue())
                        · {{ trans_choice(':count day late|:count days late', $task->daysOverdue(), ['count' => $task->daysOverdue()]) }}
                    @endif
                </span>
            @endif
            <span>{{ $task->progress }}%</span>
        </span>

        @if($history['first_meeting'])
            <span class="mt-1 block text-xs text-slate-400 dark:text-slate-500">
                {{ __('open since :number', ['number' => $history['first_meeting']]) }}
                · {{ trans_choice(':count meeting|:count meetings', $history['meetings'], ['count' => $history['meetings']]) }}
            </span>
        @endif

        @if($task->relationLoaded('notes') && $task->notes->isNotEmpty())
            <span class="mt-1 block truncate text-xs italic text-slate-400 dark:text-slate-500">
                “{{ \Illuminate\Support\Str::limit($task->notes->first()->body, 70) }}”
            </span>
        @endif
    </span>
</label>
