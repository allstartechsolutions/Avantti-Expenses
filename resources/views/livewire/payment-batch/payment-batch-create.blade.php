<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('New Payment Batch') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Create a new batch to plan contract payments') }}</p>
            </div>
        </div>
    </div>

    <!-- Batch Details -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Batch Details') }}</h3>
        </div>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Batch Name') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="{{ __('e.g. March 2026 Payments') }}"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <!-- Payment Date -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        {{ __('Payment Date') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        wire:model="payment_date"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    @error('payment_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Notes') }}
                </label>
                <textarea
                    wire:model="notes"
                    rows="3"
                    placeholder="{{ __('Optional notes for this batch...') }}"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"></textarea>
                @error('notes') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    <!-- Contract Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mt-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contract Filters') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Pre-filter which contracts will be shown in this batch. Leave blank to show all.') }}</p>
        </div>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Client -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Client') }}</label>
                    <select wire:model.live="client_id"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">{{ __('All Clients') }}</option>
                        @foreach($this->clients as $client)
                            <option value="{{ $client->id }}">{{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Project -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Project') }}</label>
                    <select wire:model="project_id"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">{{ __('All Projects') }}</option>
                        @foreach($this->projects as $project)
                            <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Subcontractor -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Subcontractor') }}</label>
                    <select wire:model="subcontractor_id"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">{{ __('All Subcontractors') }}</option>
                        @foreach($this->subcontractors as $sub)
                            <option value="{{ $sub->id }}">{{ $sub->company_name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Project Manager -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Project Manager') }}</label>
                    <select wire:model="project_manager_id"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">{{ __('All Project Managers') }}</option>
                        @foreach($this->projectManagers as $pm)
                            <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Contract Status -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Contract Status') }}</label>
                    <select wire:model="contract_status_filter"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="completed">{{ __('Completed') }}</option>
                        <option value="partially_paid">{{ __('Partially Paid') }}</option>
                        <option value="paid">{{ __('Paid') }}</option>
                        <option value="cancelled">{{ __('Cancelled') }}</option>
                    </select>
                </div>

                <!-- Show Paid/Cancelled -->
                <div class="flex items-end pb-1">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="show_zero_balance" class="sr-only peer">
                        <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[#3F5189] dark:peer-focus:ring-[#4A5A96] rounded-full peer dark:bg-slate-600 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-slate-500 peer-checked:bg-[#3F5189]"></div>
                        <span class="ms-3 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Show Paid/Cancelled') }}</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center justify-end space-x-4 mt-6">
        <x-ui.button variant="secondary" href="{{ route('payment-batches.index') }}" icon="arrow-left">
            {{ __('Cancel') }}
        </x-ui.button>
        <x-ui.button variant="primary" wire:click="save" icon="save">
            {{ __('Create Batch') }}
        </x-ui.button>
    </div>
</div>
