@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $select = 'block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $filtered = $this->hasFilters();
    $canDelete = auth()->user()->can('vendors.delete');
    $projectsOn = \App\Models\ModuleAccess::isEnabled('projects');
    $catalogOn = \App\Models\ModuleAccess::isEnabled('catalog');
@endphp
<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Vendors') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every company you buy from or subcontract to, in one list. A company can be both.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @can('vendors.merge')
                    <x-ui.button variant="secondary" href="{{ route('vendors.duplicates') }}">{{ __('Merge Duplicates') }}</x-ui.button>
                @endcan
                @can('vendors.create')
                    @if($catalogOn)
                        <x-ui.button variant="secondary" href="{{ route('suppliers.create') }}" icon="plus">{{ __('Add Supplier') }}</x-ui.button>
                    @endif
                    @if($projectsOn)
                        <x-ui.button variant="primary" href="{{ route('subcontractors.create') }}" icon="plus">{{ __('Add Subcontractor') }}</x-ui.button>
                    @endif
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

    <!-- Type cards double as filters -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach([
            '' => [__('All vendors'), $counts['all'], __('Suppliers and subcontractors')],
            'suppliers' => [__('Suppliers'), $counts['suppliers'], __('You buy from them')],
            'subcontractors' => [__('Subcontractors'), $counts['subcontractors'], __('They work on your projects')],
            'both' => [__('Both'), $counts['both'], __('Supply and subcontract')],
        ] as $key => [$label, $count, $hint])
            <button type="button" wire:click="$set('type', '{{ $key }}')"
                class="text-left bg-white dark:bg-slate-800 rounded-lg shadow-sm border p-5 transition {{ $type === $key ? 'border-[#3F5189] ring-2 ring-[#3F5189]/40' : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600' }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ $count }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
            </button>
        @endforeach
    </div>

    <!-- Search and filters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6 p-6">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <div class="md:col-span-6">
                <label for="search" class="sr-only">{{ __('Search vendors') }}</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" id="search" wire:model.live.debounce.300ms="search" class="{{ $select }} pl-10 placeholder-slate-400" placeholder="{{ __('Search by company, contact, email, phone or city...') }}">
                </div>
            </div>
            <div class="md:col-span-3">
                <label for="type" class="sr-only">{{ __('Type') }}</label>
                <select id="type" wire:model.live="type" class="{{ $select }}">
                    <option value="">{{ __('Type: all') }}</option>
                    <option value="suppliers">{{ __('Suppliers') }}</option>
                    <option value="subcontractors">{{ __('Subcontractors') }}</option>
                    <option value="both">{{ __('Both') }}</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="documentHealth" class="sr-only">{{ __('Filter by documents') }}</label>
                <select id="documentHealth" wire:model.live="documentHealth" class="{{ $select }}">
                    <option value="">{{ __('Documents: all') }}</option>
                    @foreach($healthOptions as $state => $label)
                        <option value="{{ $state }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-1 flex md:justify-end">
                @if($filtered)
                    <x-ui.button variant="secondary" wire:click="clearFilters" icon="x">{{ __('Clear') }}</x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Vendors table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($vendors->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900/50">
                        <tr>
                            <th class="{{ $th }}">{{ __('Company') }}</th>
                            <th class="{{ $th }}">{{ __('Contact') }}</th>
                            <th class="{{ $th }}">{{ __('Phone') }}</th>
                            <th class="{{ $th }}">{{ __('Location') }}</th>
                            <th class="{{ $th }}">{{ __('Linked Records') }}</th>
                            <th class="{{ $th }}">{{ __('Documents') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($vendors as $vendor)
                            @php
                                // A subcontractor's page is the richer one (documents, employees); a pure supplier goes to its own.
                                $showRoute = $vendor->is_subcontractor ? route('subcontractors.show', $vendor->id) : route('suppliers.show', $vendor->id);
                                $editRoute = $vendor->is_subcontractor ? route('subcontractors.edit', $vendor->id) : route('suppliers.edit', $vendor->id);
                                $linked = array_filter([
                                    __('Contracts') => $vendor->contracts_count,
                                    __('Batches') => $vendor->payment_batches_count,
                                    __('Employees') => $vendor->employees_count,
                                    __('Expenses') => $vendor->expenses_count,
                                    __('POs') => $vendor->purchase_orders_count,
                                    __('Catalog') => $vendor->catalog_items_count,
                                ]);
                                $blocked = ($vendor->is_supplier && $vendor->is_subcontractor)
                                    || $vendor->contracts_count || $vendor->payment_batches_count
                                    || $vendor->expenses_count || $vendor->purchase_orders_count || $vendor->catalog_items_count;
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center">
                                                <span class="text-sm font-medium text-white">{{ strtoupper(mb_substr($vendor->name, 0, 2)) }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4 min-w-0">
                                            <a href="{{ $showRoute }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $vendor->name }}</a>
                                            <div class="mt-0.5 flex flex-wrap gap-1">
                                                @if($vendor->is_supplier)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ __('Supplier') }}</span>
                                                @endif
                                                @if($vendor->is_subcontractor)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Subcontractor') }}</span>
                                                @endif
                                            </div>
                                            @if($vendor->website)
                                                <a href="{{ $vendor->website }}" target="_blank" rel="noopener" class="block text-xs text-slate-500 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96] truncate max-w-xs">{{ $vendor->website }}</a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-slate-900 dark:text-white">{{ $vendor->contact_name ?: '—' }}</div>
                                    @if($vendor->contact_email ?: $vendor->email)
                                        <a href="mailto:{{ $vendor->contact_email ?: $vendor->email }}" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $vendor->contact_email ?: $vendor->email }}</a>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">{{ \App\Models\Subcontractor::formatPhone($vendor->phone) ?: '—' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                    @if($vendor->city || $vendor->state)
                                        {{ $vendor->city }}{{ $vendor->city && $vendor->state ? ', ' : '' }}{{ $vendor->state }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                    @if(empty($linked))
                                        —
                                    @else
                                        @foreach($linked as $label => $count)
                                            <span class="inline-block mr-2 whitespace-nowrap">{{ $label }}: {{ $count }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($vendor->is_subcontractor)
                                        <x-vendor.document-health
                                            :state="$vendor->document_health"
                                            :expired="$vendor->expired_documents_count"
                                            :expiring="$vendor->expiring_documents_count"
                                            mode="full" />
                                    @else
                                        <span class="text-sm text-slate-500 dark:text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.view-edit-buttons :viewRoute="$showRoute" :editRoute="$editRoute" />
                                        @if($canDelete)
                                            @if($blocked)
                                                <span title="{{ ($vendor->is_supplier && $vendor->is_subcontractor) ? __('Both classifications: open the record to remove one.') : __('Linked records: merge into another vendor instead of deleting.') }}">
                                                    <x-ui.icon-button variant="danger" size="sm" icon="trash" disabled />
                                                </span>
                                            @else
                                                <x-ui.icon-button variant="danger" size="sm" icon="trash" title="{{ __('Delete') }}"
                                                    wire:click="deleteVendor({{ $vendor->id }})"
                                                    wire:confirm="{{ __('Delete :name? This cannot be undone.', ['name' => $vendor->name]) }}" />
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($vendors->hasPages())
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">{{ $vendors->links() }}</div>
            @endif
        @else
            <div class="text-center py-12 px-6">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                @if($filtered)
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No vendor matches these filters') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Try a different search, or clear the filters.') }}</p>
                    <div class="mt-6"><x-ui.button variant="secondary" wire:click="clearFilters" icon="x">{{ __('Clear Filters') }}</x-ui.button></div>
                @else
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No vendors yet') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Add the companies you buy from and the ones you subcontract to. A company can be both.') }}</p>
                    @can('vendors.create')
                        <div class="mt-6 flex flex-wrap justify-center gap-3">
                            @if($catalogOn)<x-ui.button variant="secondary" href="{{ route('suppliers.create') }}" icon="plus">{{ __('Add Supplier') }}</x-ui.button>@endif
                            @if($projectsOn)<x-ui.button variant="primary" href="{{ route('subcontractors.create') }}" icon="plus">{{ __('Add Subcontractor') }}</x-ui.button>@endif
                        </div>
                    @endcan
                @endif
            </div>
        @endif
    </div>
</div>
