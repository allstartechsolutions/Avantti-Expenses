{{-- Three different nothings: filtered out, empty trash, empty folder. --}}
<div class="px-6 py-16 text-center">
    @if($this->hasFilters())
        <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ __('No documents match these filters') }}</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Try a different search, or clear the filters to see everything here.') }}</p>
        <div class="mt-4">
            <x-ui.button variant="secondary" size="sm" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>
        </div>
    @elseif($showTrash)
        <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">{{ __('The trash is empty') }}</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Deleted documents appear here and can be restored.') }}</p>
    @else
        <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>
        <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white">
            {{ $folderId ? __('This folder is empty') : __('No documents yet') }}
        </h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
            @if($this->canUploadHere())
                {{ __('Drag files anywhere on this panel, or use the Upload button. Plans, permits, contracts, photos — up to :size each.', ['size' => $this->uploadConfig['maxLabel']]) }}
            @else
                {{ __('Nothing has been filed here yet. Ask a manager or administrator to upload the documents for this location.') }}
            @endif
        </p>
        @if($this->canUploadHere())
            <div class="mt-4 flex items-center justify-center gap-2">
                <x-ui.button variant="primary" icon="upload" wire:click="openUploadModal">{{ __('Upload Files') }}</x-ui.button>
                @unless($this->isFlatMode())
                    <x-ui.button variant="secondary" wire:click="openFolderModal">{{ __('New Folder') }}</x-ui.button>
                @endunless
            </div>
        @endif
    @endif
</div>
