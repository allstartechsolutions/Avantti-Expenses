<div>
    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Module Access</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Enable or disable modules to customize what's available in the system.</p>
        </div>
    </div>

    <!-- Module Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($modules as $module)
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex-1 mr-4">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ $module->module_name }}
                        </h3>
                        @if($module->description)
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                                {{ $module->description }}
                            </p>
                        @endif
                    </div>

                    <!-- Toggle Switch -->
                    <button
                        wire:click="toggle({{ $module->id }})"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 {{ $module->is_enabled ? 'bg-[#3F5189]' : 'bg-slate-200 dark:bg-slate-600' }}"
                        role="switch"
                        aria-checked="{{ $module->is_enabled ? 'true' : 'false' }}">
                        <span
                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $module->is_enabled ? 'translate-x-5' : 'translate-x-0' }}">
                        </span>
                    </button>
                </div>

                <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $module->is_enabled ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400' }}">
                        {{ $module->is_enabled ? 'Enabled' : 'Disabled' }}
                    </span>

                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        wire:click="viewHistory({{ $module->id }})">
                        History
                    </x-ui.button>
                </div>
            </div>
        @endforeach
    </div>

    @if($modules->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No configurable modules</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">All modules are core and cannot be toggled.</p>
            </div>
        </div>
    @endif

    <!-- History Modal -->
    @if($showHistoryModal)
        <x-ui.modal name="module-access-history-modal" :show="true" maxWidth="2xl">
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
