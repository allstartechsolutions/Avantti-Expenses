@props(['vendor'])

{{-- Shown at the top of a detail page while the vendor is switched off. --}}
@unless($vendor->is_active)
    <div class="mb-6 p-4 bg-slate-100 border border-slate-300 text-slate-700 rounded-lg dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300 flex items-start gap-3">
        <svg class="h-5 w-5 mt-0.5 flex-shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
        <div class="min-w-0">
            <p class="font-medium">{{ __('This vendor is inactive.') }}</p>
            <p class="text-sm mt-0.5">
                @if($vendor->deactivated_at)
                    {{ __('Switched off on :date by :name.', ['date' => $vendor->deactivated_at->appDateTime(), 'name' => $vendor->deactivatedBy?->name ?? __('Unknown')]) }}
                @endif
                {{ __('Records that already name it are kept. New expenses, orders, contracts and quotations will not offer it until it is switched back on.') }}
            </p>
        </div>
    </div>
@endunless
