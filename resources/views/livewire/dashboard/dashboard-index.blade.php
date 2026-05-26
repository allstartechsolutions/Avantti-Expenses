<div>
    @if ($role === 'admin')
        @include('livewire.dashboard.partials.admin')
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-8 text-center">
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Welcome') }}, {{ auth()->user()->name }}</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Your dashboard is coming soon.') }}</p>
        </div>
    @endif
</div>
