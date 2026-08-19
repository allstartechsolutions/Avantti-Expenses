{{--
    The RFQ e-mail composer — one request, several vendors, one PDF each.
    Expects: $viewingQuotation
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $deliverable = $this->rfqMailIsDeliverable();
@endphp

<x-ui.modal name="quotation-send-modal" maxWidth="full" layer="top">
    @if($viewingQuotation)
        <div class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Send the Quotation Request') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ $viewingQuotation->quotation_number ?? '#'.$viewingQuotation->id }} &middot; {{ $viewingQuotation->title }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeSendModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        title="{{ __('Close') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6 space-y-6">
                @unless($deliverable)
                    <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5">
                        <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('This install cannot deliver e-mail yet') }}</h3>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                            {{ __('No mail server is configured, so nothing will reach the vendors. Download the request as a PDF and send it from your own e-mail, then record the round as sent — or send anyway to write the message to the log while testing.') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-3">
                            <x-ui.button
                                variant="outline"
                                size="sm"
                                href="{{ route('quotations.rfq.pdf.download', $viewingQuotation->id) }}"
                                icon="download">
                                {{ __('Download the PDF') }}
                            </x-ui.button>
                            <x-ui.button
                                variant="ghost"
                                size="sm"
                                href="{{ route('quotations.rfq.pdf.view', $viewingQuotation->id) }}"
                                target="_blank"
                                icon="eye">
                                {{ __('Preview') }}
                            </x-ui.button>
                        </div>
                    </div>
                @endunless

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- The message -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="{{ $card }} space-y-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Message') }}</h3>

                            <div>
                                <label class="{{ $label }}">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="rfqSubject" class="{{ $field }}">
                                @error('rfqSubject') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="{{ $label }}">{{ __('Message') }} <span class="text-red-500">*</span></label>
                                <textarea wire:model="rfqBody" rows="10" class="{{ $field }}"></textarea>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Basic HTML is allowed. The scope and the PDF are added automatically.') }}</p>
                                @error('rfqBody') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="{{ $label }}">{{ __('CC') }}</label>
                                <input type="text" wire:model="rfqCc" placeholder="{{ __('you@company.com, buyer@company.com') }}" class="{{ $field }}">
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Separate addresses with commas. Every vendor gets their own e-mail — they never see each other.') }}</p>
                                @error('rfqCc') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ __('What goes with it') }}</h3>
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                {{ trans_choice('A PDF of the scope with :count line and empty price columns.|A PDF of the scope with :count lines and empty price columns.', $viewingQuotation->items->count(), ['count' => $viewingQuotation->items->count()]) }}
                                {{ __('It also asks for freight, taxes, lead time, payment terms and proposal validity — the facts the comparison needs.') }}
                            </p>
                            <div class="mt-3">
                                <x-ui.button
                                    variant="ghost"
                                    size="sm"
                                    href="{{ route('quotations.rfq.pdf.view', $viewingQuotation->id) }}"
                                    target="_blank"
                                    icon="eye">
                                    {{ __('Preview the PDF') }}
                                </x-ui.button>
                            </div>
                        </div>
                    </div>

                    <!-- The recipients -->
                    <div class="space-y-4">
                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Recipients') }}</h3>

                            @error('rfqRecipients') <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                            @if(count($rfqRecipients) > 0)
                                <div class="space-y-3">
                                    @foreach($rfqRecipients as $index => $recipient)
                                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3" wire:key="rfq-recipient-{{ $index }}">
                                            <label class="flex items-start gap-2">
                                                <input type="checkbox" wire:model.live="rfqRecipients.{{ $index }}.selected" class="mt-1 rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $recipient['vendor_name'] }}</span>
                                            </label>
                                            <div class="mt-2">
                                                <input type="email" wire:model="rfqRecipients.{{ $index }}.email" placeholder="{{ __('vendor@example.com') }}" class="{{ $field }}">
                                                @error('rfqRecipients.'.$index.'.email') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                                @if(empty($recipient['email']))
                                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('No address on file — type one to include this vendor.') }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">
                                    {{ __('No vendor to write to. Invite them on the round first.') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $deliverable
                            ? __('Each vendor receives their own copy, and the round is marked as sent.')
                            : __('Sending now would only write to the application log — useful for testing, useless to the vendors.') }}
                    </p>
                    <div class="flex items-center gap-3">
                        <x-ui.button variant="secondary" wire:click="closeSendModal">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button
                            variant="{{ $deliverable ? 'primary' : 'secondary' }}"
                            icon="send"
                            wire:click="sendRfq({{ $viewingQuotation->id }})"
                            wire:loading.attr="disabled"
                            wire:target="sendRfq">
                            <span wire:loading.remove wire:target="sendRfq">
                                {{ $deliverable ? __('Send the Request') : __('Send anyway (log only)') }}
                            </span>
                            <span wire:loading wire:target="sendRfq">{{ __('Sending...') }}</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-ui.modal>
