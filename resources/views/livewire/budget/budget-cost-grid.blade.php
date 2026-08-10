<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Cost Code Grid') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $budget->name }} &bull; {{ $budget->location_name }} &bull;
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $budget->project->project_name }}</span>
                </p>
            </div>
            <div>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('budgets.show', $budget->id) }}"
                    icon="arrow-left">
                    {{ __('Back to Budget') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    @php
        $fmt = fn ($v) => Number::currency($v, config('app.currency'), config('app.locale'));
        $pct = fn ($v) => $v === null ? null : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') . '%';
    @endphp

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Budgeted') }}</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $fmt($grid['totals']['budgeted']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Contracted') }}</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $fmt($grid['totals']['contracted']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Paid') }}</p>
            <p class="text-xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $fmt($grid['totals']['paid']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Balance') }}</p>
            <p class="text-xl font-bold {{ $grid['totals']['balance'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-slate-900 dark:text-white' }} mt-1">{{ $fmt($grid['totals']['balance']) }}</p>
        </div>
    </div>

    <!-- Grid -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 dark:bg-slate-900/50 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost Code') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Budgeted') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contracted') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('% Complete') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grid['sections'] as $section)
                        @php
                            $parentRow = $section['rows'][0];
                            $childRows = array_slice($section['rows'], 1);
                            $parentHasValues = $parentRow['budgeted'] != 0 || $parentRow['contracted'] != 0 || $parentRow['paid'] != 0;
                        @endphp

                        <!-- Section header -->
                        <tr class="bg-slate-100 dark:bg-slate-900/70 border-t border-slate-200 dark:border-slate-700">
                            <td colspan="{{ $parentHasValues ? 1 : 6 }}" class="px-4 py-2.5">
                                <span class="px-2 py-0.5 text-xs font-mono font-semibold rounded bg-[#3F5189] text-white mr-2">{{ $parentRow['code'] }}</span>
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $parentRow['name'] }}</span>
                                @if($parentRow['is_default'])
                                    <span class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                @endif
                            </td>
                            @if($parentHasValues)
                                <td class="px-4 py-2.5 text-sm text-right font-medium text-slate-900 dark:text-white">{{ $fmt($parentRow['budgeted']) }}</td>
                                <td class="px-4 py-2.5 text-sm text-right font-medium text-slate-900 dark:text-white">{{ $fmt($parentRow['contracted']) }}</td>
                                <td class="px-4 py-2.5 text-sm text-right font-medium {{ $parentRow['paid'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $fmt($parentRow['paid']) }}</td>
                                <td class="px-4 py-2.5 text-sm text-right text-slate-700 dark:text-slate-300">{{ $pct($parentRow['percent']) ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-sm text-right font-medium text-slate-900 dark:text-white">{{ $fmt($parentRow['balance']) }}</td>
                            @endif
                        </tr>

                        <!-- Lines -->
                        @foreach($childRows as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 border-t border-slate-100 dark:border-slate-700/50">
                                <td class="px-4 py-2 pl-10 text-sm text-slate-900 dark:text-white">
                                    <span class="font-mono text-xs text-slate-500 dark:text-slate-400 mr-2">{{ $row['code'] }}</span>
                                    {{ $row['name'] }}
                                    @if($row['is_default'])
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($row['budgeted']) }}</td>
                                <td class="px-4 py-2 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($row['contracted']) }}</td>
                                <td class="px-4 py-2 text-sm text-right {{ $row['paid'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $fmt($row['paid']) }}</td>
                                <td class="px-4 py-2 text-right">
                                    @if($row['percent'] !== null)
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-14 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                                <div class="h-full bg-[#3F5189] dark:bg-[#4A5A96] rounded-full" style="width: {{ min(100, max(0, $row['percent'])) }}%"></div>
                                            </div>
                                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ $pct($row['percent']) }}</span>
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-sm text-right {{ $row['balance'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-slate-700 dark:text-slate-300' }}">{{ $fmt($row['balance']) }}</td>
                            </tr>
                        @endforeach

                        <!-- Section subtotal -->
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-slate-700">
                            <td class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                                {{ __('Sub-Total') }}
                                @if($section['pct_of_budget'] !== null)
                                    <span class="ml-2 text-xs text-slate-400 dark:text-slate-500">{{ $pct($section['pct_of_budget']) }} {{ __('of budget') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">{{ $fmt($section['subtotal']['budgeted']) }}</td>
                            <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">{{ $fmt($section['subtotal']['contracted']) }}</td>
                            <td class="px-4 py-2 text-sm text-right font-semibold text-green-600 dark:text-green-400">{{ $fmt($section['subtotal']['paid']) }}</td>
                            <td class="px-4 py-2 text-sm text-right text-slate-700 dark:text-slate-300">{{ $pct($section['subtotal']['percent']) ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">{{ $fmt($section['subtotal']['balance']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-500 dark:text-slate-400">
                                {{ __('This budget has no cost codes yet.') }}
                            </td>
                        </tr>
                    @endforelse

                    <!-- Unassigned bucket (only when no default code is set) -->
                    @if($grid['unassigned'])
                        <tr class="bg-slate-100 dark:bg-slate-900/70 border-t border-slate-200 dark:border-slate-700">
                            <td class="px-4 py-2.5 text-sm italic text-slate-500 dark:text-slate-400">{{ $grid['unassigned']['name'] }}</td>
                            <td class="px-4 py-2.5 text-sm text-right text-slate-500 dark:text-slate-400">{{ $fmt($grid['unassigned']['budgeted']) }}</td>
                            <td class="px-4 py-2.5 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($grid['unassigned']['contracted']) }}</td>
                            <td class="px-4 py-2.5 text-sm text-right {{ $grid['unassigned']['paid'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $fmt($grid['unassigned']['paid']) }}</td>
                            <td class="px-4 py-2.5 text-sm text-right text-slate-400 dark:text-slate-500">—</td>
                            <td class="px-4 py-2.5 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($grid['unassigned']['balance']) }}</td>
                        </tr>
                    @endif
                </tbody>
                <tfoot class="bg-slate-50 dark:bg-slate-900/50 border-t-2 border-slate-300 dark:border-slate-600">
                    <tr>
                        <td class="px-4 py-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Total') }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-slate-900 dark:text-white">{{ $fmt($grid['totals']['budgeted']) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-slate-900 dark:text-white">{{ $fmt($grid['totals']['contracted']) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-green-600 dark:text-green-400">{{ $fmt($grid['totals']['paid']) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-slate-700 dark:text-slate-300">{{ $pct($grid['totals']['percent']) ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-slate-900 dark:text-white">{{ $fmt($grid['totals']['balance']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
