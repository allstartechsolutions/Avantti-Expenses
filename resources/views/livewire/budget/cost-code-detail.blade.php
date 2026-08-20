<div>
    @php
        $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $signed = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $statusStyles = [
            'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
            'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
            'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        ];
        $statusLabels = [
            'draft' => __('Draft'), 'pending' => __('Pending'),
            'approved' => __('Approved'), 'rejected' => __('Rejected'),
        ];
        $spentPct = (float) $row['revised'] > 0 ? min(100, max(0, $row['actual'] / $row['revised'] * 100)) : null;
        $projPct = (float) $row['revised'] > 0 ? min(100, max(0, $row['projected'] / $row['revised'] * 100)) : null;
    @endphp

    <!-- Header -->
    <div class="mb-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0">
                @if($item?->parent)
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        <a href="{{ route('budgets.cost-code', [$budget->id, $item->parent->id]) }}" class="hover:underline">
                            {{ $item->parent->code }} - {{ $item->parent->name }}
                        </a>
                    </p>
                @endif
                <div class="flex items-center gap-3 flex-wrap">
                    @if($item)
                        <span class="px-2 py-1 text-sm font-mono font-semibold rounded bg-[#3F5189] text-white">{{ $item->code }}</span>
                    @endif
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $item?->name ?? __('Unassigned') }}</h1>
                    @if($item?->is_default)
                        <span class="px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                    @endif
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $budget->name }} &bull; {{ $budget->location_name }} &bull;
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $budget->project->project_name }}</span>
                </p>
                @if($item?->description)
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 max-w-3xl">{{ $item->description }}</p>
                @endif
                @if(! $item)
                    <p class="text-sm text-amber-700 dark:text-amber-300 mt-2 max-w-3xl">
                        {{ __('Costs recorded without a cost code. Star a code on the budget page to make it the default and these will land there instead.') }}
                    </p>
                @endif
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <x-ui.button variant="secondary" href="{{ route('budgets.cost-grid', $budget->id) }}" icon="arrow-left">
                    {{ __('Cost Grid') }}
                </x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('budgets.show', $budget->id) }}" icon="eye">
                    {{ __('Budget') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Where this code stands -->
    <div class="mb-6 bg-gradient-to-r from-[#3F5189] to-[#5A6FA8] rounded-lg p-6 text-white">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <p class="text-sm text-white/80">{{ __('Revised Budget') }}</p>
                <p class="text-3xl font-bold mt-1">{{ $fmt($row['revised']) }}</p>
                <p class="text-sm text-white/70 mt-1">
                    {{ __('Original') }} {{ $fmt($row['original']) }}
                    @if((float) $row['changes'] != 0.0)
                        &bull; {{ __('Approved changes') }} {{ $signed($row['changes']) }}
                    @endif
                </p>
            </div>
            <div class="lg:text-right">
                <p class="text-sm text-white/80">{{ (float) $row['remaining'] < 0 ? __('Over budget') : __('Remaining') }}</p>
                <p class="text-3xl font-bold mt-1 {{ (float) $row['remaining'] < 0 ? 'text-red-200' : '' }}">{{ $fmt(abs($row['remaining'])) }}</p>
                @if($projPct !== null)
                    <div class="mt-2 flex items-center gap-2 lg:justify-end">
                        <div class="relative w-40 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="absolute inset-y-0 left-0 bg-white/50" style="width: {{ $projPct }}%"></div>
                            <div class="absolute inset-y-0 left-0 bg-white" style="width: {{ $spentPct ?? 0 }}%"></div>
                        </div>
                        <span class="text-sm text-white/80">{{ number_format($row['percent_committed'], 0) }}% {{ __('used') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-white/20 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-white/70">{{ __('Committed') }}</p>
                <p class="font-semibold mt-0.5">{{ $fmt($row['committed']) }}</p>
            </div>
            <div>
                <p class="text-white/70">{{ __('Actual') }}</p>
                <p class="font-semibold mt-0.5">{{ $fmt($row['actual']) }}</p>
            </div>
            <div>
                <p class="text-white/70">{{ __('Projected') }}</p>
                <p class="font-semibold mt-0.5">{{ $fmt($row['projected']) }}</p>
            </div>
            <div>
                <p class="text-white/70">{{ __('Expenses') }}</p>
                <p class="font-semibold mt-0.5">{{ $fmt($row['actual_expenses']) }}</p>
            </div>
        </div>
    </div>

    @if($children->isNotEmpty())
        <!-- Children -->
        <div class="{{ $card }} mb-6">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Codes Under This One') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('The figures above are this code\'s own. Its children each carry their own budget.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Cost Code') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Revised') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actual') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Remaining') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($children as $child)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-3 text-sm">
                                    <a href="{{ route('budgets.cost-code', [$budget->id, $child['item']->id]) }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                        <span class="font-mono text-xs mr-2">{{ $child['item']->code }}</span>{{ $child['item']->name }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ $fmt($child['row']['revised'] ?? 0) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($child['row']['actual'] ?? 0) }}</td>
                                <td class="px-6 py-3 text-sm text-right {{ ($child['row']['remaining'] ?? 0) < 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-slate-700 dark:text-slate-300' }}">
                                    {{ $fmt($child['row']['remaining'] ?? 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="space-y-6">
        <!-- Change orders -->
        <div class="{{ $card }}">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Change Orders') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('What approved changes did to this code\'s budget.') }}</p>
                </div>
                <span class="text-lg font-bold {{ (float) $row['changes'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                    {{ $signed($row['changes']) }}
                </span>
            </div>

            @if($transactions['change_orders']->isNotEmpty() || $transactions['pending_change_orders']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Change Order') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($transactions['change_orders'] as $line)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-6 py-3">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">
                                            @if($line->changeOrder->co_number)
                                                <span class="text-xs text-slate-500 dark:text-slate-400 mr-2">{{ $line->changeOrder->co_number }}</span>
                                            @endif
                                            {{ $line->changeOrder->title }}
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $line->changeOrder->requested_date->translatedFormat('d M Y') }}
                                            @if($line->description) &bull; {{ $line->description }} @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusStyles[$line->changeOrder->status] }}">
                                            {{ $statusLabels[$line->changeOrder->status] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right font-medium {{ $line->amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}">
                                        {{ $signed($line->amount) }}
                                    </td>
                                </tr>
                            @endforeach

                            @foreach($transactions['pending_change_orders'] as $line)
                                <tr class="bg-amber-50/50 dark:bg-amber-900/10 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                                    <td class="px-6 py-3">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">
                                            @if($line->changeOrder->co_number)
                                                <span class="text-xs text-slate-500 dark:text-slate-400 mr-2">{{ $line->changeOrder->co_number }}</span>
                                            @endif
                                            {{ $line->changeOrder->title }}
                                        </div>
                                        <div class="text-xs text-amber-700 dark:text-amber-300">
                                            {{ __('Not approved — not in the budget above') }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusStyles[$line->changeOrder->status] }}">
                                            {{ $statusLabels[$line->changeOrder->status] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-400 dark:text-slate-500 line-through">
                                        {{ $signed($line->amount) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-8 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No change order has touched this cost code.') }}</p>
                </div>
            @endif
        </div>

        <!-- Contracts -->
        <div class="{{ $card }}">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contracts') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Subcontract value allocated to this code, and what has been paid on it.') }}</p>
                </div>
                <span class="text-lg font-bold text-slate-900 dark:text-white">{{ $fmt($row['committed_contracts']) }}</span>
            </div>

            @if($transactions['contracts']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contract') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Scheduled') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Paid') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($transactions['contracts'] as $line)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-6 py-3">
                                        <a href="{{ route('contracts.show', $line['contract']->id) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            {{ $line['contract']->contract_number ?? __('Contract #:id', ['id' => $line['contract']->id]) }}
                                        </a>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $line['contract']->subcontractor?->name ?? __('No subcontractor') }}
                                            @if($line['percent_complete'] !== null)
                                                &bull; {{ number_format($line['percent_complete'], 0) }}% {{ __('complete') }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ $fmt($line['scheduled']) }}</td>
                                    <td class="px-6 py-3 text-sm text-right {{ $line['paid'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $fmt($line['paid']) }}</td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-700 dark:text-slate-300">{{ $fmt($line['balance']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-8 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No contract carries money on this cost code.') }}</p>
                </div>
            @endif
        </div>

        <!-- Purchase orders awaiting approval -->
        @if($transactions['purchase_orders']->isNotEmpty())
            <div class="{{ $card }}">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Purchase Orders Awaiting Approval') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Lined up but not yet spent. Approving one turns it into an expense.') }}</p>
                    </div>
                    <span class="text-lg font-bold text-slate-900 dark:text-white">{{ $fmt($row['committed_pos']) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Purchase Order') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('On This Code') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($transactions['purchase_orders'] as $line)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-6 py-3">
                                        <a href="{{ route('purchase-orders.show', $line['order']->id) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            {{ $line['order']->po_number ?? __('Purchase Order #:id', ['id' => $line['order']->id]) }}
                                        </a>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $line['order']->supplier?->name ?? __('No supplier') }}
                                            @unless($line['is_whole_order'])
                                                &bull; {{ __('part of a :total order', ['total' => $fmt($line['order']->total_amount)]) }}
                                            @endunless
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ $fmt($line['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Expenses -->
        <div class="{{ $card }}">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Expenses') }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every expense line charged to this cost code, paid or not.') }}</p>
                </div>
                <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $fmt($row['actual_expenses']) }}</span>
            </div>

            @if($transactions['expenses']->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Item') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Supplier') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($transactions['expenses'] as $line)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                        {{ $line->expense->expense_date->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-sm text-slate-900 dark:text-white">{{ $line->item_name }}</div>
                                        @if($line->quantity && $line->unit)
                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }} {{ $line->unit }} &times; {{ $fmt($line->unit_price) }}
                                            </div>
                                        @endif
                                        @if(is_null($line->budget_item_id))
                                            <div class="text-xs text-amber-700 dark:text-amber-300">{{ __('No cost code of its own') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300">{{ $line->expense->supplier?->name ?? '—' }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $line->expense->status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                            {{ $line->expense->status === 'paid' ? __('Paid') : __('Unpaid') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right font-medium text-slate-900 dark:text-white">{{ $fmt($line->total_amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-6 py-8 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing has been spent against this cost code yet.') }}</p>
                </div>
            @endif
        </div>

        <!-- Contract payments -->
        @if($transactions['payments']->isNotEmpty())
            <div class="{{ $card }}">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contract Payments') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Already inside the contract figures above — shown here so the money can be traced.') }}</p>
                    </div>
                    <span class="text-lg font-bold text-green-600 dark:text-green-400">{{ $fmt($row['actual_payments']) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contract') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($transactions['payments'] as $line)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                    <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                        {{ $line->payment->payment_date->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-6 py-3">
                                        <a href="{{ route('contracts.show', $line->payment->contract_id) }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            {{ $line->payment->contract->contract_number ?? __('Contract #:id', ['id' => $line->payment->contract_id]) }}
                                        </a>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $line->payment->contract->subcontractor?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-right text-slate-900 dark:text-white">{{ $fmt($line->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($item)
            <!-- Record -->
            <div class="{{ $card }} px-6 py-4">
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Sort Order') }}</dt>
                        <dd class="text-slate-900 dark:text-white">{{ $item->sort_order }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Created') }}</dt>
                        <dd class="text-slate-900 dark:text-white">{{ $item->created_at->translatedFormat('d M Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Last Updated') }}</dt>
                        <dd class="text-slate-900 dark:text-white">{{ $item->updated_at->translatedFormat('d M Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>
</div>
