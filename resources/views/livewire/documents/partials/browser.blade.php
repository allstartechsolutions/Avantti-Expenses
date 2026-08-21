{{-- Breadcrumb, folders and documents. Shared by both levels. --}}
@php
    $documents = $this->documents;
    $folders = $this->folders;
    // M12 split the old two-way write/delete flag into the grants that
    // actually differ. $canWrite is still "may they change anything here",
    // which is what most of the toolbar asks.
    $canWrite = $this->canManageDocuments();
    $canCreate = $this->canCreateDocuments();
    $canDelete = $this->canDeleteDocuments();
    $canShare = $this->canShareDocuments();
@endphp

<div
    class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700"
    @if($this->canUploadHere())
        x-data="{ over: false }"
        @dragover.prevent="over = true"
        @dragleave.prevent="over = false"
        @drop.prevent="over = false; window.__documentDroppedFiles = $event.dataTransfer.files; $wire.openUploadModal()"
        :class="over ? 'ring-2 ring-[#3F5189] ring-offset-2 dark:ring-offset-slate-900' : ''"
    @endif
>
    {{-- Location / folder path --}}
    <div class="px-4 sm:px-6 py-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center gap-2 text-sm">
        @if($showTrash)
            <span class="font-medium text-slate-900 dark:text-white">
                {{ __('Trash') }}
                @unless($this->isFlatMode())
                    <span class="font-normal text-slate-500 dark:text-slate-400">— {{ $this->activeLocationName() }}</span>
                @endunless
            </span>
            <span class="text-slate-400 dark:text-slate-500">
                @if(config('documents.retention_days'))
                    {{ __('Deleted documents are removed for good after :days days.', ['days' => config('documents.retention_days')]) }}
                @else
                    {{ __('Deleted documents stay here until an administrator removes them.') }}
                @endif
            </span>
        @elseif($this->isFlatMode())
            <span class="font-medium text-slate-900 dark:text-white">{{ __('All Locations') }}</span>
            <span class="text-slate-400 dark:text-slate-500">{{ __('Every document in the project. Choose a location to browse folders.') }}</span>
        @else
            <button type="button" wire:click="openFolder(null)"
                class="inline-flex items-center gap-1 {{ $folderId ? 'text-[#3F5189] dark:text-[#8B9DD6] hover:underline' : 'font-medium text-slate-900 dark:text-white' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                {{ $this->activeLocationName() }}
            </button>
            @foreach($this->breadcrumb as $crumb)
                <span class="text-slate-400">/</span>
                @if($loop->last)
                    <span class="font-medium text-slate-900 dark:text-white">{{ $crumb->name }}</span>
                @else
                    <button type="button" wire:click="openFolder({{ $crumb->id }})" class="text-[#3F5189] dark:text-[#8B9DD6] hover:underline">{{ $crumb->name }}</button>
                @endif
            @endforeach
        @endif

        @if(filled($search))
            <span class="ml-auto text-xs text-slate-500 dark:text-slate-400">{{ __('Searching the whole location') }}</span>
        @endif
    </div>

    {{-- Bulk action bar --}}
    @if(count($selected) > 0)
        <div class="px-4 sm:px-6 py-3 bg-[#3F5189]/5 dark:bg-[#3F5189]/20 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-slate-900 dark:text-white">
                {{ trans_choice('{1} :count selected|[2,*] :count selected', count($selected), ['count' => count($selected)]) }}
            </span>

            @if($canCreate && ! $showTrash)
                @unless($this->isFlatMode())
                    <div class="flex items-center gap-2">
                        <select wire:model="bulkFolderId" class="px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="">{{ __('Root folder') }}</option>
                            @foreach($this->folderOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->parent ? $option->parent->name.' / ' : '' }}{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <x-ui.button variant="secondary" size="sm" wire:click="bulkMove">{{ __('Move') }}</x-ui.button>
                    </div>
                @endunless

                <div class="flex items-center gap-2">
                    <select wire:model="bulkCategory" class="px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        <option value="">{{ __('Set category...') }}</option>
                        @foreach($this->categories as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <x-ui.button variant="secondary" size="sm" wire:click="bulkSetCategory">{{ __('Apply') }}</x-ui.button>
                </div>
            @endif

            @if($canDelete && ! $showTrash)
                <x-ui.button variant="danger" size="sm" wire:click="bulkDelete"
                    wire:confirm="{{ __('Move the selected documents to the trash?') }}">
                    {{ __('Delete') }}
                </x-ui.button>
            @endif

            @if($canDelete && $showTrash)
                <x-ui.button variant="secondary" size="sm" wire:click="bulkRestore">
                    {{ __('Restore') }}
                </x-ui.button>
                <x-ui.button variant="danger" size="sm" wire:click="bulkPurge"
                    wire:confirm="{{ trans_choice('{1} Permanently delete :count document and its files? This cannot be undone.|[2,*] Permanently delete :count documents and their files? This cannot be undone.', count($selected), ['count' => count($selected)]) }}">
                    {{ __('Delete for good') }}
                </x-ui.button>
            @endif

            <button type="button" wire:click="$set('selected', [])" class="text-sm text-slate-500 dark:text-slate-400 hover:underline ml-auto">
                {{ __('Clear selection') }}
            </button>
        </div>
    @endif

    {{-- Folders --}}
    @if($folders->isNotEmpty())
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach($folders as $folder)
                    <div class="group flex items-center gap-3 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2.5 hover:border-[#3F5189] dark:hover:border-[#8B9DD6] transition">
                        <button type="button" wire:click="openFolder({{ $folder->id }})" class="flex items-center gap-3 min-w-0 flex-1 text-left">
                            <svg class="w-6 h-6 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900 dark:text-white truncate">{{ $folder->name }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    {{ trans_choice('{0} Empty|{1} :count file|[2,*] :count files', $folder->documents_count, ['count' => $folder->documents_count]) }}
                                </span>
                            </span>
                        </button>
                        @if($canWrite || $canShare)
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition">
                                @if($canShare)
                                <button type="button" wire:click="openShareModal(null, {{ $folder->id }})" title="{{ __('Share') }}"
                                    class="p-1 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342a3 3 0 100-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684zm0-12a3 3 0 105.368-2.684 3 3 0 00-5.368 2.684z"/></svg>
                                </button>
                                @endif
                                @if($canWrite)
                                <button type="button" wire:click="openFolderModal({{ $folder->id }})" title="{{ __('Rename') }}"
                                    class="p-1 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                @endif
                                @if($canDelete)
                                    <button type="button" wire:click="deleteFolder({{ $folder->id }})"
                                        wire:confirm="{{ __('Delete this folder? Anything inside moves up one level.') }}" title="{{ __('Delete') }}"
                                        class="p-1 text-slate-400 hover:text-red-600 dark:hover:text-red-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Documents --}}
    @if($documents->count() === 0)
        @include('livewire.documents.partials.empty-state')
    @elseif($viewMode === 'grid')
        @include('livewire.documents.partials.grid', ['documents' => $documents, 'canWrite' => $canWrite, 'canDelete' => $canDelete, 'canShare' => $canShare])
    @else
        @include('livewire.documents.partials.table', ['documents' => $documents, 'canWrite' => $canWrite, 'canDelete' => $canDelete, 'canShare' => $canShare])
    @endif

    @if($documents->hasPages())
        <div class="px-4 sm:px-6 py-4 border-t border-slate-200 dark:border-slate-700">
            {{ $documents->links() }}
        </div>
    @endif
</div>
