{{--
    Counts from the same visibleTo-narrowed query as the list, so they describe
    what this person may see rather than what exists.

    The certificate card only appears where there are certificates: a lapsing
    count of zero on a project with none is noise, and on a BR job with many it
    is the most useful number on the screen.
--}}
@php
    $cards = [
        ['label' => __('collaboration.label.review'), 'value' => $summary['live'], 'tone' => 'text-slate-900 dark:text-white'],
        ['label' => __('collaboration.label.waiting_3'), 'value' => $summary['awaiting_me'], 'tone' => 'text-indigo-600 dark:text-indigo-400'],
        ['label' => __('collaboration.label.overdue'), 'value' => $summary['overdue'], 'tone' => 'text-rose-600 dark:text-rose-400'],
        ['label' => __('collaboration.label.approved'), 'value' => $summary['approved'], 'tone' => 'text-emerald-600 dark:text-emerald-400'],
    ];

    if ($summary['certificates_lapsing'] > 0) {
        $cards[] = [
            'label' => __('collaboration.label.certificates_lapsing'),
            'value' => $summary['certificates_lapsing'],
            'tone' => 'text-amber-600 dark:text-amber-400',
        ];
    }
@endphp

<div class="grid grid-cols-2 lg:grid-cols-{{ count($cards) }} gap-4">
    @foreach($cards as $card)
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $card['tone'] }}">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>
