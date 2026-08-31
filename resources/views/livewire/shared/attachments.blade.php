<div>
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Attachments') }}</h4>
    </div>

    @if(session('attachment_message'))
        <div class="mb-3 text-sm text-green-600 dark:text-green-400">{{ session('attachment_message') }}</div>
    @endif

    <!-- Attachment List -->
    @if($attachments->count() > 0)
        <ul class="space-y-2 mb-4">
            @foreach($attachments as $attachment)
                <li class="flex items-center justify-between gap-3 p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-3 min-w-0">
                        @if($attachment->isPdf())
                            <svg class="w-6 h-6 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        @else
                            <svg class="w-6 h-6 flex-shrink-0 text-[#3F5189]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        @endif
                        <div class="min-w-0">
                            <a href="{{ route('files.show', ['path' => $attachment->file_path]) }}" target="_blank"
                               class="block text-sm font-medium text-[#3F5189] dark:text-[#8B9DD6] hover:underline truncate">
                                {{ $attachment->original_name }}
                            </a>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                @if($attachment->uploadedBy)
                                    {{ __('Uploaded by') }} {{ $attachment->uploadedBy->name }} ·
                                @endif
                                {{ $attachment->created_at->appDateTime() }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ route('files.download', ['path' => $attachment->file_path]) }}"
                           class="text-slate-500 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]"
                           title="{{ __('Download') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                        </a>
                        @if($this->canDelete())
                            <button
                                wire:click="deleteAttachment({{ $attachment->id }})"
                                wire:confirm="{{ __('Are you sure you want to delete this file?') }}"
                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                title="{{ __('Delete') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">{{ __('No attachments yet.') }}</p>
    @endif

    <!-- Upload Form -->
    @if($this->canUpload())
        <form wire:submit.prevent="save" class="space-y-3">
            <x-ui.file-drop
                wire:model="newUploads"
                accept=".pdf,.jpg,.jpeg,.png"
                :hint="__('PDF, JPG or PNG, up to 10MB each.')">

                {{-- Three keys, three different refusals: the upload's own check,
                     this box's, and Livewire's temporary-upload rules. --}}
                @error('uploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @error('uploads.*') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @error('newUploads') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                @error('newUploads.*') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                @if(count($uploads) > 0)
                    <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                        @foreach($uploads as $index => $file)
                            <li wire:key="pending-{{ $index }}" class="px-3 py-2 flex items-center justify-between gap-3">
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
                                    wire:click="discardUpload({{ $index }})"
                                    title="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                    aria-label="{{ __('Remove :file', ['file' => $file->getClientOriginalName()]) }}"
                                    class="hover:text-red-600 dark:hover:text-red-400" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.file-drop>

            @if(count($uploads) > 0)
                <div class="flex items-center justify-end">
                    <x-ui.button type="submit" variant="secondary" size="sm" icon="upload"
                        wire:loading.attr="disabled" wire:target="newUploads,save">
                        <span wire:loading.remove wire:target="newUploads,save">
                            {{ trans_choice('Upload :count file|Upload :count files', count($uploads), ['count' => count($uploads)]) }}
                        </span>
                        <span wire:loading wire:target="newUploads,save">{{ __('Uploading...') }}</span>
                    </x-ui.button>
                </div>
            @endif
        </form>
    @endif
</div>
