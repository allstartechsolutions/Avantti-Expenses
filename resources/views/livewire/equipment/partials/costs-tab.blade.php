@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $money = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
@endphp
<div class="space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-5 text-white">
            <p class="text-sm font-medium text-white/80">{{ __('Total cost of ownership') }}</p>
            <x-ui.money class="block text-2xl font-bold mt-1" :amount="$costs['tco']" rollup />
            <p class="mt-1 text-xs text-white/80">{{ __('Purchase plus every tagged expense you may see') }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Purchase cost') }}</p>
            @if($equipment->purchase_cost === null)<p class="mt-1 text-2xl font-bold text-slate-400">—</p>@else<x-ui.money class="block text-2xl font-bold mt-1 text-slate-900 dark:text-white" :amount="$costs['purchase']" rollup />@endif
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Tagged expenses') }}</p>
            <x-ui.money class="block text-2xl font-bold mt-1 text-slate-900 dark:text-white" :amount="$costs['total']" rollup />
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ trans_choice(':count expense|:count expenses', $costs['expenses']->count(), ['count' => $costs['expenses']->count()]) }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Not tied to a maintenance') }}</p>
            <x-ui.money class="block text-2xl font-bold mt-1 text-slate-900 dark:text-white" :amount="$costs['untagged']" rollup />
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Fuel, insurance, tolls…') }}</p>
        </div>
    </div>

    @if($costs['canFileCompany'] && ! $equipment->isRetired())
        <div class="flex justify-end">
            <x-ui.button variant="primary" icon="plus" href="{{ route('company-expenses.create', ['equipment' => $equipment->id]) }}">{{ __('Add a company expense for this equipment') }}</x-ui.button>
        </div>
    @endif

    @if($costs['expenses']->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
            <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No expense tagged to this equipment yet') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('When filing an expense — on a project or the company\'s — pick this equipment in the form and it shows here. A confined reader sees only the projects they are on.') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @foreach([['title' => __('By project or category'), 'rows' => $costs['byBucket'], 'key' => 'label'], ['title' => __('By month'), 'rows' => $costs['byMonth'], 'key' => 'month'], ['title' => __('By maintenance'), 'rows' => $costs['byMaintenance'], 'key' => 'title']] as $panel)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $panel['title'] }}</h3></div>
                    @if($panel['rows']->isEmpty())
                        <p class="p-6 text-sm text-slate-500 dark:text-slate-400">—</p>
                    @else
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @foreach($panel['rows'] as $row)
                                <div class="px-6 py-2.5 flex items-center justify-between gap-4 text-sm">
                                    <span class="min-w-0 truncate text-slate-700 dark:text-slate-300">
                                        {{ $panel['key'] === 'month' ? \Carbon\Carbon::createFromFormat('Y-m', $row['month'])->translatedFormat('M Y') : $row[$panel['key']] }}
                                        <span class="text-xs text-slate-400">({{ $row['count'] }})</span>
                                    </span>
                                    <x-ui.money class="shrink-0 font-medium text-slate-900 dark:text-white" :amount="$row['total']" rollup />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Every tagged expense') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th scope="col" class="{{ $th }}">{{ __('Date') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Filed under') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Vendor / Items') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Maintenance') }}</th>
                            <th scope="col" class="{{ $th }}">{{ __('Status') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($costs['expenses'] as $e)
                            <tr wire:key="cost-{{ $e->id }}">
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $e->expense_date->appDate() }}</td>
                                <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300">
                                    @if($e->isCompanyLevel())
                                        {{ __('Company (general)') }}<div class="text-xs text-slate-500 dark:text-slate-400">{{ $e->category?->getDisplayLabel() }}</div>
                                    @else
                                        {{ $e->project?->project_name }}@if($e->jobSite)<div class="text-xs text-slate-500 dark:text-slate-400">{{ $e->jobSite->job_site_name }}</div>@endif
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300">
                                    {{ $e->supplier?->name ?? __('No Supplier') }}
                                    @if($e->items->isNotEmpty())<div class="text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($e->items->pluck('item_name')->implode(', '), 60) }}</div>@elseif($e->item_name)<div class="text-xs text-slate-500 dark:text-slate-400">{{ $e->item_name }}</div>@endif
                                </td>
                                <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300">{{ $e->equipmentMaintenance?->title ?? '—' }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $e->getStatusLabel() }}</td>
                                <td class="px-6 py-3 whitespace-nowrap text-right"><x-ui.money class="text-sm font-medium text-slate-900 dark:text-white" :amount="$e->total_amount" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
