@props(['active' => true])

{{-- The vendor's Active / Inactive chip, worded once for every list and page. --}}
<span {{ $attributes->class([
    'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' => $active,
    'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300' => ! $active,
]) }}>{{ \App\Models\Vendor::activeLabel((bool) $active) }}</span>
