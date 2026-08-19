{{-- Rename, move, recategorise and tag one document.
     Expects: $jobSites (project level only). --}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $document = $editingDocumentId ? \App\Models\Document::with('currentVersion', 'uploadedBy')->find($editingDocumentId) : null;
@endphp

<x-ui.modal name="document-edit-modal" maxWidth="3xl" layer="top">
    <form wire:submit="saveDocument" class="p-6 space-y-5">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Edit Document') }}</h2>
            @if($document)
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $document->formattedSize() }} ·
                    {{ __('version :number', ['number' => $document->current_version_number]) }} ·
                    {{ __('uploaded by :name on :date', [
                        'name' => $document->uploadedBy?->name ?? __('unknown'),
                        'date' => $document->created_at->format('d/m/Y'),
                    ]) }}
                </p>
            @endif
        </div>

        <div>
            <label class="{{ $label }}">{{ __('Name') }} <span class="text-red-500">*</span></label>
            <input type="text" wire:model="documentName" class="{{ $field }}">
            @error('documentName') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="{{ $label }}">{{ __('Description') }}</label>
            <textarea wire:model="documentDescription" rows="2" class="{{ $field }}"></textarea>
            @error('documentDescription') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="{{ $label }}">{{ __('Category') }}</label>
                <select wire:model="documentCategory" class="{{ $field }}">
                    @foreach($this->categories as $value => $text)
                        <option value="{{ $value }}">{{ __($text) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="{{ $label }}">{{ __('Tags') }}</label>
                <x-ui.tag-input wire:model="documentTags" :suggestions="$this->availableTags" />
            </div>

            @unless($this->isJobSiteContext())
                <div>
                    <label class="{{ $label }}">{{ __('Location') }}</label>
                    <select wire:model.live="documentJobSiteId" class="{{ $field }}">
                        <option value="">{{ __('Project (General)') }}</option>
                        @foreach($jobSites as $site)
                            <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Moving a document to another location clears its folder.') }}</p>
                </div>
            @endunless

            <div>
                <label class="{{ $label }}">{{ __('Folder') }}</label>
                <select wire:model="documentFolderId" class="{{ $field }}">
                    <option value="">{{ __('No folder (root)') }}</option>
                    @foreach($this->folderOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->parent ? $option->parent->name.' / ' : '' }}{{ $option->name }}</option>
                    @endforeach
                </select>
                @error('documentFolderId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-1">
            <label class="{{ $label }}">{{ __('Visibility') }}</label>
            <x-ui.toggle
                wire:model.live="documentIsInternal"
                :checked="$documentIsInternal"
                :onLabel="__('Internal only')"
                :offLabel="__('Everyone with access to this project')"
                :description="$documentIsInternal
                    ? __('Only administrators and managers can see this document.')
                    : __('Anyone who can open this project can see this document.')" />
        </div>

        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
            <x-ui.button type="button" variant="secondary" wire:click="closeEditModal">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="save">{{ __('Save Changes') }}</x-ui.button>
        </div>
    </form>
</x-ui.modal>
