<div>
    {{-- Already Paid --}}
    @if($invoice->isPaid() && !$paymentSuccess)
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-8 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-slate-900 mb-2">{{ __('Invoice Paid') }}</h2>
            <p class="text-slate-500">{!! __('Invoice :number has already been paid in full.', ['number' => '<span class="font-medium">'.e($invoice->invoice_number).'</span>']) !!}</p>
            <p class="text-slate-500 mt-1">{{ __('Thank you!') }}</p>
        </div>

    {{-- Payment Success --}}
    @elseif($paymentSuccess)
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-8 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 class="text-xl font-semibold text-slate-900 mb-2">{{ __('Payment Successful') }}</h2>
            <p class="text-slate-500">
                {!! __('Your payment of :amount for invoice :number has been processed.', [
                    'amount' => '<span class="font-semibold text-slate-900">$'.e($paidAmountDisplay).'</span>',
                    'number' => '<span class="font-medium">'.e($invoice->invoice_number).'</span>',
                ]) !!}
            </p>
            @if($invoice->getBalanceDue() > 0)
                <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg inline-block">
                    <p class="text-sm text-yellow-800">
                        {!! __('Remaining balance: :amount', ['amount' => '<span class="font-semibold">$'.e(number_format($invoice->getBalanceDue(), 2)).'</span>']) !!}
                    </p>
                </div>
            @endif
            <p class="text-sm text-slate-400 mt-4">{{ __('Thank you for your payment!') }}</p>
        </div>

    {{-- Payment Form --}}
    @else
        {{-- Invoice Summary --}}
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 mb-6">
            <div class="px-6 py-4 border-b border-slate-200">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h1 class="text-xl font-bold text-slate-900">{{ $invoice->invoice_number }}</h1>
                        <p class="text-sm text-slate-500">{{ $invoice->client->company_name }}</p>
                    </div>
                    <span class="inline-flex items-center self-start px-2.5 py-0.5 rounded-full text-xs font-medium {{ $invoice->status_color }}">
                        {{ $invoice->status_label }}
                    </span>
                </div>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="font-medium text-slate-500">{{ __('Invoice Date') }}</dt>
                        <dd class="mt-1 text-slate-900">{{ $invoice->invoice_date->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">{{ __('Due Date') }}</dt>
                        <dd class="mt-1 text-slate-900">{{ $invoice->due_date->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">{{ __('Total') }}</dt>
                        <dd class="mt-1 text-slate-900 font-semibold">${{ number_format($invoice->total_amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">{{ __('Balance Due') }}</dt>
                        <dd class="mt-1 text-slate-900 font-bold text-lg">${{ number_format($invoice->getBalanceDue(), 2) }}</dd>
                    </div>
                </div>

                @if($invoice->getAmountPaid() > 0)
                    <div class="mt-4 flex items-center gap-2 text-sm text-green-700 bg-green-50 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        {!! __(':amount already paid', ['amount' => '$'.e(number_format($invoice->getAmountPaid(), 2))]) !!}
                    </div>
                @endif
            </div>
        </div>

        {{-- Line Items --}}
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 mb-6">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Items') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">{{ __('Item') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Qty') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Price') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($invoice->items as $item)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900">{{ $item->item_name }}</div>
                                    @if($item->description)
                                        <div class="text-xs text-slate-500 mt-1">{{ $item->description }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-900 text-right">{{ $item->quantity }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900 text-right">${{ number_format($item->unit_price, 2) }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-slate-900 text-right">${{ number_format($item->total_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200">
                <div class="max-w-xs ml-auto space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-600">{{ __('Subtotal') }}</span>
                        <span class="text-slate-900">${{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    @if($invoice->discount_amount > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">
                                {{ __('Discount') }}
                                @if($invoice->discount_type === 'percentage')
                                    ({{ number_format($invoice->discount_value, 2) }}%)
                                @endif
                            </span>
                            <span class="text-red-600">-${{ number_format($invoice->discount_amount, 2) }}</span>
                        </div>
                    @endif
                    @if($invoice->tax_total > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">{{ __('Tax') }}</span>
                            <span class="text-slate-900">${{ number_format($invoice->tax_total, 2) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-base font-semibold pt-2 border-t border-slate-200">
                        <span class="text-slate-900">{{ __('Total') }}</span>
                        <span class="text-slate-900">${{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    @if($invoice->getAmountPaid() > 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600">{{ __('Amount Paid') }}</span>
                            <span class="text-green-600">-${{ number_format($invoice->getAmountPaid(), 2) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold pt-1 border-t border-slate-200">
                            <span class="text-slate-900">{{ __('Balance Due') }}</span>
                            <span class="text-slate-900">${{ number_format($invoice->getBalanceDue(), 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Payment Form --}}
        @if($cardPointeConfigured)
            <div class="bg-white rounded-lg shadow-sm border border-slate-200"
                x-data="{
                    tokenReceived: false,
                    displayAmount: '{{ number_format((float) $paymentAmount, 2) }}',
                    init() {
                        window.addEventListener('message', (event) => {
                            try {
                                let data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                                if (data.token) {
                                    this.tokenReceived = true;
                                    $wire.setCardToken(data.token);
                                }
                            } catch (e) {}
                        });
                    }
                }"
            >
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Pay with Card') }}</h2>
                </div>
                <div class="p-6 space-y-4">
                    {{-- Amount --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Amount *') }}</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-slate-500 sm:text-sm">$</span>
                            </div>
                            <input
                                type="number"
                                wire:model="paymentAmount"
                                x-on:input="displayAmount = parseFloat($event.target.value || 0).toFixed(2)"
                                step="0.01"
                                min="0.01"
                                max="{{ $invoice->getBalanceDue() }}"
                                class="w-full pl-7 pr-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white text-slate-900"
                                placeholder="0.00"
                                required>
                        </div>
                        @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Card Number (iFrame) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Card Number *') }}</label>
                        <div class="rounded-lg border border-slate-300 overflow-hidden bg-white">
                            <iframe
                                src="{{ $iframeUrl }}"
                                frameborder="0"
                                scrolling="no"
                                style="width: 100%; height: 38px;"
                            ></iframe>
                        </div>
                        <div x-show="tokenReceived" class="mt-1 flex items-center gap-1 text-xs text-green-600">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('Card tokenized') }}
                        </div>
                    </div>

                    {{-- Name on Card --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Name on Card *') }}</label>
                        <input
                            type="text"
                            wire:model="cardName"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white text-slate-900"
                            placeholder="{{ __('John Doe') }}">
                        @error('cardName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Expiry + CVV --}}
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Month *') }}</label>
                            <select
                                wire:model="cardExpiryMonth"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white text-slate-900">
                                <option value="">{{ __('MM') }}</option>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                            @error('cardExpiryMonth') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Year *') }}</label>
                            <select
                                wire:model="cardExpiryYear"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white text-slate-900">
                                <option value="">{{ __('YY') }}</option>
                                @for($y = now()->format('y'); $y <= now()->format('y') + 15; $y++)
                                    <option value="{{ str_pad($y, 2, '0', STR_PAD_LEFT) }}">{{ 2000 + $y }}</option>
                                @endfor
                            </select>
                            @error('cardExpiryYear') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('CVV *') }}</label>
                            <input
                                type="text"
                                wire:model="cardCvv"
                                maxlength="4"
                                inputmode="numeric"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white text-slate-900"
                                placeholder="123">
                            @error('cardCvv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Billing Zip --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Billing Zip Code *') }}</label>
                        <input
                            type="text"
                            wire:model="cardZip"
                            maxlength="10"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white text-slate-900"
                            placeholder="12345">
                        @error('cardZip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Error --}}
                    @if($cardPaymentError)
                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm text-red-700">{{ $cardPaymentError }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Pay Button --}}
                <div class="px-6 py-4 border-t border-slate-200">
                    <button
                        type="button"
                        wire:click="processPayment"
                        wire:loading.attr="disabled"
                        wire:target="processPayment"
                        :disabled="!tokenReceived"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-[#3F5189] text-white text-sm font-medium rounded-lg hover:bg-[#354570] focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        <svg wire:loading wire:target="processPayment" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg wire:loading.remove wire:target="processPayment" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <span wire:loading wire:target="processPayment">{{ __('Processing...') }}</span>
                        <span wire:loading.remove wire:target="processPayment" x-text="'Pay $' + displayAmount"></span>
                    </button>
                    <p class="text-xs text-slate-400 text-center mt-3">
                        <svg class="w-3.5 h-3.5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        {{ __('Payments are securely processed. Card details never touch our server.') }}
                    </p>
                </div>
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-8 text-center">
                <p class="text-slate-500">{{ __('Online payment is not currently available. Please contact us for payment options.') }}</p>
            </div>
        @endif
    @endif
</div>
