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
        @php
            $exportParams = http_build_query([
                'clientFilter' => $clientFilter,
                'projectFilter' => $projectFilter,
                'jobSiteFilter' => $jobSiteFilter,
            ]);
        @endphp
        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" wire:click="exportCsv" icon="arrow-down-tray">
                {{ __('Export CSV') }}
            </x-ui.button>
            <x-ui.button variant="secondary" href="{{ route('reports.payment-schedule.pdf.view') }}?{{ $exportParams }}" icon="eye" target="_blank">
                {{ __('View PDF') }}
            </x-ui.button>
            <x-ui.button variant="primary" href="{{ route('reports.payment-schedule.pdf.download') }}?{{ $exportParams }}" icon="arrow-down-tray">
                {{ __('Download PDF') }}
            </x-ui.button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
    </div>

    {{-- Payment schedule body (shared with the project/jobsite financial reports) --}}
    @include('livewire.shared.payment-schedule-section', ['schedule' => $schedule])
</div>
