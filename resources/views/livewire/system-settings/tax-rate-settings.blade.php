<div>
    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Header with Add Button -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Tax Rates</h2>
        <x-ui.button
            variant="primary"
            wire:click="create"
            icon="plus">
            Add Tax Rate
        </x-ui.button>
    </div>

    <!-- Tax Rates Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($taxRates->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                State
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Rate
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Default
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($taxRates as $taxRate)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $taxRate->state }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">{{ $taxRate->formatted_rate }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($taxRate->is_default)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300">
                                            Default
                                        </span>
                                    @else
                                        <span class="text-sm text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="edit({{ $taxRate->id }})"
                                            icon="edit">
                                            Edit
                                        </x-ui.button>
                                        <x-ui.button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="viewHistory({{ $taxRate->id }})">
                                            History
                                        </x-ui.button>
                                        <x-ui.button
                                            variant="danger"
                                            size="sm"
                                            wire:click="confirmDelete({{ $taxRate->id }})"
                                            icon="trash">
                                            Delete
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No tax rates yet</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by adding a tax rate.</p>
                <div class="mt-6">
                    <x-ui.button
                        variant="primary"
                        wire:click="create"
                        icon="plus">
                        Add Tax Rate
                    </x-ui.button>
                </div>
            </div>
        @endif
    </div>

    <!-- Form Modal (Create/Edit) -->
    @if($showFormModal)
        <x-ui.modal name="tax-rate-form-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-6">
                    {{ $editingTaxRateId ? 'Edit Tax Rate' : 'Add Tax Rate' }}
                </h3>

                <form wire:submit="save">
                    <div class="space-y-4">
                        <!-- State -->
                        <div>
                            <label for="state" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                State <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="state"
                                wire:model="state"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]"
                                placeholder="e.g., TX, CA, NY">
                            @error('state')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Rate -->
                        <div>
                            <label for="rate" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Rate (%) <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="rate"
                                wire:model="rate"
                                step="0.01"
                                min="0"
                                max="100"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]"
                                placeholder="e.g., 8.25">
                            @error('rate')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Default Toggle -->
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                id="is_default"
                                wire:model="is_default"
                                class="h-4 w-4 text-[#3F5189] focus:ring-[#3F5189] border-slate-300 dark:border-slate-600 rounded">
                            <label for="is_default" class="ml-2 block text-sm text-slate-700 dark:text-slate-300">
                                Set as default tax rate
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <x-ui.button
                            variant="secondary"
                            type="button"
                            wire:click="closeFormModal"
                            icon="x">
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            variant="primary"
                            type="submit"
                            icon="save">
                            {{ $editingTaxRateId ? 'Update' : 'Create' }}
                        </x-ui.button>
                    </div>
                </form>
            </div>
        </x-ui.modal>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <x-ui.modal name="tax-rate-delete-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    Delete Tax Rate
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    Are you sure you want to delete the tax rate for <strong>{{ $deleteTaxRateData['state'] ?? '' }}</strong> ({{ $deleteTaxRateData['rate'] ?? '' }})?
                    This action <strong>cannot be undone</strong>.
                </p>

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDelete"
                        icon="x">
                        Cancel
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="delete"
                        icon="trash">
                        Delete Tax Rate
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif

    <!-- History Modal -->
    @if($showHistoryModal)
        <x-ui.modal name="tax-rate-history-modal" :show="true" maxWidth="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">
                    Change History
                </h3>

                @if(count($historyEntries) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Action</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Field</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Old Value</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">New Value</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Changed By</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($historyEntries as $entry)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @if($entry['action'] === 'created')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300">Created</span>
                                            @elseif($entry['action'] === 'updated')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">Updated</span>
                                            @elseif($entry['action'] === 'deleted')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300">Deleted</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                            {{ $entry['field_changed'] ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                            {{ $entry['old_value'] ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                            {{ $entry['new_value'] ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                            {{ $entry['changed_by'] }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                            {{ $entry['created_at'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">No history entries found.</p>
                @endif

                <div class="flex justify-end mt-4">
                    <x-ui.button
                        variant="secondary"
                        wire:click="closeHistory"
                        icon="x">
                        Close
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
