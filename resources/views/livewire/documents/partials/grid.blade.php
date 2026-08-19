{{-- The grid view: the same records as the table, easier on photo-heavy folders. --}}
<div class="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    @foreach($documents as $document)
        <div class="group relative flex flex-col rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden hover:border-[#3F5189] dark:hover:border-[#8B9DD6] transition"
             wire:key="grid-{{ $document->id }}">
            <div class="absolute top-2 left-2 z-10">
                <input type="checkbox" value="{{ $document->id }}" wire:model.live="selected"
                    class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] bg-white/90">
            </div>

            <div class="h-32 flex items-center justify-center bg-slate-50 dark:bg-slate-900/40 cursor-pointer"
                 wire:click="openDetail({{ $document->id }})">
                @if($document->isImage() && ! $showTrash)
                    <img src="{{ route('documents.preview', $document) }}" alt="{{ $document->name }}"
                         class="h-full w-full object-cover" loading="lazy">
                @else
                    <x-document-icon :document="$document" size="w-12 h-12" />
                @endif
            </div>

            <div class="p-3 flex-1 flex flex-col gap-2">
                <div class="flex items-start gap-2">
                    <button type="button" wire:click="openDetail({{ $document->id }})"
                        class="text-sm font-medium text-left line-clamp-2 {{ $showTrash ? 'text-slate-600 dark:text-slate-300 hover:underline' : 'text-[#3F5189] dark:text-[#8B9DD6] hover:underline' }}"
                        title="{{ __('Open details') }}">
                        {{ $document->name }}
                    </button>
                    @if($document->hasVersionHistory())
                        <span class="ml-auto px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">v{{ $document->current_version_number }}</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <x-document-category-badge :category="$document->category" />
                    @if($document->is_internal)
                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">{{ __('Internal') }}</span>
                    @endif
                </div>

                @if($document->tags->isNotEmpty())
                    {{-- Three fit a card; the rest are counted rather than wrapped
                         onto a fourth line and pushing the actions out of line. --}}
                    <div class="flex flex-wrap gap-1">
                        @foreach($document->tags->take(3) as $tag)
                            <button type="button" wire:click="$set('tagFilter', '{{ $tag->id }}')"
                                class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600"
                                title="{{ __('Filter by :tag', ['tag' => $tag->name]) }}">
                                {{ $tag->name }}
                            </button>
                        @endforeach
                        @if($document->tags->count() > 3)
                            <span class="px-1.5 py-0.5 rounded text-[10px] text-slate-500 dark:text-slate-400"
                                title="{{ $document->tags->skip(3)->pluck('name')->implode(', ') }}">
                                +{{ $document->tags->count() - 3 }}
                            </span>
                        @endif
                    </div>
                @endif

                <p class="text-xs text-slate-500 dark:text-slate-400 mt-auto">
                    {{ $document->formattedSize() }}
                    @if(! $this->isJobSiteContext())
                        · {{ $document->locationLabel() }}
                    @endif
                </p>
                <p class="text-xs text-slate-400 dark:text-slate-500">
                    {{ $document->uploadedBy?->name }} · {{ ($showTrash ? $document->deleted_at : $document->updated_at)?->format('d/m/Y') }}
                </p>

                <div class="flex items-center gap-1 pt-1 border-t border-slate-100 dark:border-slate-700">
                    @if($showTrash)
                        @if($canDelete)
                            <x-ui.button variant="secondary" size="sm" wire:click="restoreDocument({{ $document->id }})">{{ __('Restore') }}</x-ui.button>
                            <x-ui.button variant="danger" size="sm" wire:click="purgeDocument({{ $document->id }})"
                                wire:confirm="{{ __('Permanently delete this document and every version of it? This cannot be undone.') }}">{{ __('Delete for good') }}</x-ui.button>
                        @endif
                    @else
                        <button type="button" wire:click="openDetail({{ $document->id }})" title="{{ __('Open details') }}"
                            class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                        <a href="{{ route('documents.download', $document) }}" title="{{ __('Download') }}"
                           class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                        @if($canWrite)
                            <button type="button" wire:click="openUploadModal({{ $document->id }})" title="{{ __('Upload new version') }}"
                                class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            </button>
                            <button type="button" wire:click="openEditModal({{ $document->id }})" title="{{ __('Edit') }}"
                                class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                        @endif
                        @if($canDelete)
                            <button type="button" wire:click="deleteDocument({{ $document->id }})"
                                wire:confirm="{{ __('Move this document to the trash?') }}" title="{{ __('Delete') }}"
                                class="ml-auto p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
