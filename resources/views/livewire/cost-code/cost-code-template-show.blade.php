<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $template->name }}</h1>
                    @if($template->is_default)
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300">
                            {{ __('Default') }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $template->description ?? __('Manage cost codes for this template') }}
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('cost-codes.templates.index') }}"
                    icon="arrow-left">
                    {{ __('Back to Templates') }}
                </x-ui.button>
                @can('cost-codes.edit')
                    <x-ui.button
                        variant="primary"
                        href="{{ route('cost-codes.templates.edit', $template->id) }}"
                        icon="edit">
                        {{ __('Edit Template') }}
                    </x-ui.button>
                @endcan
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Error Message -->
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Cost Codes List -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Cost Codes') }}</h3>
                    <div class="flex items-center gap-2">
                        @can('cost-codes.create')
                        <x-ui.button
                            variant="secondary"
                            size="sm"
                            wire:click="openImportModal"
                            icon="upload">
                            {{ __('Import CSV') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="primary"
                            size="sm"
                            wire:click="openAddForm()"
                            icon="plus">
                            {{ __('Add Cost Code') }}
                        </x-ui.button>
                        @endcan
                    </div>
                </div>

                <div class="p-6">
                    @if($template->parentCostCodes->count() > 0)
                        <div class="space-y-3">
                            @foreach($template->parentCostCodes as $parentCode)
                                <!-- Parent Cost Code -->
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg">
                                    <div class="flex items-center justify-between px-4 py-3 bg-slate-50 dark:bg-slate-900/50 rounded-t-lg">
                                        <div class="flex items-center gap-3">
                                            <span class="px-2 py-1 text-xs font-mono font-semibold rounded bg-[#3F5189] text-white">
                                                {{ $parentCode->code }}
                                            </span>
                                            <div>
                                                <span class="font-medium text-slate-900 dark:text-white">{{ $parentCode->name }}</span>
                                                @if($parentCode->description)
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ Str::limit($parentCode->description, 60) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @can('cost-codes.create')
                                            <x-ui.button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="openAddForm({{ $parentCode->id }})"
                                                icon="plus"
                                                title="{{ __('Add child code') }}">
                                            </x-ui.button>
                                            @endcan
                                            @can('cost-codes.edit')
                                            <x-ui.button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="openEditForm({{ $parentCode->id }})"
                                                icon="edit"
                                                title="{{ __('Edit') }}">
                                            </x-ui.button>
                                            @endcan
                                            @if($parentCode->children->count() === 0)
                                                @can('cost-codes.delete')
                                                <x-ui.button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="deleteCostCode({{ $parentCode->id }})"
                                                    wire:confirm="{{ __('Are you sure you want to delete this cost code?') }}"
                                                    icon="trash"
                                                    title="{{ __('Delete') }}"
                                                    class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                                </x-ui.button>
                                                @endcan
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Child Cost Codes -->
                                    @if($parentCode->children->count() > 0)
                                        <div class="divide-y divide-slate-200 dark:divide-slate-700">
                                            @foreach($parentCode->children as $childCode)
                                                <div class="flex items-center justify-between px-4 py-3 pl-10 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                    <div class="flex items-center gap-3">
                                                        <span class="px-2 py-1 text-xs font-mono font-medium rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                                            {{ $childCode->code }}
                                                        </span>
                                                        <div>
                                                            <span class="text-sm text-slate-900 dark:text-white">{{ $childCode->name }}</span>
                                                            @if($childCode->description)
                                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ Str::limit($childCode->description, 50) }}</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        @can('cost-codes.edit')
                                                        <x-ui.button
                                                            variant="ghost"
                                                            size="sm"
                                                            wire:click="openEditForm({{ $childCode->id }})"
                                                            icon="edit"
                                                            title="{{ __('Edit') }}">
                                                        </x-ui.button>
                                                        @endcan
                                                        @can('cost-codes.delete')
                                                        <x-ui.button
                                                            variant="ghost"
                                                            size="sm"
                                                            wire:click="deleteCostCode({{ $childCode->id }})"
                                                            wire:confirm="{{ __('Are you sure you want to delete this cost code?') }}"
                                                            icon="trash"
                                                            title="{{ __('Delete') }}"
                                                            class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                                        </x-ui.button>
                                                        @endcan
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No cost codes yet') }}</h3>
                            @can('cost-codes.create')
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Get started by adding cost codes to this template.') }}</p>
                                <div class="mt-6">
                                    <x-ui.button
                                        variant="primary"
                                        wire:click="openAddForm()"
                                        icon="plus">
                                        {{ __('Add Cost Code') }}
                                    </x-ui.button>
                                </div>
                            @else
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('This template has no cost codes. You can see it but not build it — ask an administrator if that is wrong.') }}</p>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Template Info -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Template Information') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Template ID') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $template->id }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Total Cost Codes') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $template->costCodes->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Parent Codes') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $template->parentCostCodes->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Created') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $template->created_at->diffForHumans() }}</span>
                    </div>
                    @if($template->creator)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Created By') }}</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $template->creator->name }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    @if($showImportModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeImportModal"></div>

                <!-- Center spacer for sm:block -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal panel -->
                <div class="relative inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Import Cost Codes') }}</h3>
                        <button wire:click="closeImportModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <!-- Download Sample -->
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">
                                {{ __('CSV format:') }} <code class="text-xs bg-slate-200 dark:bg-slate-700 px-1 py-0.5 rounded">code, name, description, parent_code</code>
                            </p>
                            <x-ui.button
                                variant="ghost"
                                size="sm"
                                wire:click="downloadSampleCsv"
                                icon="download">
                                {{ __('Download Sample CSV') }}
                            </x-ui.button>
                        </div>

                        <!-- File Upload -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('CSV File') }} <span class="text-red-500">*</span>
                            </label>
                            <x-ui.file-drop
                                wire:model="importFile"
                                :multiple="false"
                                accept=".csv,.txt"
                                :label="__('Drop the CSV here, or')"
                                :hint="__('CSV or TXT, up to 1MB.')">

                                @error('importFile')
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                <div wire:loading wire:target="importFile" class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Processing file...') }}
                                </div>

                                @if($importFile)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                        <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                            {{ $importFile->getClientOriginalName() }}
                                        </span>
                                        <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                            {{ trans_choice(':count row read|:count rows read', count($importPreview), ['count' => count($importPreview)]) }}
                                        </span>
                                        <x-ui.icon-button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            type="button"
                                            wire:click="clearImportFile"
                                            title="{{ __('Remove :file', ['file' => $importFile->getClientOriginalName()]) }}"
                                            aria-label="{{ __('Remove :file', ['file' => $importFile->getClientOriginalName()]) }}"
                                            class="hover:text-red-600 dark:hover:text-red-400" />
                                    </div>
                                @endif
                            </x-ui.file-drop>
                        </div>

                        <!-- Import Mode -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Import Mode') }}
                            </label>
                            <select
                                wire:model="importMode"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="merge">{{ __('Merge (update existing, add new)') }}</option>
                                <option value="replace">{{ __('Replace (delete all existing first)') }}</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                @if($importMode === 'merge')
                                    {{ __('Existing codes will be updated, new codes will be added.') }}
                                @else
                                    {{ __('All existing cost codes will be deleted before import.') }}
                                @endif
                            </p>
                        </div>

                        <!-- Errors -->
                        @if(count($importErrors) > 0)
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <h4 class="font-medium text-red-800 dark:text-red-300 mb-2">{{ __('Errors Found:') }}</h4>
                                <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-400 space-y-1 max-h-32 overflow-y-auto">
                                    @foreach($importErrors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Preview -->
                        @if(count($importPreview) > 0 && count($importErrors) === 0)
                            <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <h4 class="font-medium text-green-800 dark:text-green-300 mb-2">
                                    {{ __('Preview: :count cost codes will be imported', ['count' => count($importPreview)]) }}
                                </h4>
                                <div class="max-h-48 overflow-y-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-xs text-slate-500 dark:text-slate-400">
                                                <th class="pb-2">{{ __('Code') }}</th>
                                                <th class="pb-2">{{ __('Name') }}</th>
                                                <th class="pb-2">{{ __('Parent') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-slate-700 dark:text-slate-300">
                                            @foreach($importPreview as $row)
                                                <tr>
                                                    <td class="py-1 font-mono text-xs">{{ $row['code'] }}</td>
                                                    <td class="py-1">{{ Str::limit($row['name'], 25) }}</td>
                                                    <td class="py-1 font-mono text-xs text-slate-500">{{ $row['parent_code'] ?: '-' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-end gap-3">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="closeImportModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        @php
                            $canImport = count($importErrors) === 0 && count($importPreview) > 0;
                        @endphp
                        <x-ui.button
                            type="button"
                            variant="primary"
                            wire:click="executeImport"
                            icon="check"
                            :disabled="!$canImport">
                            {{ __('Import :count Cost Codes', ['count' => count($importPreview)]) }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.cost-code.partials.code-modal')
</div>
