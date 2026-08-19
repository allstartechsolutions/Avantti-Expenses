{{--
    The comparison map (mapa comparativo) — items as rows, proposals as columns.
    Expects: $comparison
--}}
@php
    $factLabel = 'text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $money = fn ($value) => Number::currency((float) $value, config('app.currency'), config('app.locale'));
@endphp

<x-ui.modal name="quotation-comparison-modal" maxWidth="full" layer="top">
    @if($comparison)
        @php
            $quotation = $comparison['quotation'];
            $columns = $comparison['columns'];
            $rows = $comparison['rows'];
            $summary = $comparison['summary'];
        @endphp

        <div class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-30 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white truncate">{{ __('Comparison Map') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ $quotation->quotation_number ?? '#'.$quotation->id }} &middot; {{ $quotation->title }}
                            &middot; {{ trans_choice(':count proposal|:count proposals', $summary['proposals'], ['count' => $summary['proposals']]) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <x-ui.button
                            variant="secondary"
                            size="sm"
                            icon="download"
                            href="{{ route('quotations.map.pdf.download', $quotation->id) }}">
                            {{ __('PDF') }}
                        </x-ui.button>
                        <button
                            type="button"
                            wire:click="closeComparisonModal"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            title="{{ __('Close') }}">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
                @if($summary['proposals'] === 0)
                    <!-- Designed empty state: say what is missing and what to do -->
                    <div class="{{ $card }} text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('Nothing to compare yet') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ trans_choice(
                                'One vendor was asked and has not answered. Key their proposal in as soon as it arrives.|:count vendors were asked and none has answered yet. Key each proposal in as it arrives.',
                                $comparison['awaiting']->count(),
                                ['count' => $comparison['awaiting']->count()]
                            ) }}
                        </p>
                    </div>
                @else
                    <!-- What the round is worth -->
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                            <p class="text-sm font-medium text-white/80">{{ __('Lowest Equalized Offer') }}</p>
                            <p class="text-2xl font-bold mt-1">{{ $money($summary['lowest']) }}</p>
                            <p class="mt-2 text-sm text-white/80">{{ $summary['lowest_vendor'] ?? '—' }}</p>
                        </div>

                        <div class="{{ $card }}">
                            <p class="{{ $factLabel }}">{{ __('Saving vs the Highest') }}</p>
                            <p class="mt-1 text-2xl font-bold {{ $summary['saving_vs_highest'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-900 dark:text-white' }}">
                                {{ $money($summary['saving_vs_highest']) }}
                            </p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                @if($summary['comparable'] > 1)
                                    {{ __('Highest comparable offer :amount', ['amount' => $money($summary['highest'])]) }}
                                @else
                                    {{ __('Only one comparable offer — nothing to measure a saving against.') }}
                                @endif
                            </p>
                        </div>

                        <div class="{{ $card }}">
                            <p class="{{ $factLabel }}">{{ __('If Split Line by Line') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $money($summary['split_total']) }}</p>
                            <p class="mt-2 text-xs {{ $summary['split_saving'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' }}">
                                {{ $summary['split_saving'] > 0
                                    ? __(':amount below the single winner', ['amount' => $money($summary['split_saving'])])
                                    : __('No better than awarding one vendor') }}
                            </p>
                        </div>

                        <div class="{{ $card }}">
                            <p class="{{ $factLabel }}">{{ __('Against the Budget') }}</p>
                            @if($summary['budget_amount'] !== null)
                                <p class="mt-1 text-2xl font-bold {{ $summary['budget_delta'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ $summary['budget_delta'] > 0 ? '+' : '' }}{{ $money($summary['budget_delta']) }}
                                </p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Budgeted :amount', ['amount' => $money($summary['budget_amount'])]) }}
                                    &middot; {{ $quotation->budgetItem->code }}
                                </p>
                            @else
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('No budget item linked to this round.') }}</p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Link one on the round to compare against what was budgeted.') }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- Warnings that must not be buried -->
                    @if(! $summary['meets_minimum'] || ! $summary['meets_norm'] || $summary['expired'] > 0 || $summary['incomplete'] > 0)
                        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5 space-y-1">
                            @unless($summary['meets_minimum'])
                                <p class="text-sm text-amber-900 dark:text-amber-200">{{ __('Fewer than two proposals — an award will be blocked.') }}</p>
                            @elseunless($summary['meets_norm'])
                                <p class="text-sm text-amber-900 dark:text-amber-200">{{ __('Two proposals — the Brazilian norm is three.') }}</p>
                            @endunless
                            @if($summary['expired'] > 0)
                                <p class="text-sm text-amber-900 dark:text-amber-200">
                                    {{ trans_choice('One proposal has expired and is excluded from the benchmark.|:count proposals have expired and are excluded from the benchmark.', $summary['expired'], ['count' => $summary['expired']]) }}
                                </p>
                            @endif
                            @if($summary['incomplete'] > 0)
                                <p class="text-sm text-amber-900 dark:text-amber-200">
                                    {{ trans_choice('One proposal does not cover the whole scope, so its total is not comparable.|:count proposals do not cover the whole scope, so their totals are not comparable.', $summary['incomplete'], ['count' => $summary['incomplete']]) }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <!-- The map -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900/50">
                                        <th class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 min-w-[220px]">
                                            {{ __('Item') }}
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ __('Qty') }}</th>
                                        @foreach($columns as $column)
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider min-w-[150px]
                                                {{ $column['is_lowest'] ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300' : 'text-slate-500 dark:text-slate-400' }}">
                                                <div class="font-semibold normal-case text-sm {{ $column['is_lowest'] ? '' : 'text-slate-900 dark:text-white' }}">
                                                    {{ $column['vendor_name'] }}
                                                </div>
                                                @if($column['is_awarded'])
                                                    <div class="text-[11px] font-semibold text-green-700 dark:text-green-300">{{ __('Awarded') }}</div>
                                                @endif
                                                @if($column['is_lowest'])
                                                    <div class="text-[11px] font-medium">{{ __('Lowest') }}</div>
                                                @endif
                                                @if($column['expired'])
                                                    <div class="text-[11px] font-medium text-red-600 dark:text-red-400">{{ __('Expired') }}</div>
                                                @endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($rows as $row)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                            <td class="sticky left-0 z-10 bg-white dark:bg-slate-800 px-4 py-3 align-top">
                                                <div class="font-medium text-slate-900 dark:text-white">{{ $row['item']->item_name }}</div>
                                                @if($row['item']->description)
                                                    <div class="text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line">{{ $row['item']->description }}</div>
                                                @endif
                                                @if($row['spread'] > 0)
                                                    <div class="text-xs text-slate-400 dark:text-slate-500">
                                                        {{ __('spread :amount', ['amount' => $money($row['spread'])]) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right align-top whitespace-nowrap text-slate-600 dark:text-slate-300">
                                                {{ rtrim(rtrim(number_format((float) $row['item']->quantity, 2, '.', ''), '0'), '.') }}
                                                <span class="text-xs text-slate-400 dark:text-slate-500">{{ $row['item']->unit }}</span>
                                            </td>
                                            @foreach($row['cells'] as $cell)
                                                <td class="px-4 py-3 text-right align-top
                                                    {{ $cell['is_best'] ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                                    @if($cell['state'] === 'priced')
                                                        <div class="font-semibold {{ $cell['is_best'] ? 'text-green-700 dark:text-green-300' : 'text-slate-900 dark:text-white' }}">
                                                            {{ $money($cell['total']) }}
                                                        </div>
                                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                                            {{ $money($cell['unit_price']) }} / {{ $row['item']->unit ?: __('un') }}
                                                        </div>
                                                        @if($cell['brand'] || $cell['spec'])
                                                            <div class="mt-1 text-xs text-amber-600 dark:text-amber-400" title="{{ $cell['spec'] }}">
                                                                {{ $cell['brand'] ? __('substitute: :brand', ['brand' => $cell['brand']]) : __('substitute offered') }}
                                                            </div>
                                                        @endif
                                                        @if($cell['notes'])
                                                            <div class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $cell['notes'] }}</div>
                                                        @endif
                                                    @elseif($cell['state'] === 'unavailable')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300">
                                                            {{ __('Cannot supply') }}
                                                        </span>
                                                        @if($cell['notes'])
                                                            <div class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $cell['notes'] }}</div>
                                                        @endif
                                                    @else
                                                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('Not quoted') }}</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>

                                <!-- Equalization, in the open -->
                                <tfoot class="bg-slate-50 dark:bg-slate-900/50 divide-y divide-slate-200 dark:divide-slate-700">
                                    <tr>
                                        <td class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/50 px-4 py-2 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" colspan="2">{{ __('Lines') }}</td>
                                        @foreach($columns as $column)
                                            <td class="px-4 py-2 text-right text-slate-700 dark:text-slate-200 {{ $column['is_lowest'] ? 'bg-green-50 dark:bg-green-900/20' : '' }}">{{ $money($column['subtotal']) }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/50 px-4 py-2 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" colspan="2">{{ __('Freight') }}</td>
                                        @foreach($columns as $column)
                                            <td class="px-4 py-2 text-right text-slate-700 dark:text-slate-200 {{ $column['is_lowest'] ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                                {{ $money($column['freight']) }}
                                                @if($column['freight_type'])
                                                    <span class="text-xs text-slate-400 dark:text-slate-500">{{ strtoupper($column['freight_type']) }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/50 px-4 py-2 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" colspan="2">{{ __('Taxes') }}</td>
                                        @foreach($columns as $column)
                                            <td class="px-4 py-2 text-right text-slate-700 dark:text-slate-200 {{ $column['is_lowest'] ? 'bg-green-50 dark:bg-green-900/20' : '' }}">{{ $money($column['tax']) }}</td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/50 px-4 py-2 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" colspan="2">{{ __('Discount') }}</td>
                                        @foreach($columns as $column)
                                            <td class="px-4 py-2 text-right text-slate-700 dark:text-slate-200 {{ $column['is_lowest'] ? 'bg-green-50 dark:bg-green-900/20' : '' }}">− {{ $money($column['discount']) }}</td>
                                        @endforeach
                                    </tr>
                                    <tr class="bg-slate-100 dark:bg-slate-900">
                                        <td class="sticky left-0 z-10 bg-slate-100 dark:bg-slate-900 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200" colspan="2">{{ __('Equalized Total') }}</td>
                                        @foreach($columns as $column)
                                            <td class="px-4 py-3 text-right {{ $column['is_lowest'] ? 'bg-green-100 dark:bg-green-900/30' : '' }}">
                                                <div class="text-base font-bold {{ $column['is_lowest'] ? 'text-green-700 dark:text-green-300' : 'text-slate-900 dark:text-white' }}">
                                                    {{ $money($column['total']) }}
                                                </div>
                                                @if($column['delta_to_lowest'] > 0)
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">+ {{ $money($column['delta_to_lowest']) }}</div>
                                                @endif
                                                @if($column['negotiated_rounds'] > 0)
                                                    <div class="text-xs text-green-600 dark:text-green-400">
                                                        {{ __('was :amount', ['amount' => $money($column['opening_total'])]) }}
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                    <tr>
                                        <td class="sticky left-0 z-10 bg-slate-50 dark:bg-slate-900/50 px-4 py-2 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" colspan="2">{{ __('Terms') }}</td>
                                        @foreach($columns as $column)
                                            <td class="px-4 py-2 text-right text-xs text-slate-500 dark:text-slate-400 {{ $column['is_lowest'] ? 'bg-green-50 dark:bg-green-900/20' : '' }}">
                                                @if($column['lead_time_days'] !== null)
                                                    <div>{{ trans_choice(':count day|:count days', $column['lead_time_days'], ['count' => $column['lead_time_days']]) }}</div>
                                                @endif
                                                @if($column['payment_terms'])
                                                    <div>{{ $column['payment_terms'] }}</div>
                                                @endif
                                                @if($column['valid_until'])
                                                    <div class="{{ $column['expired'] ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                                        {{ $column['expired']
                                                            ? __('Expired :date', ['date' => $column['valid_until']->format('M d, Y')])
                                                            : __('Valid to :date', ['date' => $column['valid_until']->format('M d, Y')]) }}
                                                    </div>
                                                @endif
                                                @if($column['unquoted'] > 0)
                                                    <div class="text-red-600 dark:text-red-400">{{ __(':count not quoted', ['count' => $column['unquoted']]) }}</div>
                                                @endif
                                                @if($column['unavailable'] > 0)
                                                    <div class="text-amber-600 dark:text-amber-400">{{ __(':count not supplied', ['count' => $column['unavailable']]) }}</div>
                                                @endif
                                                @if($column['substitutes'] > 0)
                                                    <div class="text-amber-600 dark:text-amber-400">{{ __(':count substituted', ['count' => $column['substitutes']]) }}</div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Totals are equalized: lines plus freight and taxes, less discount. The benchmark is the cheapest proposal that covers the whole scope and has not expired.') }}
                        @if($summary['negotiated_saving'] > 0)
                            {{ __('Negotiation has taken :amount off the offers on this map.', ['amount' => $money($summary['negotiated_saving'])]) }}
                        @endif
                    </p>
                @endif

                <!-- Who has not answered -->
                @if($comparison['awaiting']->count() > 0 || $comparison['declined']->count() > 0)
                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Not on the Map') }}</h3>
                        <ul class="space-y-2">
                            @foreach($comparison['awaiting'] as $row)
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="text-slate-900 dark:text-white">{{ $row->vendor?->name ?? __('Unknown') }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $row->invited_at ? __('Asked :date, no answer yet', ['date' => $row->invited_at->format('M d, Y')]) : __('Not asked yet') }}
                                    </span>
                                </li>
                            @endforeach
                            @foreach($comparison['declined'] as $row)
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <span class="text-slate-900 dark:text-white">{{ $row->vendor?->name ?? __('Unknown') }}</span>
                                    <span class="text-xs text-red-600 dark:text-red-400">{{ __('Declined to quote') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $quotation->isAwarded()
                            ? __('This round has been awarded. The map is kept as it stood.')
                            : __('The award carries a written reason, so a choice other than the cheapest can be defended later.') }}
                    </p>
                    <div class="flex items-center gap-3">
                        @if((auth()->user()?->canReviewRequisitions() ?? false) && $quotation->canBeAwarded())
                            <x-ui.button variant="success" icon="check" wire:click="openAwardModal({{ $quotation->id }})">
                                {{ __('Award the Round') }}
                            </x-ui.button>
                        @endif
                        <x-ui.button variant="secondary" wire:click="closeComparisonModal">{{ __('Close') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-ui.modal>
