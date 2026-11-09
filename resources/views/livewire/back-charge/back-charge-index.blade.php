@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $select = 'block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5';
    $canDelete = auth()->user()->can('back-charges.delete');
    $canEdit = auth()->user()->can('back-charges.edit');
    // The company-wide total in the subtitle is a roll-up like the cards above it.
    $maySeeTotals = app(\App\Services\PermissionResolver::class)->canSeeMoney(auth()->user(), null);
@endphp
<div>
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Back Charges') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Money a subcontractor or one of its employees owes, taken out of what they are paid.') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <x-ui.button variant="secondary" wire:click="exportCsv" icon="download">{{ __('CSV') }}</x-ui.button>
                @can('back-charges.create')
                    <x-ui.button variant="primary" wire:click="openBackChargeCreate" icon="plus">{{ __('New Back Charge') }}</x-ui.button>
                @endcan
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="{{ $card }}">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Open') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $summary['open_count'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $summary['open_count'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('With something left to deduct') }}</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Left to deduct') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><x-ui.money rollup :amount="$summary['open_remaining']" /></p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Across the open back charges') }}</p>
        </div>
        <div class="{{ $card }}">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Deducted') }}</p>
            <p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"><x-ui.money rollup :amount="$summary['deducted']" /></p>
            @if($maySeeTotals)
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Of :total charged in all', ['total' => Number::currency($summary['total'], config('app.currency'), config('app.locale'))]) }}</p>
            @endif
        </div>
        <div class="{{ $card }}">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Fully deducted') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $summary['done_count'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count back charge in all|:count back charges in all', $summary['count'], ['count' => $summary['count']]) }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 p-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-6">
                <label for="search" class="sr-only">{{ __('Search back charges') }}</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" id="search" wire:model.live.debounce.300ms="search" class="{{ $select }} pl-10 placeholder-slate-400" placeholder="{{ __('Search by number, reason, subcontractor or employee…') }}">
                </div>
            </div>
            <div class="md:col-span-3">
                <label for="statusFilter" class="sr-only">{{ __('Status') }}</label>
                <select id="statusFilter" wire:model.live="statusFilter" class="{{ $select }}">
                    <option value="">{{ __('Status: all') }}</option>
                    <option value="open">{{ __('Open') }}</option>
                    <option value="deducted">{{ __('Fully deducted') }}</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label for="subcontractorFilter" class="sr-only">{{ __('Subcontractor') }}</label>
                <select id="subcontractorFilter" wire:model.live="subcontractorFilter" class="{{ $select }}">
                    <option value="">{{ __('Subcontractor: all') }}</option>
                    @foreach($subcontractors as $sub)
                        <option value="{{ $sub->id }}">{{ $sub->company_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if($this->hasFilters())
            <div class="mt-4 flex justify-end">
                <x-ui.button variant="secondary" size="sm" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button>
            </div>
        @endif
    </div>

    @if($backCharges->count() > 0)
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Back charge') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Owed by') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Where it happened') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Amount') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Deducted') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Remaining') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($backCharges as $bc)
                            @php $pct = $bc->amount > 0 ? min(100, round($bc->getDeducted() / $bc->amount * 100)) : 0; @endphp
                            <tr wire:key="back-charge-{{ $bc->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4">
                                    <a href="{{ route('back-charges.show', $bc) }}" wire:navigate class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $bc->number }}</a>
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $bc->reason }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $bc->date->appDate() }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-slate-900 dark:text-white">{{ $bc->subcontractor?->company_name ?? '—' }}</div>
                                    @if($bc->subcontractorEmployee)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('Employee: :name', ['name' => $bc->subcontractorEmployee->name]) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                    @if($bc->project)
                                        {{ $bc->project->project_name }}@if($bc->jobSite) <span class="text-slate-400">/</span> {{ $bc->jobSite->job_site_name }}@endif
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-white"><x-ui.money :amount="$bc->amount" /></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-700 dark:text-slate-300">
                                    <x-ui.money :amount="$bc->getDeducted()" />
                                    <div class="mt-1 ml-auto h-1.5 w-20 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden" title="{{ $pct }}%">
                                        <div class="h-full bg-green-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold {{ $bc->isFullyDeducted() ? 'text-slate-400 dark:text-slate-500' : 'text-amber-600 dark:text-amber-400' }}"><x-ui.money :amount="$bc->getRemaining()" /></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $bc->getStatusColor() === 'green' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' }}">{{ $bc->getStatusLabel() }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <x-ui.view-edit-buttons :viewRoute="route('back-charges.show', $bc)" :editAction="$canEdit ? 'openBackChargeEdit('.$bc->id.')' : null" />
                                        @if($canDelete && $bc->canBeDeleted())
                                            <x-ui.icon-button variant="danger" size="sm" icon="trash" wire:click="delete({{ $bc->id }})" wire:confirm="{{ __('Delete :number? Nothing has been deducted from it.', ['number' => $bc->number]) }}" title="{{ __('Delete') }}" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">{{ $backCharges->links() }}</div>
        </div>
    @else
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path></svg>
                @if($this->hasFilters())
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No back charges match these filters') }}</h3>
                    <div class="mt-6"><x-ui.button variant="secondary" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button></div>
                @else
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No back charges yet') }}</h3>
                    @can('back-charges.create')
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Record what a subcontractor owes — damage, rework, cleanup — and deduct it from their next payments.') }}</p>
                        <div class="mt-6"><x-ui.button variant="primary" wire:click="openBackChargeCreate" icon="plus">{{ __('New Back Charge') }}</x-ui.button></div>
                    @else
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing has been recorded yet.') }}</p>
                    @endcan
                @endif
            </div>
        </div>
    @endif

    @include('livewire.back-charge.partials.form-modal')
</div>
