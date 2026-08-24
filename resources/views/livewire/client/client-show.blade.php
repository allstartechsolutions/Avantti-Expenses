<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Client Details') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('View client information') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('clients.index') }}"
                    icon="arrow-left">
                    {{ __('Back to Clients') }}
                </x-ui.button>
                <x-ui.button
                    variant="primary"
                    href="{{ route('clients.edit', $client->id) }}"
                    icon="edit">
                    {{ __('Edit Client') }}
                </x-ui.button>
                @if($projectsCount > 0)
                    <span title="Cannot delete: linked to {{ $projectsCount }} project(s)">
                        <x-ui.button
                            variant="danger"
                            icon="trash"
                            disabled>
                            {{ __('Delete') }}
                        </x-ui.button>
                    </span>
                @else
                    <x-ui.button
                        variant="danger"
                        wire:click="confirmDeleteClient"
                        icon="trash">
                        {{ __('Delete') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Error Message -->
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Company Profile Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Company Information') }}</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center space-x-6 mb-6">
                        <div class="flex-shrink-0">
                            <div class="h-20 w-20 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center text-white text-2xl font-medium">
                                {{ $client->initials }}
                            </div>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $client->company_name }}</h2>
                            <p class="text-slate-500 dark:text-slate-400">{{ $client->email }}</p>
                            @if($client->website)
                                <a href="{{ $client->website }}" target="_blank" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ $client->website }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Company Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Company Name') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->company_name }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Email Address') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->email }}</p>
                        </div>

                        <!-- Website -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Website') }}
                            </label>
                            @if($client->website)
                                <a href="{{ $client->website }}" target="_blank" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ $client->website }}
                                </a>
                            @else
                                <p class="text-slate-900 dark:text-white">{{ __('Not provided') }}</p>
                            @endif
                        </div>

                        <!-- Created At -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Added On') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->created_at->format('F d, Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Person Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contact Person') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Contact Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Full Name') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->contact_name }}</p>
                        </div>

                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Title/Position') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->title ?? __('Not provided') }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Phone Number') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->formatted_phone ?? __('Not provided') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Address Information') }}</h3>
                </div>
                <div class="p-6">
                    @if($client->full_address)
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Full Address') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->full_address }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Street -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Street Address') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->street ?? __('Not provided') }}</p>
                        </div>

                        <!-- City -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('City') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->city ?? __('Not provided') }}</p>
                        </div>

                        <!-- State -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('State') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->state ?? __('Not provided') }}</p>
                        </div>

                        <!-- Postal Code -->
                        <div>
                            <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                {{ __('Postal Code') }}
                            </label>
                            <p class="text-slate-900 dark:text-white">{{ $client->postal_code ?? __('Not provided') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Methods Card -->
            @if($cardPointeConfigured)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Payment Methods') }}</h3>
                        <x-ui.button variant="primary" size="sm" wire:click="openAddCardModal" icon="plus">
                            {{ __('Add Card') }}
                        </x-ui.button>
                    </div>
                    <div class="p-6">
                        @if(count($paymentMethods) > 0)
                            <div class="space-y-3">
                                @foreach($paymentMethods as $pm)
                                    <div class="flex items-center justify-between p-4 rounded-lg border border-slate-200 dark:border-slate-600 {{ $pm['is_default'] ? 'bg-blue-50/50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800' : '' }}">
                                        <div class="flex items-center gap-3">
                                            {{-- Card icon --}}
                                            <div class="flex-shrink-0">
                                                <svg class="w-8 h-8 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-slate-900 dark:text-white">
                                                        {{ __(':brand ending in :last4', ['brand' => $pm['card_brand'] ? ucfirst($pm['card_brand']) : __('Card'), 'last4' => $pm['card_last_four']]) }}
                                                    </span>
                                                    @if($pm['is_default'])
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                            {{ __('Default') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if($pm['expiry_formatted'])
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Expires :date', ['date' => $pm['expiry_formatted']]) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if(!$pm['is_default'])
                                                <x-ui.button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="setDefaultCard({{ $pm['id'] }})"
                                                    title="{{ __('Set as default') }}">
                                                    {{ __('Set Default') }}
                                                </x-ui.button>
                                            @endif
                                            <x-ui.button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="openEditCardModal({{ $pm['id'] }})"
                                                icon="edit"
                                                title="{{ __('Edit expiry') }}">
                                            </x-ui.button>
                                            <x-ui.button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="deleteCard({{ $pm['id'] }})"
                                                wire:confirm="{{ __('Are you sure you want to remove this payment method?') }}"
                                                icon="trash"
                                                title="{{ __('Remove card') }}">
                                            </x-ui.button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">{{ __('No payment methods saved.') }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar Actions -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Quick Actions') }}</h3>
                </div>
                <div class="p-6 space-y-3">
                    <x-ui.button
                        variant="secondary"
                        class="w-full justify-center"
                        href="{{ route('clients.edit', $client->id) }}"
                        icon="edit">
                        {{ __('Edit Client') }}
                    </x-ui.button>

                    @if($client->email)
                        <a href="mailto:{{ $client->email }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            {{ __('Send Email') }}
                        </a>
                    @endif

                    @if($client->phone)
                        <a href="tel:{{ $client->phone }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            {{ __('Call Client') }}
                        </a>
                    @endif
                </div>
            </div>

            <!-- Client Stats -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Client Information') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Client ID') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $client->id }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Added') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->created_at->diffForHumans() }}</span>
                    </div>
                    @if($client->created_at != $client->updated_at)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Last Updated') }}</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->updated_at->diffForHumans() }}</span>
                        </div>
                    @endif
                    @if($client->createdBy)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Added By') }}</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->createdBy->name }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Add Card Modal -->
    @if($showAddCardModal)
        <x-ui.modal name="add-card-modal" :show="true" maxWidth="lg">
            <div
                x-data="{
                    tokenReceived: false,
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
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Add Payment Method') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    {{-- Card Number (iFrame tokenizer) --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Card Number *') }}</label>
                        <div class="rounded-lg border border-slate-300 dark:border-slate-600 overflow-hidden bg-white">
                            <iframe
                                src="{{ $iframeUrl }}"
                                frameborder="0"
                                scrolling="no"
                                style="width: 100%; height: 38px;"
                            ></iframe>
                        </div>
                        <div x-show="tokenReceived" class="mt-1 flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('Card tokenized') }}
                        </div>
                    </div>

                    {{-- Name on Card --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Name on Card *') }}</label>
                        <input
                            type="text"
                            wire:model="cardName"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('John Doe') }}">
                        @error('cardName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Expiry + CVV --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Expiration *') }}</label>
                            <input
                                type="text"
                                wire:model="cardExpiry"
                                maxlength="4"
                                inputmode="numeric"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('MMYY') }}">
                            @error('cardExpiry') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('CVV *') }}</label>
                            <input
                                type="text"
                                wire:model="cardCvv"
                                maxlength="4"
                                inputmode="numeric"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="123">
                            @error('cardCvv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Billing Zip --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Billing Zip Code *') }}</label>
                        <input
                            type="text"
                            wire:model="cardZip"
                            maxlength="10"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="12345">
                        @error('cardZip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Error --}}
                    @if($cardError)
                        <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm text-red-700 dark:text-red-300">{{ $cardError }}</p>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button variant="secondary" wire:click="$set('showAddCardModal', false)" icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" wire:click="addCard" icon="plus" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="addCard">{{ __('Add Card') }}</span>
                        <span wire:loading wire:target="addCard">{{ __('Processing...') }}</span>
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif

    <!-- Edit Card Modal -->
    @if($showEditCardModal)
        <x-ui.modal name="edit-card-modal" :show="true" maxWidth="md">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Edit Payment Method') }}</h3>
            </div>
            <div class="p-6 space-y-4">
                {{-- Card display (read-only) --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Card') }}</label>
                    <p class="text-sm text-slate-900 dark:text-white">{{ $editCardDisplayName }}</p>
                </div>

                {{-- Name on Card --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Name on Card *') }}</label>
                    <input
                        type="text"
                        wire:model="editCardName"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('John Doe') }}">
                    @error('editCardName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Expiry --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Expiration *') }}</label>
                    <input
                        type="text"
                        wire:model="editExpiry"
                        maxlength="4"
                        inputmode="numeric"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('MMYY') }}">
                    @error('editExpiry') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Error --}}
                @if($editError)
                    <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-red-700 dark:text-red-300">{{ $editError }}</p>
                        </div>
                    </div>
                @endif
            </div>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                <x-ui.button variant="secondary" wire:click="$set('showEditCardModal', false)" icon="x">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="updateCard" icon="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="updateCard">{{ __('Update') }}</span>
                    <span wire:loading wire:target="updateCard">{{ __('Updating...') }}</span>
                </x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    <!-- Delete Client Confirmation Modal -->
    @if($showDeleteModal)
        <x-ui.modal name="delete-client-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    {{ __('Delete Client') }}
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    Are you sure you want to delete <strong>{{ $deleteClientData['name'] ?? $client->company_name }}</strong>?
                    This action <strong>{{ __('cannot be undone') }}</strong>.
                </p>

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDeleteClient"
                        icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteClient"
                        icon="trash">
                        {{ __('Delete Client') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
