{{--
    Proposal entry — what the vendor e-mailed back, keyed in line by line.
    Expects: $pricingVendorRow
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $currency = fn ($value) => Number::currency((float) $value, config('app.currency'), config('app.locale'));
    $totals = $pricingVendorRow ? $this->proposalTotals() : null;
@endphp

<x-ui.modal name="quotation-proposal-modal" maxWidth="full" layer="top">
    @if($pricingVendorRow && $totals)
        <form wire:submit="saveProposal" class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white truncate">
                            {{ $proposalMode === 'negotiation'
                                ? __('Negotiation round with :vendor', ['vendor' => $pricingVendorRow->vendor?->name ?? __('Unknown')])
                                : __('Proposal from :vendor', ['vendor' => $pricingVendorRow->vendor?->name ?? __('Unknown')]) }}
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ $pricingVendorRow->quotation?->quotation_number }} &middot; {{ $pricingVendorRow->quotation?->title }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeProposalModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        title="{{ __('Close') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- The prices -->
                    <div class="lg:col-span-2 space-y-4">
                        @if($proposalMode === 'negotiation')
                            @php $opening = $pricingVendorRow->equalizedTotal(); @endphp
                            <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5">
                                <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('Recording a negotiation round') }}</h3>
                                <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                                    {{ __('Change the prices to what was agreed. The offer on the table now — :amount — is kept as the before figure, so the round survives the new numbers.', ['amount' => $currency($opening)]) }}
                                </p>
                                <div class="mt-3">
                                    <label class="{{ $label }}">{{ __('What was agreed') }} <span class="text-red-500">*</span></label>
                                    <textarea wire:model="negotiationNote" rows="3" placeholder="{{ __('e.g. 5% off for payment in 15 days, freight now included') }}" class="{{ $field }}"></textarea>
                                    @error('negotiationNote') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                @if($pricingVendorRow->negotiations->count() > 0)
                                    <p class="mt-3 text-xs text-amber-800 dark:text-amber-300">
                                        {{ trans_choice('This will be round two.|This will be round :count.', $pricingVendorRow->negotiations->count() + 1, ['count' => $pricingVendorRow->negotiations->count() + 1]) }}
                                    </p>
                                @endif
                            </div>
                        @endif

                        <div class="{{ $card }}">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Prices') }}</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {{ __('Key in what the vendor sent. A line they cannot supply is marked, not left blank.') }}
                                    </p>
                                </div>
                                <span class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ __(':priced of :lines priced', ['priced' => $totals['priced'], 'lines' => $totals['lines']]) }}
                                </span>
                            </div>

                            @error('priceRows') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            <div class="space-y-3">
                                @foreach($priceRows as $index => $row)
                                    <div class="rounded-lg border {{ !empty($row['is_unavailable']) ? 'border-red-200 dark:border-red-800 bg-red-50/50 dark:bg-red-900/10' : 'border-slate-200 dark:border-slate-700' }} p-4" wire:key="price-row-{{ $index }}">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $row['item_name'] }}</p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ rtrim(rtrim(number_format((float) $row['quantity'], 2, '.', ''), '0'), '.') }}
                                                    {{ $row['unit'] ?: __('un') }}
                                                    @if($row['description'])
                                                        &middot; {{ $row['description'] }}
                                                    @endif
                                                </p>
                                                @if(! empty($row['last_paid']))
                                                    <p class="text-xs text-green-600 dark:text-green-400">
                                                        {{ __('last paid :amount on :date', [
                                                            'amount' => $currency($row['last_paid']['amount']),
                                                            'date' => $row['last_paid']['date'],
                                                        ]) }}
                                                    </p>
                                                @endif
                                            </div>
                                            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                                <input type="checkbox" wire:model.live="priceRows.{{ $index }}.is_unavailable" class="rounded border-slate-300 dark:border-slate-600 text-red-600 focus:ring-red-500">
                                                {{ __('Cannot supply') }}
                                            </label>
                                        </div>

                                        @if(empty($row['is_unavailable']))
                                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="{{ $label }}">{{ __('Unit Price') }}</label>
                                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="priceRows.{{ $index }}.unit_price" placeholder="0.00" class="{{ $field }}">
                                                    @error('priceRows.'.$index.'.unit_price') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <label class="{{ $label }}">{{ __('Line Total') }}</label>
                                                    <div class="px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-900/40 text-sm font-semibold text-slate-900 dark:text-white">
                                                        {{ $currency(round((float) ($row['unit_price'] ?: 0), 2) * (float) $row['quantity']) }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="{{ $label }}">{{ __('Brand Offered') }}</label>
                                                    <input type="text" wire:model="priceRows.{{ $index }}.offered_brand" placeholder="{{ __('If not the one asked for') }}" class="{{ $field }}">
                                                    @error('priceRows.'.$index.'.offered_brand') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                                </div>
                                                <div>
                                                    <label class="{{ $label }}">{{ __('Note') }}</label>
                                                    <input type="text" wire:model="priceRows.{{ $index }}.notes" class="{{ $field }}">
                                                    @error('priceRows.'.$index.'.notes') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                                </div>
                                                <div class="sm:col-span-4">
                                                    <label class="{{ $label }}">{{ __('Spec Offered') }}</label>
                                                    <textarea wire:model="priceRows.{{ $index }}.offered_spec" rows="2" placeholder="{{ __('Only if the vendor quoted something different from what was asked.') }}" class="{{ $field }}"></textarea>
                                                    @error('priceRows.'.$index.'.offered_spec') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                        @else
                                            <div class="mt-3">
                                                <label class="{{ $label }}">{{ __('Note') }}</label>
                                                <input type="text" wire:model="priceRows.{{ $index }}.notes" placeholder="{{ __('Why they cannot supply it, if they said.') }}" class="{{ $field }}">
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- The terms and the total -->
                    <div class="space-y-4">
                        <!-- Running total, so nobody needs a calculator -->
                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Equalized Total') }}</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Lines') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">{{ $currency($totals['subtotal']) }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Freight') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">{{ $currency($totals['freight']) }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Taxes') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">{{ $currency($totals['tax']) }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Discount') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">− {{ $currency($totals['discount']) }}</dd>
                                </div>
                                <div class="flex items-center justify-between border-t border-slate-200 dark:border-slate-700 pt-2">
                                    <dt class="font-semibold text-slate-900 dark:text-white">{{ __('Total') }}</dt>
                                    <dd class="text-lg font-bold text-slate-900 dark:text-white">{{ $currency($totals['total']) }}</dd>
                                </div>
                                @if($proposalMode === 'negotiation')
                                    @php $movement = round($pricingVendorRow->equalizedTotal() - $totals['total'], 2); @endphp
                                    <div class="flex items-center justify-between">
                                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Against the standing offer') }}</dt>
                                        <dd class="font-semibold {{ $movement > 0 ? 'text-green-600 dark:text-green-400' : ($movement < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400') }}">
                                            {{ $movement > 0 ? '− ' : ($movement < 0 ? '+ ' : '') }}{{ $currency(abs($movement)) }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                            @if($totals['unavailable'] > 0)
                                <p class="mt-3 text-xs text-amber-600 dark:text-amber-400">
                                    {{ trans_choice('One line cannot be supplied and is excluded from the total.|:count lines cannot be supplied and are excluded from the total.', $totals['unavailable'], ['count' => $totals['unavailable']]) }}
                                </p>
                            @endif
                        </div>

                        @if($pricingVendorRow->negotiations->count() > 0)
                            <div class="{{ $card }}">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Rounds So Far') }}</h3>
                                <ul class="space-y-2 text-sm">
                                    @foreach($pricingVendorRow->negotiations as $negotiation)
                                        <li>
                                            <span class="font-medium text-slate-900 dark:text-white">{{ __('Round :number', ['number' => $negotiation->round]) }}</span>
                                            <span class="text-slate-500 dark:text-slate-400">
                                                {{ $currency($negotiation->previous_total) }} &rarr; {{ $currency($negotiation->new_total) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="{{ $card }} space-y-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Proposal Terms') }}</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">{{ __('Freight') }}</label>
                                    <select wire:model="prop_freight_type" class="{{ $field }}">
                                        <option value="">{{ __('Not stated') }}</option>
                                        <option value="cif">{{ __('CIF — vendor pays') }}</option>
                                        <option value="fob">{{ __('FOB — we pay') }}</option>
                                    </select>
                                    @error('prop_freight_type') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">{{ __('Freight Amount') }}</label>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="prop_freight_amount" placeholder="0.00" class="{{ $field }}">
                                    @error('prop_freight_amount') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">{{ __('Taxes') }}</label>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="prop_tax_amount" placeholder="0.00" class="{{ $field }}">
                                    @error('prop_tax_amount') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">{{ __('Discount') }}</label>
                                    <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="prop_discount_amount" placeholder="0.00" class="{{ $field }}">
                                    @error('prop_discount_amount') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">{{ __('Lead Time (days)') }}</label>
                                    <input type="number" min="0" wire:model="prop_lead_time_days" class="{{ $field }}">
                                    @error('prop_lead_time_days') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">{{ __('Valid Until') }}</label>
                                    <x-ui.date-input wire:model="prop_valid_until" class="{{ $field }}" />
                                    @error('prop_valid_until') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="{{ $label }}">{{ __('Payment Terms') }}</label>
                                    <input type="text" wire:model="prop_payment_terms" placeholder="{{ __('e.g. 30/60/90 days, cash on delivery') }}" class="{{ $field }}">
                                    @error('prop_payment_terms') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="{{ $card }} space-y-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('How It Arrived') }}</h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="{{ $label }}">{{ __('Channel') }} <span class="text-red-500">*</span></label>
                                    <select wire:model="prop_source" class="{{ $field }}">
                                        <option value="email">{{ __('E-mail') }}</option>
                                        <option value="whatsapp">{{ __('WhatsApp') }}</option>
                                        <option value="phone">{{ __('Phone') }}</option>
                                        <option value="in_person">{{ __('In person') }}</option>
                                    </select>
                                    @error('prop_source') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="{{ $label }}">{{ __('Received On') }} <span class="text-red-500">*</span></label>
                                    <x-ui.date-input wire:model="prop_received_at" class="{{ $field }}" />
                                    @error('prop_received_at') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="{{ $label }}">{{ __('Notes') }}</label>
                                <textarea wire:model="prop_notes" rows="3" placeholder="{{ __('Anything the vendor said that the numbers do not capture.') }}" class="{{ $field }}"></textarea>
                                @error('prop_notes') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="{{ $label }}">{{ __('The Vendor’s Proposal') }}</label>
                                <x-ui.file-drop
                                    wire:model="prop_new_uploads"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    :hint="__('Attach the PDF they sent, so every keyed-in price has the original behind it.')">

                                    @error('prop_uploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    @error('prop_new_uploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                    @error('prop_new_uploads.*') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                    @if(count($prop_uploads) > 0)
                                        <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                            @foreach($prop_uploads as $index => $file)
                                                <li wire:key="prop_uploads-{{ $index }}" class="px-3 py-2 flex items-center justify-between gap-3">
                                                    <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                                        {{ $file->getClientOriginalName() }}
                                                    </span>
                                                    <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                                        {{ \App\Services\DocumentSettings::formatBytes($file->getSize()) }}
                                                    </span>
                                                    <x-ui.icon-button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="trash"
                                                        type="button"
                                                        wire:click="discardProposalUpload({{ $index }})"
                                                        title="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                                        aria-label="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                                        class="hover:text-red-600 dark:hover:text-red-400" />
                                                </li>
                                            @endforeach
                                        </ul>

                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ trans_choice(':count file goes up when this is saved.|:count files go up when this is saved.', count($prop_uploads), ['count' => count($prop_uploads)]) }}
                                        </p>
                                    @endif
                                </x-ui.file-drop>
                                @if($pricingVendorRow->attachments->count() > 0)
                                    <ul class="mt-2 space-y-1">
                                        @foreach($pricingVendorRow->attachments as $attachment)
                                            <li class="text-xs text-slate-500 dark:text-slate-400">{{ $attachment->original_name }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $proposalMode === 'negotiation'
                            ? __('The round is kept with the before and after totals, and the quotation moves to negotiating.')
                            : __('Saving marks this vendor as having responded and moves the round to comparing.') }}
                    </p>
                    <div class="flex items-center gap-3">
                        <x-ui.button type="button" variant="secondary" wire:click="closeProposalModal">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon="save">
                            {{ $proposalMode === 'negotiation' ? __('Record the Round') : __('Save the Proposal') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    @endif
</x-ui.modal>
