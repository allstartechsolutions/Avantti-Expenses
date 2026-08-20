@props(['task'])

@php
    $palette = [
        'gray' => 'text-slate-500 dark:text-slate-400',
        'blue' => 'text-blue-600 dark:text-blue-400',
        'orange' => 'text-orange-600 dark:text-orange-400',
        'red' => 'text-red-600 dark:text-red-400',
    ];

    $classes = $palette[$task->getPriorityColor()] ?? $palette['gray'];
@endphp

{{-- Normal priority is the default and says nothing worth the ink. --}}
@if($task->priority !== 'normal')
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 text-xs font-medium {$classes}"]) }}>
        @if(in_array($task->priority, ['high', 'urgent'], true))
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        @endif
        {{ $task->getPriorityLabel() }}
    </span>
@endif
