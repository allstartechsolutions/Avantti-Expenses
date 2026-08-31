<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Edit Contract') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $contract->contract_number }} &mdash;
                    {{ $contract->project->project_name }}
                    @if($contract->jobSite)
                        / {{ $contract->jobSite->job_site_name }}
                    @endif
                </p>
            </div>
            <div>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('contracts.show', $contract->id) }}"
                    icon="arrow-left">
                    {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Edit Form -->
    <form wire:submit="save" class="space-y-8">
        <!-- Contract Details Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contract Details') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Basic information about this contract') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Subcontractor Search -->
                    <div class="relative" x-data="{ open: false }" @click.away="open = false">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Subcontractor') }}</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="subcontractorSearch"
                                @focus="open = true"
                                @input="open = true"
                                placeholder="{{ __('Search subcontractor...') }}"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @if($subcontractor_id)
                                <button type="button" wire:click="clearSubcontractor" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        @if($subcontractors->count() > 0)
                            <div x-show="open" class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                @foreach($subcontractors as $sub)
                                    <button type="button" wire:click="selectSubcontractor({{ $sub->id }})" @click="open = false" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $sub->company_name }}</div>
                                        @if($sub->contact_name)
                                            <div class="text-xs text-slate-500">{{ $sub->contact_name }}</div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Contact (Employee) -->
                    @if($subcontractor_id)
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Contact (Employee)') }}</label>
                            <select
                                wire:model="subcontractor_employee_id"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="">{{ __('No specific contact') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}@if($employee->title) — {{ $employee->title }}@endif</option>
                                @endforeach
                            </select>
                            @error('subcontractor_employee_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Location') }}</label>
                        <select
                            wire:model="job_site_id"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="">{{ __('Project (General)') }}</option>
                            @foreach($jobSites as $js)
                                <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Start Date') }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            wire:model="start_date"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        @error('start_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- End Date -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('End Date') }}</label>
                        <input
                            type="date"
                            wire:model="end_date"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        @error('end_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Financial') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Contract value') }}</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-2xl">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Amount') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-slate-500">$</span>
                            <input
                                type="number"
                                step="0.01"
                                wire:model="amount"
                                placeholder="0.00"
                                class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        </div>
                        @error('amount') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Retention (%)') }}</label>
                        <div class="relative">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="50"
                                wire:model="retention_percent"
                                placeholder="0.00"
                                class="w-full pl-3 pr-8 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <span class="absolute right-3 top-2.5 text-slate-500">%</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Applies to measurements approved from now on — already approved measurements keep the percent in force at their approval.') }}</p>
                        @error('retention_percent') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        @include('livewire.contract.partials.allocation-editor')

        <!-- Additional Info Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Additional Information') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Notes and attachments') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Notes') }}</label>
                    <textarea
                        wire:model="notes"
                        rows="3"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                        placeholder="{{ __('Optional notes about this contract...') }}"></textarea>
                </div>

                <!-- Existing File -->
                @if($existingFilePath)
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Current File') }}</label>
                        <div class="flex items-center space-x-4 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg">
                            <a href="{{ route('files.show', ['path' => $existingFilePath]) }}" target="_blank" class="inline-flex items-center text-sm text-[#3F5189] hover:text-[#2F3F6F]">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                {{ __('View Current File') }}
                            </a>
                            <x-ui.button
                                type="button"
                                variant="danger"
                                size="sm"
                                wire:click="removeExistingFile"
                                wire:confirm="{{ __('Remove the current file?') }}"
                                icon="trash">
                                {{ __('Remove') }}
                            </x-ui.button>
                        </div>
                    </div>
                @endif

                <!-- Contract File Upload -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ $existingFilePath ? __('Replace File') : __('Contract File') }}
                    </label>
                    <x-ui.file-drop
                        wire:model="contract_file"
                        :multiple="false"
                        accept=".pdf,.jpg,.jpeg,.png"
                        :label="$existingFilePath ? __('Drop the replacement here, or') : __('Drop the contract here, or')"
                        :hint="__('PDF, JPG or PNG, up to 10MB.')">

                        @error('contract_file') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                        @if($contract_file)
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                    {{ $contract_file->getClientOriginalName() }}
                                </span>
                                <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                    {{ \App\Services\DocumentSettings::formatBytes($contract_file->getSize()) }}
                                </span>
                                <x-ui.icon-button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    type="button"
                                    wire:click="clearContractFile"
                                    title="{{ __('Remove :file', ['file' => $contract_file->getClientOriginalName()]) }}"
                                    aria-label="{{ __('Remove :file', ['file' => $contract_file->getClientOriginalName()]) }}"
                                    class="hover:text-red-600 dark:hover:text-red-400" />
                            </div>

                            @if($existingFilePath)
                                <p class="text-xs text-amber-600 dark:text-amber-400">
                                    {{ __('This replaces the file already on the contract when you save.') }}
                                </p>
                            @endif
                        @endif
                    </x-ui.file-drop>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4">
            <x-ui.button
                type="button"
                variant="secondary"
                href="{{ route('contracts.show', $contract->id) }}">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save">
                {{ __('Update Contract') }}
            </x-ui.button>
        </div>
    </form>
</div>
