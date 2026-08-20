@props(['task', 'size' => 'sm'])

@php
    // Written out rather than composed, because Tailwind only ships classes it
    // can see as complete strings.
    $palette = [
        'gray' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        'yellow' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        'green' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
        'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
    ];

    $classes = $palette[$task->getStatusColor()] ?? $palette['gray'];
    $sizeClasses = $size === 'md' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-medium whitespace-nowrap {$sizeClasses} {$classes}"]) }}>
    {{ $task->getStatusLabel() }}
</span>
