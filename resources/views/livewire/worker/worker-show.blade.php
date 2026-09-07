@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $canLink = auth()->user()->can('workers.link');
    $current = $employees->filter->isCurrent();
    $statusBadge = fn ($status) => match ($status) {
        'active' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'completed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        'partially_paid' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
    };
@endphp
<div>
    <x-ui.breadcrumb :items="[
        ['label' => __('Vendors'), 'url' => route('vendors.index')],
        ['label' => __('Workers'), 'url' => route('workers.index')],
        ['label' => $worker->name],
    ]" />

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $worker->name }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ trans_choice('Known at :count company|Known at :count companies', $employees->count(), ['count' => $employees->count()]) }}
                    @if($current->isNotEmpty())
                        · {{ __('currently at :companies', ['companies' => $current->map(fn ($e) => $e->subcontractor?->company_name)->filter()->join(', ')]) }}
                    @endif
                </p>
                @if($worker->notes)
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $worker->notes }}</p>
                @endif
            </div>
            <div class="flex items-center space-x-3 shrink-0">
                <x-ui.button variant="secondary" href="{{ route('workers.index') }}" icon="arrow-left">{{ __('Back') }}</x-ui.button>
                @if($canLink)
                    <x-ui.button variant="primary" wire:click="startEdit" icon="edit">{{ __('Edit') }}</x-ui.button>
                @endif
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Companies') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $employees->count() }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count current|:count current', $current->count(), ['count' => $current->count()]) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Tax IDs presented') }}</p>
            <p class="mt-1 text-2xl font-semibold {{ $taxIds->count() > 1 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ $taxIds->count() }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                @if($taxIds->count() > 1)
                    {{ __('Different numbers at different companies') }}
                @elseif($taxIds->count() === 1)
                    {{ __('The same number everywhere it was given') }}
                @else
                    {{ __('None recorded yet') }}
                @endif
            </p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Contracts') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $contractTotals['count'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count still open|:count still open', $contractTotals['open'], ['count' => $contractTotals['open']]) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Contract value') }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white"><x-ui.money :amount="$contractTotals['adjusted']" rollup :visible="$canSeeMoney" /></p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Paid') }}: <x-ui.money :amount="$contractTotals['paid']" rollup :visible="$canSeeMoney" /></p>
        </div>
    </div>

    <!-- Companies -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Companies') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('One record per company, exactly as it was given there. Nothing here is merged.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th class="{{ $th }}">{{ __('Company') }}</th>
                        <th class="{{ $th }}">{{ __('Name as given') }}</th>
                        <th class="{{ $th }}">{{ __('Title') }}</th>
                        <th class="{{ $th }}">{{ __('Tax ID') }}</th>
                        <th class="{{ $th }}">{{ __('Phone') }}</th>
                        <th class="{{ $th }}">{{ __('Email') }}</th>
                        <th class="{{ $th }}">{{ __('Period') }}</th>
                        <th class="{{ $th }}">{{ __('Linked') }}</th>
                        <th class="{{ $th }}">{{ __('Notes') }}</th>
                        @if($canLink)<th class="{{ $th }} text-right">{{ __('Actions') }}</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($employees as $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-6 py-4 text-sm">
                                @if($row->subcontractor)
                                    <a href="{{ route('subcontractors.show', $row->subcontractor) }}" class="font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $row->subcontractor->company_name }}</a>
                                @else
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('Deleted company') }}</span>
                                @endif
                                <span class="block mt-1">
                                    @if($row->isCurrent())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Current') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Former') }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">{{ $row->name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ $row->title ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm font-mono whitespace-nowrap">
                                @if($row->tax_id)
                                    <span class="{{ $taxIds->count() > 1 ? 'text-amber-700 dark:text-amber-300' : 'text-slate-900 dark:text-white' }}">{{ $row->tax_id }}</span>
                                @else
                                    <span class="text-slate-500 dark:text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                @if($row->phone)<a href="tel:{{ $row->phone }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $row->formatted_phone }}</a>@else<span class="text-slate-500 dark:text-slate-400">—</span>@endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($row->email)<a href="mailto:{{ $row->email }}" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $row->email }}</a>@else<span class="text-slate-500 dark:text-slate-400">—</span>@endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                @if($row->started_at || $row->ended_at)
                                    {{ $row->started_at?->appDate() ?? '…' }} – {{ $row->ended_at?->appDate() ?? __('present') }}
                                @else
                                    <span class="text-slate-500 dark:text-slate-400">{{ __('Not recorded') }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                @if($row->linked_at)
                                    {{ $row->linked_at->appDateTime() }}
                                    @if($row->linkedBy)<span class="block text-xs">{{ __('by :name', ['name' => $row->linkedBy->name]) }}</span>@endif
                                    @if($row->link_reason)<span class="block text-xs italic">“{{ $row->link_reason }}”</span>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400" @if($row->notes) title="{{ $row->notes }}" @endif>{{ $row->notes ? \Illuminate\Support\Str::limit($row->notes, 40) : '—' }}</td>
                            @if($canLink)
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    @if($employees->count() > 1)
                                    <x-ui.button variant="ghost" size="sm" wire:click="unlinkEmployee({{ $row->id }})" wire:confirm="{{ __('Unlink this record from the worker? It becomes a worker of its own; the record itself is kept on its company\'s page.') }}">{{ __('Unlink') }}</x-ui.button>
                                    @else
                                        <a href="{{ route('subcontractors.show', ['subcontractor' => $row->subcontractor_id, 'tab' => 'employees']) }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('Link from the company page') }}</a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Contracts -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contracts') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Every contract where one of these records was the contact, whichever company it was through.') }}
                @if($confined)
                    {{ __('Only contracts on the projects and job sites you belong to are shown.') }}
                @endif
            </p>
        </div>
        @if($contracts->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="{{ $th }}">{{ __('Contract') }}</th>
                            <th class="{{ $th }}">{{ __('Project') }}</th>
                            <th class="{{ $th }}">{{ __('Location') }}</th>
                            <th class="{{ $th }}">{{ __('Through') }}</th>
                            <th class="{{ $th }}">{{ __('Status') }}</th>
                            <th class="{{ $th }}">{{ __('Start') }}</th>
                            <th class="{{ $th }}">{{ __('End') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Amount') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Paid') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($contracts as $contract)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 text-sm whitespace-nowrap">
                                    <a href="{{ route('contracts.show', $contract) }}" class="font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $contract->contract_number }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">{{ $contract->project?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ $contract->jobSite?->name ?? __('Project (General)') }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                    {{ $contract->subcontractor?->company_name ?? '—' }}
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('as :name', ['name' => $contract->subcontractorEmployee?->name]) }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge($contract->status) }}">{{ $contract->getStatusLabel() }}</span></td>
                                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $contract->start_date?->appDate() ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $contract->end_date?->appDate() ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-right text-slate-900 dark:text-white whitespace-nowrap"><x-ui.money :amount="$contract->getAdjustedAmount()" :scope="$contract->jobSite ?? $contract->project" /></td>
                                <td class="px-6 py-4 text-sm text-right text-slate-900 dark:text-white whitespace-nowrap"><x-ui.money :amount="$contract->getAmountPaid()" :scope="$contract->jobSite ?? $contract->project" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <td colspan="7" class="px-6 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ trans_choice('Total of :count contract|Total of :count contracts', $contractTotals['count'], ['count' => $contractTotals['count']]) }}</td>
                            <td class="px-6 py-3 text-sm font-semibold text-right text-slate-900 dark:text-white whitespace-nowrap"><x-ui.money :amount="$contractTotals['adjusted']" rollup :visible="$canSeeMoney" /></td>
                            <td class="px-6 py-3 text-sm font-semibold text-right text-slate-900 dark:text-white whitespace-nowrap"><x-ui.money :amount="$contractTotals['paid']" rollup :visible="$canSeeMoney" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="text-center py-12 px-6">
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No contracts yet') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('None of this worker\'s records is the contact on a contract. Choose them under Contact when creating or editing a contract and it will appear here.') }}</p>
            </div>
        @endif
    </div>

    <!-- Audit -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Record') }}</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-slate-500 dark:text-slate-400">{{ __('Created') }}</dt>
                <dd class="text-slate-900 dark:text-white">{{ $worker->created_at->appDateTime() }}@if($worker->createdBy) · {{ $worker->createdBy->name }}@endif</dd>
            </div>
            <div>
                <dt class="text-slate-500 dark:text-slate-400">{{ __('Last updated') }}</dt>
                <dd class="text-slate-900 dark:text-white">{{ $worker->updated_at->appDateTime() }}</dd>
            </div>
            <div>
                <dt class="text-slate-500 dark:text-slate-400">{{ __('How this record works') }}</dt>
                <dd class="text-slate-900 dark:text-white">{{ __('One worker, one record per company. Link a record from another company to bring it in; unlink one and it becomes a worker of its own.') }}</dd>
            </div>
        </dl>
    </div>

    <!-- Edit dialog -->
    <x-ui.modal name="edit-worker-modal" maxWidth="lg">
        <form wire:submit="saveWorker" class="p-6 space-y-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Edit Worker') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('The name and notes of the worker. Each company\'s own record keeps the name and details given there.') }}</p>
            <div>
                <label for="worker_name" class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input type="text" id="worker_name" wire:model="worker_name" class="{{ $field }}">
                @error('worker_name') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="worker_notes" class="{{ $label }}">{{ __('Notes') }}</label>
                <textarea id="worker_notes" wire:model="worker_notes" rows="4" class="{{ $field }}" placeholder="{{ __('What is known about this worker regardless of the company…') }}"></textarea>
                @error('worker_notes') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <x-ui.button type="button" variant="secondary" wire:click="cancelEdit">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="save">{{ __('Save Changes') }}</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
