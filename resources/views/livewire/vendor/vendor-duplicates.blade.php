<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Merge Duplicate Companies') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Combine records that refer to the same company. The kept record receives all expenses, purchase orders, contracts, documents and employees of the merged one.') }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button variant="secondary" href="{{ route('suppliers.index') }}" icon="arrow-left">{{ __('Suppliers') }}</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('subcontractors.index') }}" icon="arrow-left">{{ __('Subcontractors') }}</x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Possible duplicates -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-3">{{ __('Possible duplicates') }}</h2>

        @if($duplicateGroups->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-8 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No companies with matching names were found. You can still merge any two records manually below.') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($duplicateGroups as $group)
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Company') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Type') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contact') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Linked Records') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Created') }}</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($group as $vendor)
                                        <tr>
                                            <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">{{ $vendor->name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($vendor->is_supplier)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ __('Supplier') }}</span>
                                                @endif
                                                @if($vendor->is_subcontractor)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Subcontractor') }}</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                                {{ $vendor->email ?: $vendor->contact_email ?: '-' }}
                                                @if($vendor->phone)<span class="block text-xs">{{ $vendor->phone }}</span>@endif
                                            </td>
                                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                                @php
                                                    $counts = array_filter([
                                                        __('Expenses') => $vendor->expenses_count,
                                                        __('POs') => $vendor->purchase_orders_count,
                                                        __('Catalog') => $vendor->catalog_items_count,
                                                        __('Contracts') => $vendor->contracts_count,
                                                        __('Batches') => $vendor->payment_batches_count,
                                                        __('Documents') => $vendor->documents_count,
                                                        __('Employees') => $vendor->employees_count,
                                                    ]);
                                                @endphp
                                                @if(empty($counts))
                                                    -
                                                @else
                                                    @foreach($counts as $label => $count)
                                                        <span class="inline-block mr-2 whitespace-nowrap">{{ $label }}: {{ $count }}</span>
                                                    @endforeach
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $vendor->created_at->appDate() }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <x-ui.button
                                                    variant="primary"
                                                    size="sm"
                                                    wire:click="mergeGroup({{ $vendor->id }}, {{ json_encode($group->pluck('id')->all()) }})"
                                                    wire:confirm="{{ __('Keep this record and merge the other matching records into it? This cannot be undone.') }}">
                                                    {{ __('Keep this one') }}
                                                </x-ui.button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Manual merge -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-1">{{ __('Manual merge') }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ __('For duplicates written differently (e.g. abbreviations), pick the record to keep and the record to merge into it.') }}</p>

        <div class="flex flex-col md:flex-row md:items-end gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Keep this company') }}</label>
                <select wire:model="keepVendorId" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach($allVendors as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                    @endforeach
                </select>
                @error('keepVendorId') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Merge this company into it') }}</label>
                <select wire:model="mergeVendorId" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach($allVendors as $vendor)
                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                    @endforeach
                </select>
                @error('mergeVendorId') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-ui.button
                    variant="primary"
                    wire:click="mergeManual"
                    wire:confirm="{{ __('Merge these two records? All linked history moves to the kept company. This cannot be undone.') }}">
                    {{ __('Merge') }}
                </x-ui.button>
            </div>
        </div>
    </div>
</div>
