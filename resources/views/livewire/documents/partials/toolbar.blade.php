{{-- Search, filters and the actions. Shared by the project and job site pages. --}}
@props(['showLocation' => false, 'jobSites' => null])

<div class="space-y-4">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex-1 flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="relative max-w-md w-full">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search name, description, tag...') }}"
                    class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            @if($showLocation)
                <select wire:model.live="locationFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="project">{{ __('Project (General)') }}</option>
                    @foreach($jobSites as $site)
                        <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                    @endforeach
                    <option value="">{{ __('All Locations') }}</option>
                </select>
            @endif

            <select wire:model.live="categoryFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($this->categories as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </select>

            @if($this->availableTags->isNotEmpty())
                <select wire:model.live="tagFilter" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('All Tags') }}</option>
                    @foreach($this->availableTags as $tag)
                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                    @endforeach
                </select>
            @endif

            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open"
                    class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 flex items-center gap-2">
                    {{ __('More filters') }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" x-transition
                    class="absolute z-20 mt-2 w-72 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-lg p-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('Uploaded by') }}</label>
                        <select wire:model.live="uploaderFilter" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            <option value="">{{ __('Anyone') }}</option>
                            @foreach($this->uploaders as $uploader)
                                <option value="{{ $uploader->id }}">{{ $uploader->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('From') }}</label>
                            <input type="date" wire:model.live="dateFrom" class="w-full px-2 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ __('To') }}</label>
                            <input type="date" wire:model.live="dateTo" class="w-full px-2 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        </div>
                    </div>
                </div>
            </div>

            @if($this->hasFilters())
                <button type="button" wire:click="clearFilters" class="text-sm text-[#3F5189] dark:text-[#8B9DD6] hover:underline whitespace-nowrap">
                    {{ __('Clear filters') }}
                </button>
            @endif
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- List / grid --}}
            <div class="inline-flex rounded-lg border border-slate-300 dark:border-slate-600 overflow-hidden">
                <button type="button" wire:click="setViewMode('list')" title="{{ __('List view') }}"
                    class="px-3 py-2 text-sm {{ $viewMode === 'list' ? 'bg-[#3F5189] text-white' : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <button type="button" wire:click="setViewMode('grid')" title="{{ __('Grid view') }}"
                    class="px-3 py-2 text-sm {{ $viewMode === 'grid' ? 'bg-[#3F5189] text-white' : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-600' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </button>
            </div>

            @admin
                <x-ui.button variant="ghost" size="sm" wire:click="toggleTrash">
                    {{ $showTrash ? __('Back to files') : __('Trash') }}
                    @if(! $showTrash && $this->trashCount > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full bg-slate-200 dark:bg-slate-600 text-xs">{{ $this->trashCount }}</span>
                    @endif
                </x-ui.button>

                @if($showTrash)
                    @php($binned = $this->stats['count'])
                    {{-- The button empties what is listed, so when a filter narrows
                         the list it must not claim to empty the whole trash. --}}
                    <x-ui.button
                        variant="danger"
                        size="sm"
                        icon="trash"
                        :disabled="$binned === 0"
                        wire:click="emptyTrash"
                        wire:confirm="{{ trans_choice('{1} Permanently delete :count document and its files? This cannot be undone.|[2,*] Permanently delete :count documents and their files? This cannot be undone.', $binned, ['count' => $binned]) }}">
                        {{ $this->hasFilters() ? __('Delete Listed') : __('Empty Trash') }}@if($binned > 0) ({{ $binned }})@endif
                    </x-ui.button>
                @endif
            @endadmin

            @if($this->canUploadHere())
                @unless($this->isFlatMode())
                    <x-ui.button variant="secondary" size="sm" wire:click="openFolderModal">
                        {{ __('New Folder') }}
                    </x-ui.button>
                @endunless
                <x-ui.button variant="primary" icon="upload" wire:click="openUploadModal">
                    {{ __('Upload Files') }}
                </x-ui.button>
            @endif
        </div>
    </div>
</div>
