@props(['task', 'showLabel' => true])

@php
    $progress = (int) $task->progress;

    $bar = match (true) {
        $task->status === 'cancelled' => 'bg-slate-400 dark:bg-slate-500',
        $task->status === 'completed' => 'bg-green-500',
        $task->isOverdue() => 'bg-red-500',
        $task->status === 'blocked' => 'bg-red-400',
        $task->status === 'ready' => 'bg-amber-500',
        default => 'bg-[#3F5189] dark:bg-[#4A5A96]',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <div class="flex-1 h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden min-w-[3rem]">
        <div class="h-full rounded-full transition-all duration-300 {{ $bar }}" style="width: {{ $progress }}%"></div>
    </div>
    @if($showLabel)
        <span class="text-xs font-semibold tabular-nums text-slate-600 dark:text-slate-300 w-9 text-right">{{ $progress }}%</span>
    @endif
</div>
