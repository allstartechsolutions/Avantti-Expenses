{{-- Duplicate-company suggestions under the name field on create forms.
     Expects: $matches (array), $flagKey ('is_supplier'|'is_subcontractor'),
     $flagAction (Livewire method), $alreadyLabel, $confirmText --}}
@if(!empty($matches))
    <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
        <p class="text-sm font-medium text-amber-800 dark:text-amber-300 mb-2">{{ __('Similar companies already registered:') }}</p>
        <ul class="space-y-2">
            @foreach($matches as $match)
                <li class="flex items-center justify-between gap-3">
                    <span class="text-sm text-slate-900 dark:text-white">
                        {{ $match['name'] }}
                        @if($match['is_supplier'])
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ __('Supplier') }}</span>
                        @endif
                        @if($match['is_subcontractor'])
                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Subcontractor') }}</span>
                        @endif
                    </span>
                    @if($match[$flagKey])
                        <span class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $alreadyLabel }}</span>
                    @else
                        <x-ui.button
                            type="button"
                            variant="ghost"
                            size="sm"
                            wire:click="{{ $flagAction }}({{ $match['id'] }})"
                            wire:confirm="{{ $confirmText }}">
                            {{ __('Use this company') }}
                        </x-ui.button>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
