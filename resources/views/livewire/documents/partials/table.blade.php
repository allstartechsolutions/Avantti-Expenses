{{-- The list view. --}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
        <thead class="bg-slate-50 dark:bg-slate-900/40">
            <tr>
                <th class="px-4 sm:px-6 py-3 w-10">
                    <input type="checkbox" wire:click="toggleSelectAll"
                        @checked(count($selected) > 0 && count($selected) === $documents->count())
                        class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                </th>
                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Name') }}</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">{{ __('Category') }}</th>
                @if(! $this->isJobSiteContext())
                    <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden lg:table-cell">{{ __('Location') }}</th>
                @endif
                @if($this->isFlatMode())
                    <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden xl:table-cell">{{ __('Folder') }}</th>
                @endif
                <th class="px-3 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden sm:table-cell">{{ __('Size') }}</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden lg:table-cell">{{ __('Uploaded by') }}</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">{{ __('Updated') }}</th>
                <th class="px-4 sm:px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($documents as $document)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40" wire:key="doc-{{ $document->id }}">
                    <td class="px-4 sm:px-6 py-3">
                        <input type="checkbox" value="{{ $document->id }}" wire:model.live="selected"
                            class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <x-document-icon :document="$document" />
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="openDetail({{ $document->id }})"
                                        class="text-sm font-medium text-left truncate {{ $showTrash ? 'text-slate-600 dark:text-slate-300 hover:underline' : 'text-[#3F5189] dark:text-[#8B9DD6] hover:underline' }}"
                                        title="{{ __('Open details') }}">
                                        {{ $document->name }}
                                    </button>
                                    @if($document->hasVersionHistory())
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                            v{{ $document->current_version_number }}
                                        </span>
                                    @endif
                                    @if($document->is_internal)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">
                                            {{ __('Internal') }}
                                        </span>
                                    @endif
                                </div>
                                @if($document->tags->isNotEmpty())
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($document->tags as $tag)
                                            <button type="button" wire:click="$set('tagFilter', '{{ $tag->id }}')"
                                                class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600"
                                                title="{{ __('Filter by :tag', ['tag' => $tag->name]) }}">
                                                {{ $tag->name }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                <p class="sm:hidden text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $document->formattedSize() }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3 hidden md:table-cell">
                        <x-document-category-badge :category="$document->category" />
                    </td>
                    @if(! $this->isJobSiteContext())
                        <td class="px-3 py-3 hidden lg:table-cell text-sm text-slate-600 dark:text-slate-300">{{ $document->locationLabel() }}</td>
                    @endif
                    @if($this->isFlatMode())
                        <td class="px-3 py-3 hidden xl:table-cell text-sm text-slate-600 dark:text-slate-300">{{ $document->folder?->name ?? '—' }}</td>
                    @endif
                    <td class="px-3 py-3 hidden sm:table-cell text-sm text-right text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $document->formattedSize() }}</td>
                    <td class="px-3 py-3 hidden lg:table-cell text-sm text-slate-600 dark:text-slate-300">{{ $document->uploadedBy?->name ?? '—' }}</td>
                    <td class="px-3 py-3 hidden md:table-cell text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                        {{ ($showTrash ? $document->deleted_at : $document->updated_at)?->appDateTime() }}
                    </td>
                    <td class="px-4 sm:px-6 py-3">
                        <div class="flex items-center justify-end gap-1">
                            @if($showTrash)
                                @if($canDelete)
                                    <x-ui.button variant="secondary" size="sm" wire:click="restoreDocument({{ $document->id }})">{{ __('Restore') }}</x-ui.button>
                                    <x-ui.button variant="danger" size="sm" wire:click="purgeDocument({{ $document->id }})"
                                        wire:confirm="{{ __('Permanently delete this document and every version of it? This cannot be undone.') }}">
                                        {{ __('Delete for good') }}
                                    </x-ui.button>
                                @endif
                            @else
                                <button type="button" wire:click="openDetail({{ $document->id }})" title="{{ __('Open details') }}"
                                    class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                <a href="{{ route('documents.download', $document) }}" title="{{ __('Download') }}"
                                   class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                                @if($canShare)
                                    <button type="button" wire:click="openShareModal({{ $document->id }})" title="{{ __('Share') }}"
                                        class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342a3 3 0 100-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684zm0-12a3 3 0 105.368-2.684 3 3 0 00-5.368 2.684z"/></svg>
                                    </button>
                                @endif
                                @if($canWrite)
                                    <button type="button" wire:click="openUploadModal({{ $document->id }})" title="{{ __('Upload new version') }}"
                                        class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    </button>
                                    <button type="button" wire:click="openEditModal({{ $document->id }})" title="{{ __('Edit') }}"
                                        class="p-1.5 text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                @endif
                                @if($canDelete)
                                    <button type="button" wire:click="deleteDocument({{ $document->id }})"
                                        wire:confirm="{{ __('Move this document to the trash?') }}" title="{{ __('Delete') }}"
                                        class="p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
