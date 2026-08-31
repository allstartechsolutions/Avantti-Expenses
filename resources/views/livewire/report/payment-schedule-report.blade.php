<div>
    @php
        $currency = config('app.currency');
        $locale = config('app.locale');
    @endphp

    {{-- Page header --}}
    <div class="mb-6 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Payment Schedule') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Everything the company owes and has paid — expenses by due date with monthly projections, plus contract balances — across the whole system or filtered by client, project, or job site.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @can('reports.export')
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </x-ui.button>
            @endcan
            {{-- PDF links build their URL at click time from the address bar, which
                 Livewire keeps in sync with the active filters — so the export always
                 matches what is on screen. --}}
            <x-ui.button variant="secondary" href="{{ route('reports.payment-schedule.pdf.view') }}" icon="eye"
                x-data x-on:click.prevent="window.open($el.getAttribute('href') + window.location.search, '_blank')">
                {{ __('View PDF') }}
            </x-ui.button>
            <x-ui.button variant="primary" href="{{ route('reports.payment-schedule.pdf.download') }}" icon="arrow-down-tray"
                x-data x-on:click.prevent="window.location.href = $el.getAttribute('href') + window.location.search">
                {{ __('Download PDF') }}
            </x-ui.button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('From') }}</label>
                <x-ui.date-input wire:model.live="fromDate" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('To') }}</label>
                <x-ui.date-input wire:model.live="toDate" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]" />
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Client') }}</label>
                <select wire:model.live="clientFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All clients') }}</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Project') }}</label>
                <select wire:model.live="projectFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All projects') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Job Site') }}</label>
                <select wire:model.live="jobSiteFilter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189]">
                    <option value="">{{ __('All job sites') }}</option>
                    @foreach ($jobSites as $j)
                        <option value="{{ $j->id }}">{{ $j->job_site_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="button" wire:click="setAllTime" class="px-3 py-1 text-xs rounded-md {{ !$fromDate && !$toDate ? 'bg-[#3F5189] text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' }}">{{ __('All time') }}</button>
            <button type="button" wire:click="setCurrentMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('This month') }}</button>
            <button type="button" wire:click="setNextMonth" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Next month') }}</button>
            <button type="button" wire:click="setNext3Months" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('Next 3 months') }}</button>
            <button type="button" wire:click="setThisYear" class="px-3 py-1 text-xs rounded-md bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600">{{ __('This year') }}</button>
        </div>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            {{ __('Open items are matched by due date; payments made are matched by paid date. Expenses without a due date are dated by their expense date, so nothing is left out.') }}
        </p>
    </div>

    @if ($fromDate || $toDate)
        <div class="mb-6 px-6 py-3 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-900/30 rounded-lg">
            <p class="text-xs text-blue-800 dark:text-blue-300">
                {{ __('Showing period') }}: <strong>{{ $fromDate ? \Carbon\Carbon::parse($fromDate)->appDate() : __('beginning') }}</strong> — <strong>{{ $toDate ? \Carbon\Carbon::parse($toDate)->appDate() : __('open-ended') }}</strong>.
                {{ __('Contract installments are matched by their due date and contract payments by their payment date, like expenses.') }}
            </p>
        </div>
    @endif

    {{-- Payment schedule body (shared with the project/jobsite financial reports) --}}
    @include('livewire.shared.payment-schedule-section', ['schedule' => $schedule])
</div>
