{{--
    The award (adjudicação) — pick the winner, write why, confirm.
    Expects: $awardingQuotation
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $money = fn ($value) => Number::currency((float) $value, config('app.currency'), config('app.locale'));
@endphp

<x-ui.modal name="quotation-award-modal" maxWidth="full" layer="top">
    @if($awardingQuotation)
        @php
            $proposals = $awardingQuotation->awardableProposals();
            $lowestTotal = $proposals->min(fn ($row) => $row->equalizedTotal());
            $chosen = $proposals->firstWhere('id', (int) $awardVendorRowId);
            $expiredChosen = $awardMode === 'whole'
                ? ($chosen?->proposalExpired() ?? false)
                : $proposals->whereIn('id', collect($awardLines)->pluck('vendor_row_id')->filter()->all())
                    ->contains(fn ($row) => $row->proposalExpired());
        @endphp

        <form wire:submit="awardQuotation" class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white truncate">{{ __('Award the Round') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ $awardingQuotation->quotation_number }} &middot; {{ $awardingQuotation->title }}
                            &middot; {{ trans_choice(':count proposal|:count proposals', $proposals->count(), ['count' => $proposals->count()]) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeAwardModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        title="{{ __('Close') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
                <!-- The rule, stated before the choice -->
                @unless($awardingQuotation->meetsProposalNorm())
                    <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            {{ trans_choice(
                                'Only two proposals are on the table. Three is the Brazilian norm — awarding with fewer has to be a deliberate choice.|Only :count proposals are on the table. Three is the Brazilian norm — awarding with fewer has to be a deliberate choice.',
                                $proposals->count(),
                                ['count' => $proposals->count()]
                            ) }}
                        </p>
                        <label class="mt-3 flex items-start gap-2 text-sm text-amber-900 dark:text-amber-200">
                            <input type="checkbox" wire:model.live="awardAcknowledgedNorm" class="mt-0.5 rounded border-amber-300 dark:border-amber-700 text-amber-600 focus:ring-amber-500">
                            {{ __('I am awarding with fewer than three proposals.') }}
                        </label>
                        @error('awardAcknowledgedNorm') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                @endunless

                <!-- Whole or split -->
                <div class="{{ $card }}">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('How to Award') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 rounded-lg border p-4 cursor-pointer {{ $awardMode === 'whole' ? 'border-[#3F5189] bg-[#3F5189]/5 dark:bg-[#4A5A96]/10' : 'border-slate-200 dark:border-slate-700' }}">
                            <input type="radio" value="whole" wire:model.live="awardMode" class="mt-1 text-[#3F5189] focus:ring-[#3F5189]">
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('The whole round to one vendor') }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('One order, one delivery, one relationship. This is the usual choice.') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border p-4 cursor-pointer {{ $awardMode === 'split' ? 'border-[#3F5189] bg-[#3F5189]/5 dark:bg-[#4A5A96]/10' : 'border-slate-200 dark:border-slate-700' }}">
                            <input type="radio" value="split" wire:model.live="awardMode" class="mt-1 text-[#3F5189] focus:ring-[#3F5189]">
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('Split it line by line') }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Each line to whoever priced it best. Every winning vendor gets their own order.') }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                @if($awardMode === 'whole')
                    <!-- Pick the proposal -->
                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('The Winner') }}</h3>
                        @error('awardVendorRowId') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        <div class="space-y-3">
                            @foreach($proposals as $row)
                                @php
                                    $total = $row->equalizedTotal();
                                    $isLowest = $lowestTotal !== null && (int) round($total * 100) === (int) round($lowestTotal * 100);
                                    $selected = (int) $awardVendorRowId === $row->id;
                                @endphp
                                <label class="flex items-start gap-3 rounded-lg border p-4 cursor-pointer {{ $selected ? 'border-[#3F5189] bg-[#3F5189]/5 dark:bg-[#4A5A96]/10' : 'border-slate-200 dark:border-slate-700' }}">
                                    <input type="radio" value="{{ $row->id }}" wire:model.live="awardVendorRowId" class="mt-1 text-[#3F5189] focus:ring-[#3F5189]">
                                    <span class="flex-1 min-w-0">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $row->vendor?->name ?? __('Unknown') }}</span>
                                            @if($isLowest)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300">{{ __('Lowest') }}</span>
                                            @endif
                                            @if($row->proposalExpired())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300">{{ __('Expired') }}</span>
                                            @endif
                                            @if($row->unquotedCount($awardingQuotation->items->count()) > 0)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                                    {{ __(':count not quoted', ['count' => $row->unquotedCount($awardingQuotation->items->count())]) }}
                                                </span>
                                            @endif
                                        </span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            @if($row->lead_time_days !== null){{ trans_choice(':count day|:count days', $row->lead_time_days, ['count' => $row->lead_time_days]) }} &middot; @endif
                                            @if($row->payment_terms){{ $row->payment_terms }} &middot; @endif
                                            {{ $row->freight_type ? strtoupper($row->freight_type) : __('freight not stated') }}
                                            @if($row->hasBeenNegotiated())
                                                &middot; {{ __('was :amount', ['amount' => $money($row->openingTotal())]) }}
                                            @endif
                                        </span>
                                    </span>
                                    <span class="text-right shrink-0">
                                        <span class="block text-base font-bold text-slate-900 dark:text-white">{{ $money($total) }}</span>
                                        @if(! $isLowest && $lowestTotal !== null)
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">+ {{ $money($total - $lowestTotal) }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <!-- Pick a winner per line -->
                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">{{ __('Winner per Line') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">{{ __('Lines nobody priced can be left unawarded.') }}</p>
                        @error('awardLines') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead>
                                    <tr>
                                        <th class="py-2 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Item') }}</th>
                                        <th class="py-2 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Qty') }}</th>
                                        <th class="py-2 pl-4 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Awarded to') }}</th>
                                        <th class="py-2 pl-4 text-right text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Line Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($awardLines as $index => $line)
                                        @php
                                            $winner = $proposals->firstWhere('id', (int) ($line['vendor_row_id'] ?? 0));
                                            $priced = $winner?->items->firstWhere('quotation_item_id', $line['quotation_item_id']);
                                        @endphp
                                        <tr wire:key="award-line-{{ $index }}">
                                            <td class="py-3 pr-4 text-sm text-slate-900 dark:text-white">{{ $line['item_name'] }}</td>
                                            <td class="py-3 text-right text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                                {{ rtrim(rtrim(number_format((float) $line['quantity'], 2, '.', ''), '0'), '.') }} {{ $line['unit'] }}
                                            </td>
                                            <td class="py-3 pl-4">
                                                <select wire:model.live="awardLines.{{ $index }}.vendor_row_id" class="{{ $field }}">
                                                    <option value="">{{ __('Not awarded') }}</option>
                                                    @foreach($proposals as $row)
                                                        @php $rowPrice = $row->items->firstWhere('quotation_item_id', $line['quotation_item_id']); @endphp
                                                        @if($rowPrice && ! $rowPrice->is_unavailable)
                                                            <option value="{{ $row->id }}">
                                                                {{ $row->vendor?->name }} — {{ $money($rowPrice->total_amount) }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="py-3 pl-4 text-right text-sm font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                                {{ $priced ? $money($priced->total_amount) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Each winning vendor also charges their own freight, taxes and discount once, so the committed total is more than the sum of the lines.') }}
                        </p>
                    </div>
                @endif

                <!-- What this commits against the budget -->
                @if($awardingQuotation->budgetItem)
                    @php
                        $budgeted = (float) $awardingQuotation->budgetItem->budgeted_amount;
                        $committing = $awardMode === 'split'
                            ? collect($awardLines)->sum(function ($line) use ($proposals) {
                                $winner = $proposals->firstWhere('id', (int) ($line['vendor_row_id'] ?? 0));
                                $priced = $winner?->items->firstWhere('quotation_item_id', $line['quotation_item_id']);
                                return $priced && ! $priced->is_unavailable ? (float) $priced->total_amount : 0;
                            }) + $proposals->whereIn('id', collect($awardLines)->pluck('vendor_row_id')->filter()->unique()->all())
                                ->sum(fn ($row) => (float) $row->freight_amount + (float) $row->tax_amount - (float) $row->discount_amount)
                            : (float) ($chosen?->equalizedTotal() ?? 0);
                        $over = round($committing - $budgeted, 2);
                    @endphp
                    <div class="{{ $card }}">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Against the Budget') }}</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">{{ $awardingQuotation->budgetItem->code }} — {{ $awardingQuotation->budgetItem->name }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $money($budgeted) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('This award commits') }}</dt>
                                <dd class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $money($committing) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">{{ $over > 0 ? __('Over budget') : __('Left in the budget item') }}</dt>
                                <dd class="mt-1 text-lg font-bold {{ $over > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ $money(abs($over)) }}
                                </dd>
                            </div>
                        </dl>
                        @if($over > 0)
                            <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                                {{ __('This award goes over the budgeted figure. That does not block it — say why in the justification below.') }}
                            </p>
                        @endif
                    </div>
                @endif

                <!-- The reason, and the expiry acknowledgement -->
                <div class="{{ $card }}">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Why This Award') }}</h3>
                    <label class="{{ $label }}">{{ __('Justification') }} <span class="text-red-500">*</span></label>
                    <textarea
                        wire:model="awardReason"
                        rows="4"
                        placeholder="{{ __('e.g. Not the cheapest, but the only one that delivers inside the concreting window and carries the specified brand.') }}"
                        class="{{ $field }}"></textarea>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Required. This is what defends the choice when someone asks why the cheapest offer was not taken.') }}</p>
                    @error('awardReason') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    @if($expiredChosen)
                        <label class="mt-4 flex items-start gap-2 text-sm text-red-700 dark:text-red-300">
                            <input type="checkbox" wire:model.live="awardAcknowledgedExpiry" class="mt-0.5 rounded border-red-300 dark:border-red-700 text-red-600 focus:ring-red-500">
                            {{ __('The proposal being awarded has expired. I have confirmed the vendor still honours it.') }}
                        </label>
                        @error('awardAcknowledgedExpiry') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endif
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('The losing proposals are marked not selected and the prices freeze. The award can still be revoked until it becomes an order.') }}
                    </p>
                    <div class="flex items-center gap-3">
                        <x-ui.button type="button" variant="secondary" wire:click="closeAwardModal">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="success" icon="check">{{ __('Award It') }}</x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    @endif
</x-ui.modal>
