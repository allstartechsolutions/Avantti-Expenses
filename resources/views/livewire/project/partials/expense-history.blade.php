{{--
    The expense's change history.

    Admin-only until M4; now behind `expenses.edit_paid`, the same grant that
    lets somebody alter settled money. That reproduces today's answer exactly —
    the seeds keep `edit_paid` to administrators — and makes it one tick to hand
    to an auditor. Whether it should instead follow `expenses.view` is an open
    question for the owner; see docs/review-and-improvements.md.
--}}
@can('expenses.edit_paid', $viewingExpense)
    @if(!empty($expenseHistory))
        <div class="mt-6 border-t border-slate-200 dark:border-slate-700 pt-4">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">{{ __('History') }}</h4>
            <div class="space-y-3 max-h-64 overflow-y-auto">
                @foreach($expenseHistory as $entry)
                    <div class="flex items-start gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 rounded-full flex-shrink-0
                            @switch($entry['color'])
                                @case('green') bg-green-500 @break
                                @case('yellow') bg-yellow-500 @break
                                @case('red') bg-red-500 @break
                                @case('blue') bg-blue-500 @break
                                @default bg-slate-400
                            @endswitch
                        "></span>
                        <div class="min-w-0">
                            <p class="text-slate-900 dark:text-white">
                                <span class="font-medium">{{ $entry['label'] }}</span>
                                @if($entry['user'])
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('by') }} {{ $entry['user'] }}</span>
                                @endif
                                <span class="text-xs text-slate-400 dark:text-slate-500 ml-1">{{ $entry['date'] }}</span>
                            </p>
                            @if(!empty($entry['changes']))
                                <ul class="mt-0.5 text-xs text-slate-500 dark:text-slate-400 space-y-0.5">
                                    @foreach($entry['changes'] as $field => $change)
                                        <li>
                                            {{ ucfirst(str_replace('_', ' ', $field)) }}:
                                            <span class="line-through">{{ $change['old'] ?? '—' }}</span>
                                            →
                                            <span class="text-slate-700 dark:text-slate-300">{{ $change['new'] ?? '—' }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endcan
