{{-- Create or rename a folder. A small dialog: two fields, not real work. --}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
@endphp

<x-ui.modal name="document-folder-modal" maxWidth="lg" layer="top">
    <form wire:submit="saveFolder" class="p-6 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ $editingFolderId ? __('Rename Folder') : __('New Folder') }}
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $this->activeLocationName() }}@if($this->currentFolder && ! $editingFolderId) / {{ $this->currentFolder->name }} @endif
            </p>
        </div>

        <div>
            <label class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" wire:model="folderName" class="{{ $field }}" placeholder="{{ __('Drawings') }}" autofocus>
            @error('folderName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $label }}">{{ __('Description') }}</label>
            <textarea wire:model="folderDescription" rows="2" class="{{ $field }}" placeholder="{{ __('What belongs in this folder?') }}"></textarea>
            @error('folderDescription') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <x-ui.button type="button" variant="secondary" wire:click="closeFolderModal">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save">
                {{ $editingFolderId ? __('Save Changes') : __('Create Folder') }}
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
