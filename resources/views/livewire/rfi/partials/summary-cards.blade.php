{{--
    The four questions somebody actually opens this screen to ask. Counts come
    from the same visibleTo-narrowed query as the list, so they describe what
    this person may see rather than what exists.
--}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @php
        $cards = [
            ['label' => __('Open'), 'value' => $summary['live'], 'tone' => 'slate'],
            ['label' => __('collaboration.label.waiting_3'), 'value' => $summary['waiting_on_me'], 'tone' => 'indigo'],
            ['label' => __('collaboration.label.overdue'), 'value' => $summary['overdue'], 'tone' => 'rose'],
            ['label' => __('collaboration.label.closed'), 'value' => $summary['closed'], 'tone' => 'emerald'],
        ];
        $tones = [
            'slate' => 'text-slate-900 dark:text-white',
            'indigo' => 'text-indigo-600 dark:text-indigo-400',
            'rose' => 'text-rose-600 dark:text-rose-400',
            'emerald' => 'text-emerald-600 dark:text-emerald-400',
        ];
    @endphp

    @foreach($cards as $card)
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $tones[$card['tone']] }}">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>
