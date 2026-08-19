@props(['category'])

{{-- The classes are written out in full: Tailwind only sees class names that
     appear literally in a scanned file, so an interpolated colour would ship
     unstyled. --}}
@php
    $classes = match ($category->value) {
        'plans' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
        'permits' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
        'contracts' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
        'submittals' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
        'rfi' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        'safety' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        'photos' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
        'reports' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
        'invoices' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
        'correspondence' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium '.$classes]) }}>
    {{ __($category->label()) }}
</span>
