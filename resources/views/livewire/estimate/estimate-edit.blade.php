<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Edit Estimate {{ $estimate->estimate_number }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Edit estimate details') }}</p>
            </div>
            <div>
                <x-ui.button variant="secondary" href="{{ route('estimates.show', $estimate) }}" icon="arrow-left">
                    {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <div class="space-y-8">
        <!-- Estimate Details Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Estimate Details') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Basic information about this estimate') }}</p>
            </div>
            <div class="p-6 space-y-6">
                <!-- Row 1: Client, Estimate Number -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Client Search -->
                    <div class="relative md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Client') }} <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="clientSearch"
                                placeholder="{{ __('Search client...') }}"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @if($client_id)
                                <button type="button" wire:click="clearClient" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        @if(!$client_id && strlen($clientSearch) >= 2)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                @foreach($clients as $client)
                                    <button type="button" wire:click="selectClient({{ $client->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $client->company_name }}</div>
                                        @if($client->contact_name)
                                            <div class="text-xs text-slate-500">{{ $client->contact_name }}</div>
                                        @endif
                                    </button>
                                @endforeach
                                <!-- Add New Client option -->
                                <button type="button" wire:click="openQuickAddClient" class="w-full px-4 py-2 text-left hover:bg-blue-50 dark:hover:bg-blue-900/20 border-t border-slate-200 dark:border-slate-700">
                                    <div class="flex items-center gap-2 text-sm font-medium text-[#3F5189]">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        {{ __('Add New Client') }}
                                    </div>
                                    @if($clientSearch)
                                        <div class="text-xs text-slate-500">Create "{{ $clientSearch }}"</div>
                                    @endif
                                </button>
                            </div>
                        @endif
                        @error('client_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Estimate Number -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Estimate Number') }}</label>
                        <input
                            type="text"
                            wire:model="estimate_number"
                            readonly
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300">
                    </div>
                </div>

                <!-- Row 2: Project, Job Site -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Project Search -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Project') }}</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="projectSearch"
                                placeholder="{{ $client_id ? 'Search project...' : 'Select a client first' }}"
                                {{ !$client_id ? 'disabled' : '' }}
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white {{ !$client_id ? 'bg-slate-100 dark:bg-slate-900 cursor-not-allowed' : '' }}">
                            @if($project_id)
                                <button type="button" wire:click="clearProject" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        @if($projects->count() > 0)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                @foreach($projects as $project)
                                    <button type="button" wire:click="selectProject({{ $project->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $project->project_name }}</div>
                                        @if($project->status)
                                            <div class="text-xs text-slate-500">{{ $project->status->label() }}</div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Job Site Search -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Job Site') }}</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="jobSiteSearch"
                                placeholder="{{ $project_id ? 'Search job site...' : 'Select a project first' }}"
                                {{ !$project_id ? 'disabled' : '' }}
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white {{ !$project_id ? 'bg-slate-100 dark:bg-slate-900 cursor-not-allowed' : '' }}">
                            @if($job_site_id)
                                <button type="button" wire:click="clearJobSite" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            @endif
                        </div>
                        @if($jobSites->count() > 0)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                @foreach($jobSites as $jobSite)
                                    <button type="button" wire:click="selectJobSite({{ $jobSite->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $jobSite->job_site_name }}</div>
                                        @if($jobSite->address)
                                            <div class="text-xs text-slate-500">{{ $jobSite->address }}</div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Row 3: Date, Terms, Due Date -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Estimate Date -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Estimate Date') }} <span class="text-red-500">*</span>
                        </label>
                        <x-ui.date-input wire:model.live="estimate_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                        @error('estimate_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Terms -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Terms') }} <span class="text-red-500">*</span>
                        </label>
                        <select
                            wire:model.live="terms"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="due_upon_receipt">{{ __('Due Upon Receipt') }}</option>
                            <option value="net_15">{{ __('Net 15') }}</option>
                            <option value="net_30">{{ __('Net 30') }}</option>
                            <option value="net_60">{{ __('Net 60') }}</option>
                            <option value="net_90">{{ __('Net 90') }}</option>
                        </select>
                        @error('terms') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Due Date') }}</label>
                        {{-- Derived from the payment terms, so it is read, not
                             typed. Shown the way this install writes dates
                             rather than the way the browser would. --}}
                        <p class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300">
                            {{ $due_date ? \Carbon\Carbon::parse($due_date)->appDate() : '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Items') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Add line items to this estimate') }}</p>
                    </div>
                    <x-ui.button type="button" variant="primary" icon="plus" wire:click="openAddItemModal">
                        {{ __('Add Item') }}
                    </x-ui.button>
                </div>
            </div>
            <div class="p-6">
                @error('items') <div class="text-red-500 text-sm mb-4">{{ $message }}</div> @enderror

                @if(count($items) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Item') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Qty') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Unit Price') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Discount') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Tax') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Total') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($items as $index => $item)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item['item_name'] }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ $item['item_type'] === 'catalog' ? 'From Catalog' : 'Custom' }}
                                                @if($item['unit']) &middot; {{ $item['unit'] }} @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-white text-right">{{ $item['quantity'] }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-white text-right">${{ number_format($item['unit_price'], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-white text-right">
                                            @if($item['discount_amount'] > 0)
                                                -${{ number_format($item['discount_amount'], 2) }}
                                                <div class="text-xs text-slate-500">
                                                    {{ $item['discount_type'] === 'percentage' ? $item['discount_value'] . '%' : '$' . number_format($item['discount_value'], 2) }}
                                                </div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-900 dark:text-white text-right">
                                            @if($item['is_taxable'] && $item['tax_amount'] > 0)
                                                ${{ number_format($item['tax_amount'], 2) }}
                                                <div class="text-xs text-slate-500">{{ number_format($item['tax_rate'] * 100, 2) }}%</div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white text-right">${{ number_format($item['total_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" wire:click="openEditItemModal({{ $index }})" class="text-[#3F5189] hover:text-[#2F3F6F] mr-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                            </button>
                                            <button type="button" wire:click="removeItem({{ $index }})" wire:confirm="{{ __('Remove this item?') }}" class="text-red-500 hover:text-red-700">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No items') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Add at least one item to this estimate.') }}</p>
                        <div class="mt-4">
                            <x-ui.button type="button" variant="primary" icon="plus" wire:click="openAddItemModal">
                                {{ __('Add Item') }}
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Totals Card -->
        @if(count($items) > 0)
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Totals') }}</h3>
            </div>
            <div class="p-6">
                <div class="max-w-md ml-auto space-y-4">
                    <!-- Subtotal -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ __('Subtotal') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($calc_subtotal, 2) }}</span>
                    </div>

                    <!-- Overall Discount -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-slate-600 dark:text-slate-400">{{ __('Discount') }}</span>
                            <select wire:model.live="overall_discount_type"
                                class="text-xs px-2 py-1 border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                <option value="">{{ __('None') }}</option>
                                <option value="percentage">%</option>
                                <option value="fixed">$</option>
                            </select>
                            @if($overall_discount_type)
                                <input type="number" step="0.01" min="0"
                                    wire:model.live.debounce.500ms="overall_discount_value"
                                    placeholder="{{ $overall_discount_type === 'percentage' ? '0.00%' : '0.00' }}"
                                    class="w-24 text-xs px-2 py-1 border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @endif
                        </div>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">
                            @if($calc_discount_amount > 0)
                                -${{ number_format($calc_discount_amount, 2) }}
                            @else
                                $0.00
                            @endif
                        </span>
                    </div>

                    <!-- Tax Total -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ __('Tax') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($calc_tax_total, 2) }}</span>
                    </div>

                    <!-- Grand Total -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-700">
                        <span class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Total') }}</span>
                        <span class="text-xl font-bold text-slate-900 dark:text-white">${{ number_format($calc_total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Message Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Message') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Message to include on the estimate') }}</p>
            </div>
            <div class="p-6 space-y-4">
                <!-- Message Template Selector -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Message Template') }}</label>
                    <select
                        wire:model.live="selectedMessageId"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">{{ __('— No message —') }}</option>
                        @foreach($documentMessages as $msg)
                            <option value="{{ $msg->id }}">
                                {{ $msg->title }}{{ $msg->is_default ? ' (Default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Message Body (TinyMCE) -->
                @if($selectedMessageId || $message_body)
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Message Body') }}</label>
                        <x-ui.tinymce-editor wireModel="message_body" id="estimate-edit-message-body" :height="200" modalName="estimate-edit" />
                    </div>
                @endif
            </div>
        </div>

        <!-- Notes Card -->
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Notes') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Internal notes (not shown on estimate)') }}</p>
            </div>
            <div class="p-6">
                <textarea
                    wire:model="notes"
                    rows="3"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                    placeholder="{{ __('Optional internal notes...') }}"></textarea>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end space-x-4">
            <x-ui.button type="button" variant="secondary" href="{{ route('estimates.show', $estimate) }}">
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button type="button" variant="primary" icon="save" wire:click="saveEstimate">
                {{ __('Save Estimate') }}
            </x-ui.button>
        </div>
    </div>

    <!-- Quick Add Client Modal -->
    <livewire:client.client-quick-create />

    <!-- Add/Edit Item Modal -->
    @if($showItemModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: true }" x-show="show">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeItemModal"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-lg shadow-xl" @click.stop>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                            {{ $editingItemIndex !== null ? 'Edit Item' : 'Add Item' }}
                        </h2>
                        <button type="button" wire:click="closeItemModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Item Type Toggle -->
                        <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-lg">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ $item_is_custom ? 'Custom Item' : 'From Catalog' }}
                            </span>
                            <button
                                type="button"
                                wire:click="toggleCustomItem"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $item_is_custom ? 'bg-[#3F5189]' : 'bg-slate-200' }}">
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $item_is_custom ? 'translate-x-5' : 'translate-x-0' }}"></span>
                            </button>
                        </div>

                        @if(!$item_is_custom)
                            <!-- Catalog Search -->
                            <div class="relative">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Search Catalog') }}</label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="catalogItemSearch"
                                        placeholder="{{ __('Type to search catalog...') }}"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @if($item_catalog_item_id)
                                        <button type="button" wire:click="clearCatalogItem" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                        </button>
                                    @endif
                                </div>
                                @if($catalogItems->count() > 0)
                                    <div class="absolute z-10 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-48 overflow-auto">
                                        @foreach($catalogItems as $ci)
                                            <button type="button" wire:click="selectCatalogItem({{ $ci->id }})" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $ci->name }}</div>
                                                <div class="text-xs text-slate-500">
                                                    ${{ number_format($ci->current_cost, 2) }} / {{ $ci->usage_unit ?? $ci->purchase_unit ?? __('unit') }}
                                                    @if($ci->is_taxable) &middot; Taxable{{ $ci->taxRate ? ' (' . $ci->taxRate->formatted_rate . ')' : '' }} @endif
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Item Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Item Name') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model="item_name"
                                placeholder="{{ __('Enter item name') }}"
                                {{ !$item_is_custom && $item_catalog_item_id ? 'readonly' : '' }}
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white {{ !$item_is_custom && $item_catalog_item_id ? 'bg-slate-100 dark:bg-slate-900' : '' }}">
                            @error('item_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Description') }}</label>
                            <textarea
                                wire:model="item_description"
                                rows="2"
                                placeholder="{{ __('Optional description...') }}"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"></textarea>
                        </div>

                        <!-- Quantity, Unit, Unit Price -->
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Quantity') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    wire:model.live="item_quantity"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('item_quantity') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Unit') }}</label>
                                <select
                                    wire:model="item_unit"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="unit">{{ __('Unit') }}</option>
                                    <option value="hour">{{ __('Hour') }}</option>
                                    <option value="day">{{ __('Day') }}</option>
                                    <option value="week">{{ __('Week') }}</option>
                                    <option value="month">{{ __('Month') }}</option>
                                    <option value="sqft">{{ __('Sq Ft') }}</option>
                                    <option value="lnft">{{ __('Ln Ft') }}</option>
                                    <option value="cuyd">{{ __('Cu Yd') }}</option>
                                    <option value="ton">{{ __('Ton') }}</option>
                                    <option value="load">{{ __('Load') }}</option>
                                    <option value="lot">{{ __('Lot') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    {{ __('Unit Price') }} <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2.5 text-slate-500">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        wire:model.live="item_unit_price"
                                        class="w-full pl-8 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                </div>
                                @error('item_unit_price') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Discount -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Discount') }}</label>
                            <div class="flex items-center gap-3">
                                <select wire:model.live="item_discount_type"
                                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                                    <option value="">{{ __('None') }}</option>
                                    <option value="percentage">%</option>
                                    <option value="fixed">{{ __('$ (Fixed)') }}</option>
                                </select>
                                @if($item_discount_type)
                                    <div class="relative flex-1">
                                        @if($item_discount_type === 'fixed')
                                            <span class="absolute left-3 top-2.5 text-slate-500">$</span>
                                        @endif
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            wire:model.live.debounce.500ms="item_discount_value"
                                            placeholder="{{ $item_discount_type === 'percentage' ? 'Percentage' : 'Amount' }}"
                                            class="w-full {{ $item_discount_type === 'fixed' ? 'pl-8' : 'pl-3' }} pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        @if($item_discount_type === 'percentage')
                                            <span class="absolute right-3 top-2.5 text-slate-500">%</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Tax -->
                        <div class="p-4 bg-slate-50 dark:bg-slate-900 rounded-lg space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Taxable') }}</span>
                                <button
                                    type="button"
                                    wire:click="$toggle('item_is_taxable')"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $item_is_taxable ? 'bg-[#3F5189]' : 'bg-slate-200' }}">
                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $item_is_taxable ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                            @if($item_is_taxable)
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Tax Rate') }}</label>
                                    <select wire:model.live="item_tax_rate"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm">
                                        <option value="0">{{ __('Select tax rate') }}</option>
                                        @foreach($taxRates as $rate)
                                            <option value="{{ $rate->rate }}">
                                                {{ $rate->state }} — {{ $rate->formatted_rate }}{{ $rate->is_default ? ' (Default)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>

                        <!-- Calculated Summary -->
                        <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">{{ __('Line Subtotal') }}</span>
                                <span class="text-slate-900 dark:text-white">${{ number_format($item_calc_subtotal, 2) }}</span>
                            </div>
                            @if($item_calc_discount > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-600 dark:text-slate-400">{{ __('Discount') }}</span>
                                    <span class="text-red-600 dark:text-red-400">-${{ number_format($item_calc_discount, 2) }}</span>
                                </div>
                            @endif
                            @if($item_calc_tax > 0)
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-600 dark:text-slate-400">{{ __('Tax') }}</span>
                                    <span class="text-slate-900 dark:text-white">${{ number_format($item_calc_tax, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-sm font-semibold pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span class="text-slate-900 dark:text-white">{{ __('Line Total') }}</span>
                                <span class="text-slate-900 dark:text-white">${{ number_format($item_calc_total, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeItemModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="primary" wire:click="saveItem">
                            {{ $editingItemIndex !== null ? 'Update Item' : 'Add Item' }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
