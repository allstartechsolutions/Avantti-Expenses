{{--
    Quotation detail — every field the round holds, on a full page.
    Expects: $viewingQuotation, $canReview, $canAward, $canConvert, $awardCeiling
--}}
<x-ui.modal name="quotation-view-modal" maxWidth="full">
    @if($viewingQuotation)
        @php
            $badge = [
                'gray' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300',
                'green' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                'red' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
            ];
            $factLabel = 'text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400';
            $factValue = 'mt-1 text-sm text-slate-900 dark:text-white';
            $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
            $responded = $viewingQuotation->respondedCount();
            $invited = $viewingQuotation->invitedCount();
        @endphp

        <div class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-slate-900 dark:text-white truncate">{{ $viewingQuotation->title }}</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[$viewingQuotation->getStatusColor()] }}">
                                {{ $viewingQuotation->getStatusLabel() }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ $viewingQuotation->quotation_number ?? '#'.$viewingQuotation->id }}
                            &middot; {{ $viewingQuotation->getTypeLabel() }}
                            &middot; {{ $viewingQuotation->getLocationDisplay() }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeViewModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        title="{{ __('Close') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
                @if($viewingQuotation->isAwarded())
                    @php
                        $winners = $viewingQuotation->is_split_award
                            ? $viewingQuotation->splitWinners()
                            : $viewingQuotation->quotationVendors->where('vendor_id', $viewingQuotation->awarded_vendor_id);
                    @endphp
                    <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-5 mb-6">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-green-800 dark:text-green-300">
                                    {{ $viewingQuotation->is_split_award ? __('Awarded, split across vendors') : __('Awarded') }}
                                </h3>
                                <p class="mt-1 text-lg font-semibold text-green-900 dark:text-green-200">
                                    {{ $winners->map(fn ($row) => $row->vendor?->name)->filter()->implode(', ') ?: __('Unknown') }}
                                </p>
                                <p class="mt-1 text-xs text-green-800 dark:text-green-300">
                                    {{ $viewingQuotation->awardedBy?->name ?? __('Unknown') }}
                                    &middot; {{ $viewingQuotation->awarded_at?->format('M d, Y H:i') }}
                                </p>
                                @if($viewingQuotation->award_reason)
                                    <p class="mt-3 text-sm text-green-900 dark:text-green-200 whitespace-pre-line">{{ $viewingQuotation->award_reason }}</p>
                                @endif
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs font-medium uppercase tracking-wider text-green-800 dark:text-green-300">{{ __('Committed') }}</p>
                                <p class="text-2xl font-bold text-green-900 dark:text-green-200">
                                    {{ Number::currency($viewingQuotation->awardedTotal(), config('app.currency'), config('app.locale')) }}
                                </p>
                            </div>
                        </div>

                        @error('convert') <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                        @if($viewingQuotation->isConverted())
                            @php $records = $viewingQuotation->convertedRecords(); @endphp
                            <div class="mt-4 rounded-lg bg-white dark:bg-slate-800 border border-green-200 dark:border-green-800 p-4">
                                <h4 class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                                    {{ $viewingQuotation->type === 'service' ? __('Contracts raised') : __('Purchase orders raised') }}
                                </h4>
                                @if($records->count() > 0)
                                    <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                                        @foreach($records as $record)
                                            <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                                                <span class="text-sm text-slate-900 dark:text-white">
                                                    @if($viewingQuotation->type === 'service')
                                                        {{ $record->contract_number }} &middot; {{ $record->subcontractor?->name }}
                                                    @else
                                                        {{ $record->po_number ?? '#'.$record->id }} &middot; {{ $record->supplier?->name }}
                                                        <span class="text-xs text-slate-500 dark:text-slate-400">({{ $record->getStatusLabel() }})</span>
                                                    @endif
                                                </span>
                                                <span class="flex items-center gap-3">
                                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                                        {{ Number::currency($viewingQuotation->type === 'service' ? $record->amount : $record->total_amount, config('app.currency'), config('app.locale')) }}
                                                    </span>
                                                    <a href="{{ $viewingQuotation->type === 'service' ? route('contracts.show', $record->id) : route('purchase-orders.show', $record->id) }}"
                                                       class="text-xs font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                                        {{ __('Open') }}
                                                    </a>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $viewingQuotation->type === 'service'
                                            ? __('The contract is a draft: set its dates, retention and payment schedule, then activate it. Nothing counts as committed money until you do.')
                                            : __('The order is a draft: approving it is what creates the expense, as it always has.') }}
                                    </p>
                                @else
                                    <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('The records raised from this round are no longer there.') }}</p>
                                @endif
                            </div>
                        @endif

                        @if($viewingQuotation->is_split_award)
                            <ul class="mt-4 divide-y divide-green-200 dark:divide-green-800">
                                @foreach($viewingQuotation->items as $item)
                                    <li class="py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span class="text-green-900 dark:text-green-200">{{ $item->item_name }}</span>
                                        <span class="text-green-800 dark:text-green-300">
                                            {{ $item->awardedVendorRow?->vendor?->name ?? __('Not awarded') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <!-- Where the round stands -->
                <div class="{{ $card }} mb-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Progress') }}</h3>
                            @php
                                $chain = ['draft' => __('Draft'), 'sent' => __('Sent to Vendors'), 'comparing' => __('Comparing'), 'negotiating' => __('Negotiating'), 'awarded' => __('Awarded'), 'converted' => __('Converted')];
                                $order = array_keys($chain);
                                $currentIndex = array_search($viewingQuotation->status, $order, true);
                            @endphp
                            @if($viewingQuotation->status === 'cancelled')
                                <p class="text-sm text-slate-900 dark:text-white">{{ __('This round was cancelled and goes no further.') }}</p>
                            @else
                                <ol class="flex flex-wrap items-center gap-x-2 gap-y-2">
                                    @foreach($chain as $key => $stepLabel)
                                        @php $reached = $currentIndex !== false && $loop->index <= $currentIndex; @endphp
                                        <li class="flex items-center gap-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $reached ? 'bg-[#3F5189] text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }}">
                                                {{ $stepLabel }}
                                            </span>
                                            @unless($loop->last)
                                                <span class="text-slate-300 dark:text-slate-600">&rarr;</span>
                                            @endunless
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>

                        <!-- The 2/3 rule, visible from the start -->
                        <div class="shrink-0 rounded-lg bg-slate-50 dark:bg-slate-900/40 px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Proposals') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                                {{ $responded }}<span class="text-base font-medium text-slate-500 dark:text-slate-400">/{{ $invited }}</span>
                            </p>
                            @if($viewingQuotation->isAwarded())
                                <p class="mt-1 text-xs text-green-600 dark:text-green-400">{{ __('Awarded') }}</p>
                            @else
                            <p class="mt-1 text-xs {{ $viewingQuotation->meetsProposalNorm() ? 'text-green-600 dark:text-green-400' : ($viewingQuotation->meetsProposalMinimum() ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $viewingQuotation->meetsProposalNorm()
                                    ? __('Meets the 3-proposal norm')
                                    : ($viewingQuotation->meetsProposalMinimum()
                                        ? __('Two proposals — below the norm of three')
                                        : __('Fewer than two — an award will be blocked')) }}
                            </p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: the facts -->
                    <div class="space-y-6">
                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">{{ __('Details') }}</h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Number') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingQuotation->quotation_number ?? '#'.$viewingQuotation->id }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Type') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingQuotation->getTypeLabel() }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Location') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingQuotation->getLocationDisplay() }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Needed On Site') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingQuotation->needed_by?->format('M d, Y') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Responses Due') }}</dt>
                                    <dd class="{{ $factValue }} {{ $viewingQuotation->responsesOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                        {{ $viewingQuotation->responses_due_at?->format('M d, Y') ?? '—' }}
                                        @if($viewingQuotation->responsesOverdue())
                                            <span class="block text-xs">{{ __('Overdue') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Requisition') }}</dt>
                                    <dd class="{{ $factValue }}">
                                        @if($viewingQuotation->requisition)
                                            {{ $viewingQuotation->requisition->requisition_number }}
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">{{ __('Standalone') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="{{ $factLabel }}">{{ __('Budget Item') }}</dt>
                                    <dd class="{{ $factValue }}">
                                        @if($viewingQuotation->budgetItem)
                                            {{ $viewingQuotation->budgetItem->code }} — {{ $viewingQuotation->budgetItem->name }}
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">{{ __('Not linked to the budget') }}</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">{{ __('Record') }}</h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Created By') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingQuotation->createdBy?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Created') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingQuotation->created_at?->format('M d, Y H:i') ?? '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="{{ $factLabel }}">{{ __('Last Updated') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingQuotation->updated_at?->format('M d, Y H:i') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('History') }}</h3>
                            @if($viewingQuotation->statusHistories->count() > 0)
                                <ul class="space-y-3">
                                    @foreach($viewingQuotation->statusHistories as $history)
                                        <li class="flex items-start gap-3">
                                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#3F5189] dark:bg-[#4A5A96]"></span>
                                            <div>
                                                <p class="text-sm text-slate-900 dark:text-white">
                                                    {{ $history->old_status
                                                        ? __(':old to :new', ['old' => ucfirst($history->old_status), 'new' => ucfirst($history->new_status)])
                                                        : __('Created as :status', ['status' => ucfirst($history->new_status)]) }}
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $history->changedBy?->name ?? __('Unknown') }} &middot; {{ $history->created_at?->format('M d, Y H:i') }}
                                                </p>
                                                @if($history->reason)
                                                    <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $history->reason }}</p>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('Nothing has happened to this round yet.') }}</p>
                            @endif
                        </div>
                    </div>

                    <!-- Right: scope, vendors, attachments -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Scope Notes') }}</h3>
                            @if($viewingQuotation->description)
                                <p class="text-sm text-slate-900 dark:text-white whitespace-pre-line">{{ $viewingQuotation->description }}</p>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('No scope notes were written for this round.') }}</p>
                            @endif
                        </div>

                        <div class="{{ $card }}">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Scope') }}</h3>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $viewingQuotation->items->count() }} {{ trans_choice('line|lines', $viewingQuotation->items->count()) }}
                                </span>
                            </div>
                            @if($viewingQuotation->items->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                        <thead>
                                            <tr>
                                                <th class="py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Item') }}</th>
                                                <th class="py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Qty') }}</th>
                                                <th class="py-2 pl-4 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Unit') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                            @foreach($viewingQuotation->items as $item)
                                                <tr>
                                                    <td class="py-3 pr-4">
                                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->item_name }}</div>
                                                        @if($item->description)
                                                            <div class="text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line">{{ $item->description }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="py-3 text-right text-sm text-slate-900 dark:text-white whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                                                    <td class="py-3 pl-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $item->unit ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('This round has no items yet.') }}</p>
                            @endif
                        </div>

                        <!-- The vendors asked -->
                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Invited Vendors') }}</h3>
                            @if($viewingQuotation->quotationVendors->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                        <thead>
                                            <tr>
                                                <th class="py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Vendor') }}</th>
                                                <th class="py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Asked') }}</th>
                                                <th class="py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Proposal') }}</th>
                                                <th class="py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Terms') }}</th>
                                                <th class="py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                                <th class="py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                            @foreach($viewingQuotation->quotationVendors as $row)
                                                <tr>
                                                    <td class="py-3 pr-4">
                                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $row->vendor?->name ?? __('Unknown') }}</div>
                                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                                            {{ $row->bestEmail() ?: __('no e-mail on file') }}
                                                        </div>
                                                    </td>
                                                    <td class="py-3 pr-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                        @if($row->invited_at)
                                                            {{ $row->invited_at->format('M d, Y') }}
                                                            <div class="text-xs">{{ $row->getInviteMethodLabel() }}</div>
                                                        @else
                                                            {{ __('Not yet') }}
                                                        @endif
                                                    </td>
                                                    <td class="py-3 pr-4 text-right whitespace-nowrap">
                                                        @if($row->hasPrices())
                                                            <div class="text-sm font-semibold text-slate-900 dark:text-white">
                                                                {{ Number::currency($row->equalizedTotal(), config('app.currency'), config('app.locale')) }}
                                                            </div>
                                                            <div class="text-xs text-slate-500 dark:text-slate-400">
                                                                {{ __('lines :sub', ['sub' => Number::currency($row->itemsSubtotal(), config('app.currency'), config('app.locale'))]) }}
                                                            </div>
                                                            @if($row->unavailableCount() > 0)
                                                                <div class="text-xs text-amber-600 dark:text-amber-400">
                                                                    {{ __(':count not supplied', ['count' => $row->unavailableCount()]) }}
                                                                </div>
                                                            @endif
                                                            @if($row->substituteCount() > 0)
                                                                <div class="text-xs text-amber-600 dark:text-amber-400">
                                                                    {{ __(':count substituted', ['count' => $row->substituteCount()]) }}
                                                                </div>
                                                            @endif
                                                            @if($row->hasBeenNegotiated())
                                                                <div class="text-xs text-green-600 dark:text-green-400">
                                                                    {{ __('was :amount', ['amount' => Number::currency($row->openingTotal(), config('app.currency'), config('app.locale'))]) }}
                                                                    &middot; {{ trans_choice(':count round|:count rounds', $row->negotiationRounds(), ['count' => $row->negotiationRounds()]) }}
                                                                </div>
                                                            @endif
                                                            @if($row->unquotedCount($viewingQuotation->items->count()) > 0)
                                                                <div class="text-xs text-red-600 dark:text-red-400">
                                                                    {{ __(':count not quoted', ['count' => $row->unquotedCount($viewingQuotation->items->count())]) }}
                                                                </div>
                                                            @endif
                                                        @else
                                                            <span class="text-xs text-slate-400 dark:text-slate-500">{{ __('No prices yet') }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-3 pr-4 text-xs text-slate-500 dark:text-slate-400">
                                                        @if($row->hasResponded())
                                                            @if($row->lead_time_days !== null)
                                                                <div>{{ trans_choice(':count day|:count days', $row->lead_time_days, ['count' => $row->lead_time_days]) }}</div>
                                                            @endif
                                                            @if($row->freight_type)
                                                                <div>{{ strtoupper($row->freight_type) }}</div>
                                                            @endif
                                                            @if($row->payment_terms)
                                                                <div>{{ $row->payment_terms }}</div>
                                                            @endif
                                                            @if($row->proposal_valid_until)
                                                                <div class="{{ $row->proposalExpired() ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                                                    {{ $row->proposalExpired() ? __('Expired :date', ['date' => $row->proposal_valid_until->format('M d, Y')]) : __('Valid to :date', ['date' => $row->proposal_valid_until->format('M d, Y')]) }}
                                                                </div>
                                                            @endif
                                                        @else
                                                            &mdash;
                                                        @endif
                                                    </td>
                                                    <td class="py-3 pr-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[$row->getStatusColor()] }}">
                                                            {{ $row->getStatusLabel() }}
                                                        </span>
                                                        @if($row->hasResponded() && $row->getSourceLabel())
                                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                                {{ __('by :channel', ['channel' => $row->getSourceLabel()]) }}
                                                                @if($row->received_at)
                                                                    &middot; {{ $row->received_at->format('M d') }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="py-3 text-right whitespace-nowrap">
                                                        @if(in_array($row->status, ['invited', 'responded'], true) && $viewingQuotation->isOpen() && $viewingQuotation->status !== 'awarded')
                                                            <button type="button" wire:click="openProposalModal({{ $row->id }})" class="mr-3 text-xs font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                                                {{ $row->hasPrices() ? __('Edit proposal') : __('Enter proposal') }}
                                                            </button>
                                                            @if($row->hasResponded())
                                                                <button type="button" wire:click="openNegotiationModal({{ $row->id }})" class="mr-3 text-xs font-medium text-amber-700 dark:text-amber-400 hover:underline">
                                                                    {{ __('Negotiate') }}
                                                                </button>
                                                            @endif
                                                        @endif
                                                        @if($row->status === 'responded' && auth()->user()->can('quotations.edit', $viewingQuotation))
                                                            <button
                                                                type="button"
                                                                wire:click="clearProposal({{ $row->id }})"
                                                                wire:confirm="{{ __('Remove this proposal and put the vendor back to invited?') }}"
                                                                class="mr-3 text-xs text-slate-500 dark:text-slate-400 hover:underline">
                                                                {{ __('Remove') }}
                                                            </button>
                                                        @endif
                                                        @if($row->status === 'invited')
                                                            @if($row->bestEmail())
                                                                <button type="button" wire:click="resendRfq({{ $row->id }})" class="mr-3 text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                                                    {{ $row->invited_at ? __('Send again') : __('Send request') }}
                                                                </button>
                                                            @endif
                                                            <button type="button" wire:click="declineVendor({{ $row->id }})" class="text-xs text-slate-500 dark:text-slate-400 hover:underline">
                                                                {{ __('Mark declined') }}
                                                            </button>
                                                        @elseif($row->status === 'declined')
                                                            <button type="button" wire:click="reinviteVendor({{ $row->id }})" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                                                {{ __('Re-invite') }}
                                                            </button>
                                                        @else
                                                            <span class="text-xs text-slate-400 dark:text-slate-500">&mdash;</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Totals are equalized: lines plus freight and taxes, less discount. Lines a vendor cannot supply are excluded and flagged.') }}
                                </p>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">
                                    {{ __('No vendor has been invited yet. Edit the round to add them.') }}
                                </p>
                            @endif
                        </div>

                        <!-- What the haggling won -->
                        @php $negotiated = $viewingQuotation->quotationVendors->filter(fn ($row) => $row->hasBeenNegotiated()); @endphp
                        @if($negotiated->count() > 0)
                            <div class="{{ $card }}">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Negotiation Rounds') }}</h3>
                                    <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                                        {{ __('− :amount so far', ['amount' => Number::currency($negotiated->sum(fn ($row) => $row->negotiatedSaving()), config('app.currency'), config('app.locale'))]) }}
                                    </span>
                                </div>
                                <div class="space-y-4">
                                    @foreach($negotiated as $row)
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $row->vendor?->name ?? __('Unknown') }}</p>
                                            <ul class="mt-2 space-y-2">
                                                @foreach($row->negotiations as $negotiation)
                                                    <li class="flex flex-wrap items-start justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <p class="text-sm text-slate-900 dark:text-white">
                                                                {{ __('Round :number', ['number' => $negotiation->round]) }}:
                                                                {{ Number::currency($negotiation->previous_total, config('app.currency'), config('app.locale')) }}
                                                                &rarr;
                                                                {{ Number::currency($negotiation->new_total, config('app.currency'), config('app.locale')) }}
                                                            </p>
                                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                                {{ $negotiation->negotiatedBy?->name ?? __('Unknown') }}
                                                                &middot; {{ $negotiation->negotiated_at?->format('M d, Y H:i') }}
                                                            </p>
                                                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $negotiation->note }}</p>
                                                        </div>
                                                        <span class="text-sm font-semibold whitespace-nowrap {{ $negotiation->savingAmount() > 0 ? 'text-green-600 dark:text-green-400' : ($negotiation->savingAmount() < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400') }}">
                                                            {{ $negotiation->savingAmount() > 0 ? '− ' : ($negotiation->savingAmount() < 0 ? '+ ' : '') }}{{ Number::currency(abs($negotiation->savingAmount()), config('app.currency'), config('app.locale')) }}
                                                        </span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Sending it out -->
                        @if($viewingQuotation->status === 'draft')
                            <div class="{{ $card }}">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Send the Round') }}</h3>

                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-4 mb-4">
                                    <p class="text-sm text-slate-900 dark:text-white font-medium">{{ __('By e-mail, from here') }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {{ __('Each vendor gets their own message with a PDF of the scope to price and send back.') }}
                                    </p>
                                    <div class="mt-3">
                                        <x-ui.button variant="primary" icon="send" wire:click="openSendModal({{ $viewingQuotation->id }})">
                                            {{ __('Compose the E-mail') }}
                                        </x-ui.button>
                                    </div>
                                </div>

                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('Or record a round you sent yourself') }}</p>
                                <label class="mt-2 block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('How the vendors were asked') }}</label>
                                <select wire:model="sendMethod" class="w-full sm:max-w-xs px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="email">{{ __('E-mail') }}</option>
                                    <option value="whatsapp">{{ __('WhatsApp') }}</option>
                                    <option value="phone">{{ __('Phone') }}</option>
                                    <option value="in_person">{{ __('In person') }}</option>
                                </select>
                                @error('sendMethod') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('This stamps the date and the method on every vendor still waiting, so the map later shows who was asked and when.') }}
                                </p>
                                <div class="mt-3">
                                    <x-ui.button variant="primary" icon="check" wire:click="markAsSent({{ $viewingQuotation->id }})">
                                        {{ __('Mark as Sent') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        @endif

                        @if($rfqEmails->count() > 0)
                            <div class="{{ $card }}">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Requests Sent') }}</h3>
                                <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($rfqEmails as $email)
                                        <li class="py-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <span class="text-sm text-slate-900 dark:text-white">
                                                    {{ $email->quotationVendor?->vendor?->name ?? $email->sent_to }}
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">&middot; {{ $email->sent_to }}</span>
                                                </span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $email->failed() ? $badge['red'] : $badge['green'] }}">
                                                    {{ $email->failed() ? __('Failed') : __('Sent') }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {{ $email->sent_at?->format('M d, Y H:i') }} &middot; {{ $email->sentBy?->name ?? __('Unknown') }}
                                            </p>
                                            @if($email->failed() && $email->error)
                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $email->error }}</p>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Attachments') }}</h3>
                            <livewire:shared.attachments
                                modelType="quotation"
                                :modelId="$viewingQuotation->id"
                                :key="'quotation-attachments-'.$viewingQuotation->id" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        {{-- The ceiling is a fact about this round's money, so it is
                             stated with the figure rather than hidden. --}}
                        @if($awardCeiling !== null && $viewingQuotation->canBeConverted())
                            <p class="w-full text-sm text-amber-700 dark:text-amber-400">
                                {{ __('This round commits :amount, which is above the :ceiling you may approve. Somebody with a higher ceiling has to convert it.', [
                                    'amount' => Number::currency($viewingQuotation->awardedTotal(), config('app.currency'), config('app.locale')),
                                    'ceiling' => Number::currency($awardCeiling / 100, config('app.currency'), config('app.locale')),
                                ]) }}
                            </p>
                        @endif
                        @if($canAward && $viewingQuotation->canBeAwarded())
                            <x-ui.button variant="success" icon="check" wire:click="openAwardModal({{ $viewingQuotation->id }})">
                                {{ __('Award the Round') }}
                            </x-ui.button>
                        @endif
                        @if($canConvert && $viewingQuotation->canBeConverted())
                            <x-ui.button
                                variant="primary"
                                icon="arrow-right"
                                wire:click="convertAward({{ $viewingQuotation->id }})"
                                wire:confirm="{{ $viewingQuotation->type === 'service'
                                    ? __('Raise the contract(s) for this award?')
                                    : __('Raise the purchase order(s) for this award?') }}">
                                {{ $viewingQuotation->type === 'service' ? __('Raise the Contract') : __('Raise the Purchase Order') }}
                            </x-ui.button>
                        @endif
                        @if($canAward && $viewingQuotation->canRevokeAward())
                            <x-ui.button
                                variant="warning"
                                wire:click="revokeAward({{ $viewingQuotation->id }})"
                                wire:confirm="{{ __('Revoke this award and go back to comparing?') }}">
                                {{ __('Revoke the Award') }}
                            </x-ui.button>
                        @endif
                        @if($viewingQuotation->quotationVendors->count() > 0)
                            <x-ui.button variant="primary" icon="chart" wire:click="openComparisonModal({{ $viewingQuotation->id }})">
                                {{ __('Comparison Map') }}
                            </x-ui.button>
                        @endif
                        @if($viewingQuotation->status !== 'draft' && $viewingQuotation->isOpen() && $viewingQuotation->quotationVendors->count() > 0)
                            <x-ui.button variant="secondary" icon="send" wire:click="openSendModal({{ $viewingQuotation->id }})">
                                {{ __('E-mail the Request') }}
                            </x-ui.button>
                        @endif
                        @if($viewingQuotation->canBeEdited())
                            @can('quotations.edit', $viewingQuotation)
                                <x-ui.button variant="secondary" icon="edit" wire:click="openEditModal({{ $viewingQuotation->id }})">
                                    {{ __('Edit') }}
                                </x-ui.button>
                            @endcan
                        @endif
                        @if($viewingQuotation->canBeCancelled())
                            @can('quotations.edit', $viewingQuotation)
                            <x-ui.button
                                variant="warning"
                                wire:click="cancelQuotation({{ $viewingQuotation->id }})"
                                wire:confirm="{{ __('Cancel this quotation round?') }}">
                                {{ __('Cancel Round') }}
                            </x-ui.button>
                            @endcan
                        @endif
                        @if($viewingQuotation->canBeDeleted() && auth()->user()->can('quotations.delete', $viewingQuotation))
                            <x-ui.button
                                variant="danger"
                                icon="trash"
                                wire:click="deleteQuotation({{ $viewingQuotation->id }})"
                                wire:confirm="{{ __('Delete this quotation permanently?') }}">
                                {{ __('Delete') }}
                            </x-ui.button>
                        @endif
                    </div>
                    <x-ui.button variant="secondary" wire:click="closeViewModal">{{ __('Close') }}</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</x-ui.modal>
