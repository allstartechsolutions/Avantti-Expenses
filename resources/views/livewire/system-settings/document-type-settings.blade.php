<div>
    @php
        $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
        $canEdit = auth()->user()->can('settings.edit');
    @endphp

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

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Vendor Document Types') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('What a subcontractor can be asked to file. A type that requires an expiration date drives the badges and the reminder e-mails.') }}
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ trans_choice(':count active type|:count active types', $activeCount, ['count' => $activeCount]) }}
                &middot;
                {{ trans_choice(':count requires an expiration date|:count require an expiration date', $requiredCount, ['count' => $requiredCount]) }}
            </p>
        </div>
        @if($canEdit)
            <x-ui.button variant="primary" wire:click="create" icon="plus">{{ __('Add Document Type') }}</x-ui.button>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($types->isEmpty())
            <div class="text-center py-12">
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No document types yet') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Add the certificates and licences you ask subcontractors for. Until there is one, nothing can be filed on a vendor.') }}</p>
                @if($canEdit)
                    <div class="mt-6">
                        <x-ui.button variant="primary" wire:click="create" icon="plus">{{ __('Add Document Type') }}</x-ui.button>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Type') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Expiration') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Documents') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Order') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($types as $type)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 {{ $type->is_active ? '' : 'opacity-60' }}" wire:key="type-{{ $type->id }}">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ __($type->name) }}</div>
                                    @if($type->description)
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __($type->description) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($type->requires_expiration)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">{{ __('Required') }}</span>
                                    @else
                                        <span class="text-sm text-slate-400 dark:text-slate-500">{{ __('No expiration') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                    {{ trans_choice(':count document|:count documents', $type->documents_count, ['count' => $type->documents_count]) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $type->sort_order }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($type->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Active') }}</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('Retired') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    @if($canEdit)
                                        <div class="flex items-center justify-end gap-1">
                                            <x-ui.icon-button variant="ghost" size="sm" icon="edit" wire:click="edit({{ $type->id }})" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}" />
                                            <x-ui.button variant="ghost" size="sm" wire:click="toggleActive({{ $type->id }})">
                                                {{ $type->is_active ? __('Retire') : __('Reactivate') }}
                                            </x-ui.button>
                                            @if($type->documents_count === 0)
                                                <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="delete({{ $type->id }})" wire:confirm="{{ __('Delete this document type? Nothing has been filed under it.') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" class="hover:text-red-600 dark:hover:text-red-400" />
                                            @else
                                                <span class="inline-flex p-1.5 text-slate-300 dark:text-slate-600" title="{{ __('Cannot be deleted while documents are filed under it. Retire it instead.') }}">
                                                    <x-ui.icon name="trash" class="w-4 h-4" />
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($showFormModal)
        <x-ui.modal name="document-type-form-modal" :show="true" maxWidth="lg">
            <form wire:submit="save">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ $editingId ? __('Edit Document Type') : __('Add Document Type') }}
                    </h3>
                    <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="closeForm" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label for="dt-name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                        <input id="dt-name" type="text" wire:model="name" maxlength="100" class="{{ $field }}" placeholder="{{ __('e.g. General Liability Insurance') }}">
                        @error('name') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="dt-description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Description') }}</label>
                        <input id="dt-description" type="text" wire:model="description" maxlength="255" class="{{ $field }}" placeholder="{{ __('Shown under the name so people file the right thing') }}">
                        @error('description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="dt-sort" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Sort order') }}</label>
                            <input id="dt-sort" type="number" min="0" max="999" wire:model="sort_order" class="{{ $field }}">
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Lower comes first on the picker and on the vendor page.') }}</p>
                            @error('sort_order') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                        <div class="space-y-3 pt-1">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="requires_expiration" class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] dark:bg-slate-700">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('Requires an expiration date') }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Uploads must carry a date; the badges and reminders watch it.') }}</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" wire:model="is_active" class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] dark:bg-slate-700">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ __('Active') }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Offered on the upload picker. Retired types keep their documents.') }}</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="closeForm" icon="x">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save">{{ __('Save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
