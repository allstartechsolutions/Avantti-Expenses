{{--
    Share panel: make an expiring public link for a document or a folder, and
    manage the links already made.
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
@endphp

<x-ui.modal name="document-share-modal" maxWidth="3xl" layer="top">
    <div class="flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $sharingFolderId ? __('Share Folder') : __('Share Document') }}
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $this->shareTargetName() }}</p>
            </div>
            <button type="button" wire:click="closeShareModal" title="{{ __('Close') }}"
                class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                {{ __('Anyone with the link can open it — no login needed. Set an expiry, and a password when it matters.') }}
            </p>

            @if($sharingDocumentId && \App\Models\Document::find($sharingDocumentId)?->is_internal)
                <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
                    {{ __('This document is marked internal. A share link hands it to someone outside the company, which is the opposite of that — consider a password and a short expiry.') }}
                </div>
            @endif

            @if($sharingFolderId)
                <div class="rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Everything in this folder and its subfolders will be listed, except documents marked internal. Anything added later is included automatically.') }}
                </div>
            @endif

            @if($createdShareUrl)
                {{-- The link only appears once it exists, so nothing half-made gets copied --}}
                <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4"
                     x-data="{ copied: false }">
                    <p class="text-sm font-medium text-green-900 dark:text-green-200">{{ __('Link ready') }}</p>
                    <div class="mt-2 flex flex-col sm:flex-row gap-2">
                        <input type="text" readonly value="{{ $createdShareUrl }}" x-ref="url"
                            @focus="$event.target.select()"
                            class="flex-1 px-3 py-2 text-sm border border-green-300 dark:border-green-700 rounded-lg bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                        <button type="button"
                            @click="navigator.clipboard.writeText($refs.url.value); copied = true; setTimeout(() => copied = false, 2000)"
                            class="px-4 py-2 rounded-lg bg-[#3F5189] text-white text-sm font-medium hover:bg-[#4A5A96] whitespace-nowrap">
                            <span x-show="! copied">{{ __('Copy link') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                        </button>
                    </div>
                    @if($sharePassword === '' && $this->shares->first()?->requiresPassword())
                        <p class="mt-2 text-xs text-green-800 dark:text-green-300">
                            {{ __('Send the password separately — not in the same message as the link.') }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- New link --}}
            <form wire:submit="createShare" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ __('Expires on') }}</label>
                        <x-ui.date-input wire:model="shareExpiresAt" class="{{ $field }}" />
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Leave empty for a link that never expires.') }}</p>
                        @error('shareExpiresAt') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('For whom') }}</label>
                        <input type="text" wire:model="shareRecipient" placeholder="{{ __('Client, engineer, city hall...') }}" class="{{ $field }}">
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('A note to yourself, so you know what to revoke later.') }}</p>
                        @error('shareRecipient') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Password') }}</label>
                        <input type="text" wire:model="sharePassword" placeholder="{{ __('Optional') }}" class="{{ $field }}">
                        @error('sharePassword') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('Download limit') }}</label>
                        <input type="number" min="1" wire:model="shareMaxDownloads" placeholder="{{ __('No limit') }}" class="{{ $field }}">
                        @error('shareMaxDownloads') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="pt-1">
                    <label class="{{ $label }}">{{ __('What they can do') }}</label>
                    <x-ui.toggle
                        wire:model.live="shareAllowDownload"
                        :checked="$shareAllowDownload"
                        :onLabel="__('View and download')"
                        :offLabel="__('View only')"
                        :description="$shareAllowDownload
                            ? __('The page shows a download button.')
                            : __('No download button. It cannot stop someone saving what they can see on screen.')" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <x-ui.button type="button" variant="secondary" wire:click="closeShareModal">{{ __('Close') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ __('Create Link') }}</x-ui.button>
                </div>
            </form>

            {{-- Existing links --}}
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">{{ __('Links for this item') }}</h3>
                @if($this->shares->isEmpty())
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('None yet.') }}</p>
                @else
                    <ul class="space-y-2">
                        @foreach($this->shares as $share)
                            <li class="rounded-lg border border-slate-200 dark:border-slate-700 px-4 py-3">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                                            {{ $share->recipient_label ?: __('Unnamed link') }}
                                            @if($share->isUsable())
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">{{ __('Active') }}</span>
                                            @else
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                    {{ $share->isRevoked() ? __('Revoked') : ($share->isExpired() ? __('Expired') : __('Limit reached')) }}
                                                </span>
                                            @endif
                                            @if($share->requiresPassword())
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ __('Password') }}</span>
                                            @endif
                                            @unless($share->allow_download)
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ __('View only') }}</span>
                                            @endunless
                                        </p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            @if($share->expires_at)
                                                {{ __('Expires :date', ['date' => $share->expires_at->appDate()]) }}
                                            @else
                                                {{ __('No expiry') }}
                                            @endif
                                            · {{ trans_choice('{0} never opened|{1} :count download|[2,*] :count downloads', $share->download_count, ['count' => $share->download_count]) }}
                                            @if($share->max_downloads) {{ __('of :max', ['max' => $share->max_downloads]) }} @endif
                                            @if($share->last_accessed_at) · {{ __('last opened :date', ['date' => $share->last_accessed_at->appDateTime()]) }} @endif
                                            · {{ __('by :name', ['name' => $share->createdBy?->name ?? __('unknown')]) }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0" x-data="{ copied: false }">
                                        @if($share->isUsable())
                                            <button type="button"
                                                @click="navigator.clipboard.writeText(@js($share->publicUrl())); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="text-sm text-[#3F5189] dark:text-[#8B9DD6] hover:underline">
                                                <span x-show="! copied">{{ __('Copy link') }}</span>
                                                <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                                            </button>
                                            <button type="button" wire:click="revokeShare({{ $share->id }})"
                                                wire:confirm="{{ __('Revoke this link? Anyone using it will lose access immediately.') }}"
                                                class="text-sm text-red-600 dark:text-red-400 hover:underline">{{ __('Revoke') }}</button>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-ui.modal>
