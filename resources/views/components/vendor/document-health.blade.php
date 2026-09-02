@props([
    // expired | expiring_soon | valid | none
    'state',
    'expired' => 0,
    'expiring' => 0,
    // 'quiet' shows nothing when everything is current — for lists, where a
    // green chip on every row is noise. 'full' always shows a chip.
    'mode' => 'quiet',
])

@php
    $chip = [
        'expired' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'expiring_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'valid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'none' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ][$state] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300';

    $label = \App\Models\Subcontractor::documentHealthLabel($state);

    $detail = collect([
        $expired > 0 ? trans_choice(':count expired|:count expired', $expired, ['count' => $expired]) : null,
        $expiring > 0 ? trans_choice(':count expiring soon|:count expiring soon', $expiring, ['count' => $expiring]) : null,
    ])->filter()->implode(' · ');
@endphp

@if($mode === 'full' || in_array($state, ['expired', 'expiring_soon'], true))
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium '.$chip]) }}
          @if($detail) title="{{ $detail }}" @endif>
        @if($state === 'expired')
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        @elseif($state === 'expiring_soon')
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
        @endif
        {{ $label }}
        @if($detail && $mode === 'full')
            <span class="font-normal opacity-80">&middot; {{ $detail }}</span>
        @endif
    </span>
@endif
