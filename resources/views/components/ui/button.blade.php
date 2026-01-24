@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconPosition' => 'left',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
    
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
        'xl' => 'px-8 py-4 text-lg',
    ];
    
    $variantClasses = [
        'primary' => 'bg-gradient-to-r from-[#3F5189] to-[#4A5A96] text-white hover:from-[#364672] hover:to-[#415187] focus:ring-[#3F5189]/50 shadow-sm',
        'secondary' => 'bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600',
        'success' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500/50 shadow-sm',
        'warning' => 'bg-orange-600 text-white hover:bg-orange-700 focus:ring-orange-500/50 shadow-sm',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500/50 shadow-sm',
        'ghost' => 'text-slate-700 hover:bg-slate-100 focus:ring-slate-500/50 dark:text-slate-300 dark:hover:bg-slate-800',
        'outline' => 'border border-[#3F5189] text-[#3F5189] hover:bg-[#3F5189] hover:text-white focus:ring-[#3F5189]/50',
    ];
    
    $iconSizeClasses = [
        'sm' => 'w-4 h-4',
        'md' => 'w-4 h-4',
        'lg' => 'w-5 h-5',
        'xl' => 'w-6 h-6',
    ];
    
    $classes = $baseClasses . ' ' . $sizeClasses[$size] . ' ' . $variantClasses[$variant];
    $iconSize = $iconSizeClasses[$size];
    
    $attributes = $attributes->merge(['class' => $classes]);
    if ($disabled) {
        $attributes = $attributes->merge(['disabled' => true]);
    }
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon && $iconPosition === 'left')
            <x-dynamic-component :component="'ui.icon'" :name="$icon" :class="$iconSize . ' mr-2'" />
        @endif
        
        {{ $slot }}
        
        @if($icon && $iconPosition === 'right')
            <x-dynamic-component :component="'ui.icon'" :name="$icon" :class="$iconSize . ' ml-2'" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes }}>
        @if($icon && $iconPosition === 'left')
            <x-dynamic-component :component="'ui.icon'" :name="$icon" :class="$iconSize . ' mr-2'" />
        @endif
        
        {{ $slot }}
        
        @if($icon && $iconPosition === 'right')
            <x-dynamic-component :component="'ui.icon'" :name="$icon" :class="$iconSize . ' ml-2'" />
        @endif
    </button>
@endif