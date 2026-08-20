{{--
    The Team tab lets you configure access that is not switched on yet. Saying
    so is not optional: wording that promises something the code does not
    enforce is a bug (CLAUDE.md).

    This partial disappears by itself — it renders nothing once every area of
    the catalogue has had its permission pass.
--}}
@php
    $catalogue = \App\Services\AbilityCatalog::areas();
    $pending = \App\Services\AbilityCatalog::unsweptAreas();
    $enforced = count($catalogue) - count($pending);
@endphp

@if(count($pending) > 0)
    <div class="p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.71-3.03l-6.93-11a2 2 0 00-3.42 0l-6.93 11A2 2 0 005.07 19z"></path>
            </svg>
            <div class="text-sm">
                <p class="font-medium">{{ __('This team list does not restrict anybody yet.') }}</p>
                <p class="mt-1">
                    {{ __(':enforced of :total modules have been converted to these permissions. Until a module is converted it keeps its old rules, and every signed-in person can still reach it. Set access up here now — it takes effect as each module is converted.', ['enforced' => $enforced, 'total' => count($catalogue)]) }}
                </p>
                @can('access.view')
                    <p class="mt-1">
                        <a href="{{ route('access.index') }}" class="underline font-medium">{{ __('See which modules are converted') }}</a>
                    </p>
                @endcan
            </div>
        </div>
    </div>
@endif
