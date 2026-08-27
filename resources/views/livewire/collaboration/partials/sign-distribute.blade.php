{{--
    The two dialogs both detail screens share: putting your name to the record,
    and posting it out. `$document` and `$signatures` come from the page.
--}}
@php
    $input = 'mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $isBR = config('app.country') === 'BR';
    $stampFormat = $isBR ? 'd/m/Y H:i' : 'm/d/Y g:i A';
@endphp

@if($signatures->isNotEmpty())
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.signatures') }}</h2>
        </div>
        <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
            @foreach($signatures as $signature)
                @php $intact = $document->signatureIsIntact($signature); @endphp
                <li class="px-5 py-3">
                    <p class="text-slate-900 dark:text-white">{{ $signature->getSignerLine() }}</p>
                    @if($signature->art_number)
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('collaboration.pdf.art') }} {{ $signature->art_number }}</p>
                    @endif
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $signature->getMethodLabel() }} · {{ $signature->signed_at?->format($stampFormat) }}
                    </p>

                    {{-- A signature that no longer covers what is on screen has
                         to say so, or the page claims more than it can. --}}
                    @unless($intact)
                        <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">
                            {{ __('collaboration.help.document_changed_since_signed') }}
                        </p>
                    @endunless
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if($this->canSign)
    <x-ui.modal name="document-sign" maxWidth="lg">
        <form wire:submit="signDocument" class="p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.sign_document') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('collaboration.help.name_recorded_against_document_reads') }}
            </p>

            @if($isBR)
                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.crea_cau_registration') }}</label>
                <input type="text" wire:model="signerDocument" class="{{ $input }}" placeholder="{{ __('collaboration.placeholder.e_g_crea_12345') }}">
                @error('signerDocument') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.art_number') }}</label>
                <input type="text" wire:model="artNumber" class="{{ $input }}">
                @error('artNumber') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            @else
                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.professional_registration') }}</label>
                <input type="text" wire:model="signerDocument" class="{{ $input }}">
                @error('signerDocument') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            @endif

            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'document-sign')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="primary" type="submit">{{ __('collaboration.label.sign') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif

@if($this->canDistribute)
    <x-ui.modal name="document-distribute" maxWidth="lg">
        <form wire:submit="distributeDocument" class="p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.send_distribution_list') }}</h2>

            @if($document->distribution->isEmpty())
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.nobody_distribution_list_there_nowhere') }}
                </p>
            @else
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ trans_choice('collaboration.count.document_goes_recipient_sheet',
                        $document->distribution->count(), ['count' => $document->distribution->count()]) }}
                </p>

                <ul class="mt-3 text-sm text-slate-600 dark:text-slate-300 list-disc list-inside">
                    @foreach($document->distribution as $entry)
                        <li>{{ $entry->getName() }} <span class="text-xs text-slate-400 dark:text-slate-500">{{ $entry->getEmail() }}</span></li>
                    @endforeach
                </ul>
            @endif

            <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.note_optional') }}</label>
            <textarea wire:model="distributionNote" rows="3" class="{{ $input }}"
                placeholder="{{ __('collaboration.message.anything_recipients_should_know') }}"></textarea>
            @error('distributionNote') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'document-distribute')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="primary" type="submit" wire:loading.attr="disabled">{{ __('collaboration.label.send') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
@endif
