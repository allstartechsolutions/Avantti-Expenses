<div>
    @php
        $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $totals = $grid['totals'];
        $overBudget = $totals['over_budget'];
        $hiddenCount = $grid['hidden_count'] ?? 0;
        $pdfParams = $showEmpty ? ['all' => 1] : [];
    @endphp

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Cost Code Grid') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $budget->name }} &bull; {{ $budget->location_name }} &bull;
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $budget->project->project_name }}</span>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-ui.button variant="secondary" href="{{ route('budgets.show', $budget->id) }}" icon="arrow-left">
                    {{ __('Back to Budget') }}
                </x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('budgets.cost-grid.pdf.view', ['budget' => $budget->id] + $pdfParams) }}" target="_blank" icon="eye">
                    {{ __('View PDF') }}
                </x-ui.button>
                <x-ui.button variant="primary" href="{{ route('budgets.cost-grid.pdf.download', ['budget' => $budget->id] + $pdfParams) }}" icon="download">
                    {{ __('Download PDF') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Original Budget') }}</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $fmt($totals['original']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Approved Changes') }}</p>
            <p class="text-xl font-bold mt-1 {{ (float) $totals['changes'] == 0.0 ? 'text-slate-400 dark:text-slate-500' : ((float) $totals['changes'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400') }}">
                {{ (float) $totals['changes'] == 0.0 ? $fmt(0) : $signed($totals['changes']) }}
            </p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Revised Budget') }}</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $fmt($totals['revised']) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Committed') }}</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $fmt($totals['committed']) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Contracts and purchase orders') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Actual') }}</p>
            <p class="text-xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $fmt($totals['actual']) }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Expenses and contract payments') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border p-4 {{ $overBudget ? 'border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/20' : 'border-slate-200 dark:border-slate-700' }}">
            <p class="text-sm {{ $overBudget ? 'text-red-700 dark:text-red-300' : 'text-slate-500 dark:text-slate-400' }}">{{ __('Remaining') }}</p>
            <p class="text-xl font-bold mt-1 {{ (float) $totals['remaining'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                {{ $fmt($totals['remaining']) }}
            </p>
            <p class="text-xs {{ $overBudget ? 'text-red-700 dark:text-red-300' : 'text-slate-500 dark:text-slate-400' }} mt-1">
                {{ $totals['percent_committed'] === null ? __('Nothing budgeted yet') : __(':percent% of the revised budget used', ['percent' => number_format($totals['percent_committed'], 0)]) }}
            </p>
        </div>
    </div>

    @if($overBudget)
        <div class="mb-6 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-red-600 dark:text-red-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z"></path>
            </svg>
            <p class="text-sm text-red-900 dark:text-red-200">
                {{ __('Committed and actual costs exceed the revised budget by :amount.', ['amount' => $fmt(abs($totals['remaining']))]) }}
            </p>
        </div>
    @endif

    <!-- Grid -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                @if($showEmpty)
                    {{ __('Every cost code on this budget, including the ones nothing has been budgeted or spent on.') }}
                @elseif($hiddenCount > 0)
                    {{ trans_choice('{1} :count cost code with no budget and no activity is hidden.|[2,*] :count cost codes with no budget and no activity are hidden.', $hiddenCount, ['count' => $hiddenCount]) }}
                @else
                    {{ __('Every cost code on this budget has figures against it.') }}
                @endif
            </p>
            <x-ui.toggle wire:model.live="showEmpty" :checked="$showEmpty" label="{{ __('Show empty cost codes') }}" />
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px]">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost Code') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Original') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Changes') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Revised') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Committed') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actual') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Projected') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Remaining') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Used') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grid['sections'] as $section)
                        @php
                            $parentRow = $section['rows'][0];
                            $childRows = array_slice($section['rows'], 1);
                        @endphp

                        <!-- Section header -->
                        <tr class="bg-slate-100 dark:bg-slate-900/70 border-t border-slate-200 dark:border-slate-700">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('budgets.cost-code', [$budget->id, $parentRow['budget_item_id']]) }}" class="group">
                                    <span class="px-2 py-0.5 text-xs font-mono font-semibold rounded bg-[#3F5189] text-white mr-2">{{ $parentRow['code'] }}</span>
                                    <span class="font-semibold text-slate-900 dark:text-white group-hover:underline">{{ $parentRow['name'] }}</span>
                                </a>
                                @if($parentRow['is_default'])
                                    <span class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                @endif
                            </td>
                            @include('livewire.budget.partials.ledger-row-cells', ['row' => $parentRow, 'emphasis' => 'section'])
                        </tr>

                        <!-- Lines -->
                        @foreach($childRows as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 border-t border-slate-100 dark:border-slate-700/50">
                                <td class="px-4 py-2 pl-10 text-sm text-slate-900 dark:text-white">
                                    <a href="{{ route('budgets.cost-code', [$budget->id, $row['budget_item_id']]) }}" class="hover:underline">
                                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400 mr-2">{{ $row['code'] }}</span>{{ $row['name'] }}
                                    </a>
                                    @if($row['is_default'])
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                    @endif
                                </td>
                                @include('livewire.budget.partials.ledger-row-cells', ['row' => $row, 'emphasis' => 'line'])
                            </tr>
                        @endforeach

                        <!-- Section subtotal -->
                        <tr class="bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-slate-700">
                            <td class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                                {{ __('Sub-Total') }}
                                @if($section['pct_of_budget'] !== null)
                                    <span class="ml-2 text-xs text-slate-400 dark:text-slate-500">{{ number_format($section['pct_of_budget'], 1) }}% {{ __('of budget') }}</span>
                                @endif
                            </td>
                            @include('livewire.budget.partials.ledger-row-cells', ['row' => $section['subtotal'], 'emphasis' => 'subtotal'])
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                @if($hiddenCount > 0)
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Nothing has been budgeted or spent yet.') }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ trans_choice('{1} The one cost code on this budget is empty, so it is hidden.|[2,*] All :count cost codes on this budget are empty, so they are hidden.', $hiddenCount, ['count' => $hiddenCount]) }}</p>
                                    <div class="mt-4">
                                        <x-ui.button variant="secondary" size="sm" wire:click="$set('showEmpty', true)" icon="eye">
                                            {{ __('Show empty cost codes') }}
                                        </x-ui.button>
                                    </div>
                                @else
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('This budget has no cost codes yet.') }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Add cost codes on the budget page, or apply a template, and every expense, contract and change order will report against them here.') }}</p>
                                    <div class="mt-4">
                                        <x-ui.button variant="primary" size="sm" href="{{ route('budgets.show', $budget->id) }}" icon="plus">
                                            {{ __('Add Cost Codes') }}
                                        </x-ui.button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforelse

                    <!-- Unassigned bucket (only when no default code is set) -->
                    @if($grid['unassigned'])
                        <tr class="bg-amber-50 dark:bg-amber-900/20 border-t border-amber-200 dark:border-amber-800">
                            <td class="px-4 py-2.5 text-sm text-amber-900 dark:text-amber-200">
                                <a href="{{ route('budgets.unassigned', $budget->id) }}" class="italic hover:underline">{{ $grid['unassigned']['name'] }}</a>
                                <span class="block text-xs text-amber-700 dark:text-amber-300">{{ __('Set a default cost code on the budget page and these amounts will land there instead.') }}</span>
                            </td>
                            @include('livewire.budget.partials.ledger-row-cells', ['row' => $grid['unassigned'], 'emphasis' => 'line'])
                        </tr>
                    @endif
                </tbody>
                <tfoot class="bg-slate-50 dark:bg-slate-900/50 border-t-2 border-slate-300 dark:border-slate-600">
                    <tr>
                        <td class="px-4 py-3 text-sm font-bold text-slate-900 dark:text-white">{{ __('Total') }}</td>
                        @include('livewire.budget.partials.ledger-row-cells', ['row' => $totals, 'emphasis' => 'total'])
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 space-y-1">
            <p><span class="font-medium">{{ __('Changes') }}</span> — {{ __('approved change orders only. A change order still in draft, pending or rejected does not move the budget.') }}</p>
            <p><span class="font-medium">{{ __('Committed') }}</span> — {{ __('subcontracts and their change orders, plus purchase orders awaiting approval. An approved purchase order has already become an expense, so it is counted as actual instead.') }}</p>
            <p><span class="font-medium">{{ __('Projected') }}</span> — {{ __('committed plus expenses. Contract payments are left out of this sum because they are already inside the contract value.') }}</p>
        </div>
    </div>
</div>
