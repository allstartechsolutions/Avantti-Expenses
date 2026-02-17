<div>
    <!-- Change Orders Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Change Orders</h3>
            <x-ui.button variant="primary" size="sm" wire:click="openCreateModal" icon="plus">
                Add Change Order
            </x-ui.button>
        </div>

        <div class="p-6">
            @if($changeOrders->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Title</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Amount</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Created By</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">File</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($changeOrders as $co)
                                <tr>
                                    <td class="px-3 py-3 text-sm text-slate-900 dark:text-white">
                                        {{ $co->title }}
                                        @if($co->description)
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-xs">{{ $co->description }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                        {{ $co->date->format('M d, Y') }}
                                    </td>
                                    <td class="px-3 py-3 text-sm font-medium text-right whitespace-nowrap {{ $co->amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $co->amount >= 0 ? '+' : '' }}{{ Number::currency($co->amount, config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                        {{ $co->createdBy?->name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-center">
                                        @if($co->file_path)
                                            <a href="{{ route('files.show', ['path' => $co->file_path]) }}" target="_blank" class="text-[#3F5189] hover:text-[#2F3F6F]" title="View File">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                </svg>
                                            </a>
                                        @else
                                            <span class="text-slate-300 dark:text-slate-600">-</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end space-x-2">
                                            <x-ui.button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="openEditModal({{ $co->id }})"
                                                icon="edit">
                                            </x-ui.button>
                                            <x-ui.button
                                                variant="danger"
                                                size="sm"
                                                wire:click="delete({{ $co->id }})"
                                                wire:confirm="Are you sure you want to delete this change order?"
                                                icon="trash">
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 dark:border-slate-600">
                                <td colspan="2" class="px-3 py-3 text-sm font-semibold text-slate-900 dark:text-white">
                                    Total Change Orders
                                </td>
                                @php $total = $changeOrders->sum(fn($co) => $co->getRawOriginal('amount')) / 100; @endphp
                                <td class="px-3 py-3 text-sm font-bold text-right whitespace-nowrap {{ $total >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $total >= 0 ? '+' : '' }}{{ Number::currency($total, config('app.currency'), config('app.locale')) }}
                                </td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">No change orders recorded.</p>
            @endif
        </div>
    </div>

    <!-- Add/Edit Change Order Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                            {{ $editingId ? 'Edit Change Order' : 'Add Change Order' }}
                        </h2>
                        <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Title *</label>
                            <input
                                type="text"
                                wire:model="title"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Change order title">
                            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Date *</label>
                            <input
                                type="date"
                                wire:model="date"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Amount * <span class="text-xs font-normal text-slate-400">(use negative for deductions)</span></label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-500 sm:text-sm">$</span>
                                </div>
                                <input
                                    type="number"
                                    wire:model="amount"
                                    step="0.01"
                                    class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="0.00">
                            </div>
                            @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Description</label>
                            <textarea
                                wire:model="description"
                                rows="3"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Optional description..."></textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">File</label>
                            <input
                                type="file"
                                wire:model="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="w-full text-sm text-slate-500 dark:text-slate-400
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-md file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-[#3F5189] file:text-white
                                    hover:file:bg-[#2F3F6F]">
                            <p class="mt-1 text-xs text-slate-400">PDF, JPG, PNG up to 10MB</p>
                            @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeModal">
                            Cancel
                        </x-ui.button>
                        <x-ui.button type="button" variant="primary" wire:click="save">
                            {{ $editingId ? 'Update' : 'Save' }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
