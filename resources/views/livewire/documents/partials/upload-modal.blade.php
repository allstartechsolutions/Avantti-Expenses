{{--
    Upload panel. On an install with Cloudflare R2 the browser pushes the file
    straight to the bucket (documentUploader in resources/js/app.js); without
    it the file goes through PHP, with the smaller limit stated on screen.
    Expects: $jobSites (project level only).
--}}
@php
    $config = $this->uploadConfig;
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
@endphp

<x-ui.modal name="document-upload-modal" maxWidth="full" layer="top">
    <div class="flex min-h-screen flex-col">
        {{-- Header --}}
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                        {{ $uploadDocumentId ? __('Upload New Version') : __('Upload Files') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                        {{ $this->activeLocationName() }}
                        @if($this->currentFolder) / {{ $this->currentFolder->name }} @endif
                    </p>
                </div>
                <button type="button" wire:click="closeUploadModal" title="{{ __('Close') }}"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-900">
            <div class="mx-auto max-w-7xl px-6 py-6 space-y-6">

                @if($this->isFlatMode() && ! $uploadDocumentId)
                    <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                        {{ __('You are viewing every location at once. Choose Project (General) or a job site before uploading, so the files are filed somewhere.') }}
                    </div>
                @endif

                {{-- What the files will be filed as --}}
                @unless($uploadDocumentId)
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('How these files will be filed') }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Applied to every file in this batch. Any of it can be changed per document afterwards.') }}
                            </p>
                        </div>

                        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Category') }}</label>
                                <select wire:model="uploadCategory" class="{{ $field }}">
                                    @foreach($this->categories as $value => $text)
                                        <option value="{{ $value }}">{{ __($text) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="{{ $label }}">{{ __('Tags') }}</label>
                                <x-ui.tag-input wire:model="uploadTags" :suggestions="$this->availableTags" />
                            </div>
                        </div>

                        <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700">
                            <label class="{{ $label }}">{{ __('Visibility') }}</label>
                            <x-ui.toggle
                                wire:model.live="uploadIsInternal"
                                :checked="$uploadIsInternal"
                                :onLabel="__('Internal only')"
                                :offLabel="__('Everyone with access to this project')"
                                :description="$uploadIsInternal
                                    ? __('Only administrators and managers will see these files.')
                                    : __('Anyone who can open this project will see these files.')" />
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
                        <label class="{{ $label }}">{{ __('What changed in this version?') }}</label>
                        <input type="text" wire:model="uploadVersionNotes" placeholder="{{ __('Revision B — updated foundation detail') }}" class="{{ $field }}">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                            {{ __('The previous version is kept and stays downloadable from the document history.') }}
                        </p>
                    </div>
                @endif

                @if($config['mode'] === 'cloud')
                    {{-- Direct-to-storage uploader --}}
                    <div
                        wire:key="uploader-{{ $this->uploadContextKey() }}"
                        wire:ignore
                        x-data="documentUploader(@js($config + ['messages' => [
                            'type' => __('This file type is not allowed.'),
                            'size' => __('Larger than the :size limit.', ['size' => $config['maxLabel']]),
                            'empty' => __('This file is empty.'),
                            'failed' => __('The upload failed. Try again.'),
                            'network' => __('The connection dropped during the upload.'),
                            'cancelled' => __('Cancelled.'),
                            'etag' => __('Storage did not return a file signature. Check the bucket CORS policy.'),
                        ]]))"
                        class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700"
                    >
                        <div
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop($event)"
                            :class="dragging ? 'border-[#3F5189] bg-[#3F5189]/5' : 'border-slate-300 dark:border-slate-600'"
                            class="m-5 rounded-lg border-2 border-dashed px-6 py-10 text-center transition"
                        >
                            <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">
                                {{ __('Drag files here, or') }}
                                <label class="text-[#3F5189] dark:text-[#8B9DD6] font-medium cursor-pointer hover:underline">
                                    {{ __('choose files') }}
                                    <input type="file" class="hidden" multiple accept="{{ $config['accept'] }}" @change="onPick($event)">
                                </label>
                            </p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Up to :size per file. Files go straight to secure storage.', ['size' => $config['maxLabel']]) }}
                            </p>
                        </div>

                        <template x-if="items.length">
                            <div class="px-5 pb-5 space-y-2">
                                <template x-for="item in items" :key="item.id">
                                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-4 py-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate" x-text="item.name"></p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    <span x-text="formatBytes(item.size)"></span>
                                                    <span x-show="item.status === 'uploading'"> · <span x-text="item.progress + '%'"></span></span>
                                                    <span x-show="item.status === 'finishing'"> · {{ __('Finishing...') }}</span>
                                                    <span x-show="item.status === 'done'" class="text-green-600 dark:text-green-400"> · {{ __('Uploaded') }}</span>
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <button type="button" x-show="item.status === 'error'" @click="retry(item)"
                                                    class="text-xs text-[#3F5189] dark:text-[#8B9DD6] hover:underline">{{ __('Retry') }}</button>
                                                <button type="button" x-show="item.status !== 'done'" @click="remove(item)"
                                                    class="text-slate-400 hover:text-red-600 dark:hover:text-red-400" :title="item.status === 'uploading' ? '{{ __('Cancel') }}' : '{{ __('Remove') }}'">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                        <div x-show="item.status === 'uploading' || item.status === 'finishing'" class="mt-2 h-1.5 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                            <div class="h-full bg-[#3F5189] transition-all" :style="`width: ${item.progress}%`"></div>
                                        </div>
                                        <p x-show="item.error" x-text="item.error" class="mt-2 text-xs text-red-600 dark:text-red-400"></p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Footer lives inside the Alpine scope so it can see the queue --}}
                        <div class="sticky bottom-0 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                <span x-show="! items.length">{{ __('Nothing queued yet.') }}</span>
                                <span x-show="items.length" x-text="`${items.filter(i => i.status === 'done').length} / ${items.length} {{ __('uploaded') }}`"></span>
                            </p>
                            <div class="flex items-center gap-3">
                                <x-ui.button type="button" variant="secondary" wire:click="closeUploadModal">{{ __('Close') }}</x-ui.button>
                                <button type="button" @click="startAll()" :disabled="! queued.length || busy"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#3F5189] text-white text-sm font-medium hover:bg-[#4A5A96] disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="! busy">{{ __('Start Upload') }}</span>
                                    <span x-show="busy" x-text="`{{ __('Uploading') }} ${totalProgress}%`"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Local disk: the file travels through PHP --}}
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5 space-y-4">
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                            {{ __('This install stores documents on the server. Files must be :size or smaller. Connect Cloudflare R2 to upload large files.', ['size' => $config['maxLabel']]) }}
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Files') }}</label>

                            {{-- The same act as the cloud branch above: drag or choose,
                                 see the queue, take one back off, then upload. Only the
                                 transport differs. --}}
                            <x-ui.file-drop
                                wire:model="localNewUploads"
                                :accept="$config['accept']"
                                :hint="__('Up to :size per file, :count files at a time.', ['size' => $config['maxLabel'], 'count' => 20])"
                                class="mt-1 space-y-2">

                                <div wire:loading wire:target="localNewUploads" class="text-sm text-slate-500 dark:text-slate-400">{{ __('Reading files...') }}</div>

                                @error('localUploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                @error('localUploads.*') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                @error('localNewUploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                @error('localNewUploads.*') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                @if(is_array($localUploads) && count($localUploads))
                                    <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                        @foreach($localUploads as $index => $file)
                                            <li wire:key="local-upload-{{ $index }}" class="px-3 py-2 flex items-center justify-between gap-3">
                                                <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                                    {{ $file->getClientOriginalName() }}
                                                </span>
                                                <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                                    {{ \App\Services\DocumentSettings::formatBytes($file->getSize()) }}
                                                </span>
                                                <x-ui.icon-button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    type="button"
                                                    wire:click="discardLocalUpload({{ $index }})"
                                                    title="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                                    aria-label="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                                    class="hover:text-red-600 dark:hover:text-red-400" />
                                            </li>
                                        @endforeach
                                    </ul>

                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ trans_choice(':count file queued.|:count files queued.', count($localUploads), ['count' => count($localUploads)]) }}
                                    </p>
                                @endif
                            </x-ui.file-drop>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                            <x-ui.button type="button" variant="secondary" wire:click="closeUploadModal">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="button" variant="primary" icon="upload" wire:click="saveLocalUploads" wire:loading.attr="disabled" wire:target="saveLocalUploads">
                                <span wire:loading.remove wire:target="saveLocalUploads">{{ __('Upload') }}</span>
                                <span wire:loading wire:target="saveLocalUploads">{{ __('Uploading...') }}</span>
                            </x-ui.button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-ui.modal>
