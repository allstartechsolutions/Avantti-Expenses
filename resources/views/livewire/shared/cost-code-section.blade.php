{{--
    Budget versus actual by cost code, for the financial reports.
    Expects: $costCodes — ['budgets' => [['budget' => Budget, 'grid' => grid]], 'totals' => ?row]
             $showLocation — label each budget with its location
--}}
@php
    $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
    $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));
@endphp

<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Budget by Cost Code') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Lifetime figures: the whole budget against everything committed and spent, whatever the report dates say elsewhere.') }}
            </p>
        </div>
        @if($costCodes['totals'])
            <div class="text-right">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ (float) $costCodes['totals']['remaining'] < 0 ? __('Over budget') : __('Remaining') }}</p>
                <p class="text-xl font-bold {{ (float) $costCodes['totals']['remaining'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                    {{ $fmt(abs($costCodes['totals']['remaining'])) }}
                </p>
            </div>
        @endif
    </div>

    @if(empty($costCodes['budgets']))
        <div class="px-6 py-8 text-center">
            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No budget yet') }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Create a budget with cost codes and every expense, contract and change order will report against it here.') }}
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px]">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost Code') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Original') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Changes') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Revised') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Committed') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actual') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Remaining') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($costCodes['budgets'] as $entry)
                        @php $grid = $entry['grid']; @endphp

                        @if($showLocation)
                            <tr class="bg-[#3F5189]/10 dark:bg-[#3F5189]/20 border-t border-slate-200 dark:border-slate-700">
                                <td colspan="7" class="px-4 py-2 text-sm font-semibold text-slate-900 dark:text-white">
                                    <a href="{{ route('budgets.cost-grid', $entry['budget']->id) }}" class="hover:underline">
                                        {{ $entry['budget']->location_name }}
                                    </a>
                                </td>
                            </tr>
                        @endif

                        @forelse($grid['sections'] as $section)
                            @foreach($section['rows'] as $row)
                                @continue((float) $row['revised'] == 0.0 && (float) $row['projected'] == 0.0)
                                <tr class="border-t border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white {{ $row['is_parent'] ? 'font-medium' : 'pl-8' }}">
                                        <a href="{{ route('budgets.cost-code', [$entry['budget']->id, $row['budget_item_id']]) }}" class="hover:underline">
                                            <span class="font-mono text-xs text-slate-500 dark:text-slate-400 mr-2">{{ $row['code'] }}</span>{{ $row['name'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($row['original']) }}</td>
                                    <td class="px-4 py-2 text-sm text-right {{ (float) $row['changes'] == 0.0 ? 'text-slate-400 dark:text-slate-500' : ((float) $row['changes'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400') }}">
                                        {{ (float) $row['changes'] == 0.0 ? '—' : $signed($row['changes']) }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right font-medium text-slate-900 dark:text-white">{{ $fmt($row['revised']) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($row['committed']) }}</td>
                                    <td class="px-4 py-2 text-sm text-right {{ (float) $row['actual'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-slate-500' }}">{{ $fmt($row['actual']) }}</td>
                                    <td class="px-4 py-2 text-sm text-right {{ (float) $row['remaining'] < 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-700 dark:text-slate-300' }}">{{ $fmt($row['remaining']) }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr class="border-t border-slate-100 dark:border-slate-700/50">
                                <td colspan="7" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ __('This budget has no cost codes yet.') }}</td>
                            </tr>
                        @endforelse

                        @if($grid['unassigned'])
                            <tr class="bg-amber-50 dark:bg-amber-900/20 border-t border-amber-200 dark:border-amber-800">
                                <td class="px-4 py-2 text-sm italic text-amber-900 dark:text-amber-200">{{ $grid['unassigned']['name'] }}</td>
                                <td class="px-4 py-2 text-sm text-right text-amber-900 dark:text-amber-200">{{ $fmt($grid['unassigned']['original']) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-amber-900 dark:text-amber-200">{{ (float) $grid['unassigned']['changes'] == 0.0 ? '—' : $signed($grid['unassigned']['changes']) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-amber-900 dark:text-amber-200">{{ $fmt($grid['unassigned']['revised']) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-amber-900 dark:text-amber-200">{{ $fmt($grid['unassigned']['committed']) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-amber-900 dark:text-amber-200">{{ $fmt($grid['unassigned']['actual']) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-amber-900 dark:text-amber-200">{{ $fmt($grid['unassigned']['remaining']) }}</td>
                            </tr>
                        @endif

                        @if($showLocation)
                            <tr class="bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-slate-700">
                                <td class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('Sub-Total') }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">{{ $fmt($grid['totals']['original']) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">{{ (float) $grid['totals']['changes'] == 0.0 ? '—' : $signed($grid['totals']['changes']) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">{{ $fmt($grid['totals']['revised']) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">{{ $fmt($grid['totals']['committed']) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold text-green-600 dark:text-green-400">{{ $fmt($grid['totals']['actual']) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-semibold {{ (float) $grid['totals']['remaining'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ $fmt($grid['totals']['remaining']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                @if($costCodes['totals'])
                    <tfoot class="bg-slate-50 dark:bg-slate-900/50 border-t-2 border-slate-300 dark:border-slate-600">
                        <tr>
                            <td class="px-4 py-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Total') }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-slate-900 dark:text-white">{{ $fmt($costCodes['totals']['original']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-slate-900 dark:text-white">{{ (float) $costCodes['totals']['changes'] == 0.0 ? '—' : $signed($costCodes['totals']['changes']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-slate-900 dark:text-white">{{ $fmt($costCodes['totals']['revised']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-slate-900 dark:text-white">{{ $fmt($costCodes['totals']['committed']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold text-green-600 dark:text-green-400">{{ $fmt($costCodes['totals']['actual']) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-bold {{ (float) $costCodes['totals']['remaining'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">{{ $fmt($costCodes['totals']['remaining']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
</div>
