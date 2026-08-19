{{-- The page a client or vendor sees. No app chrome, nothing else reachable. --}}
@php
    $share = $this->share;
    $document = $share->document;
    $unusable = $share->unusableReason();
@endphp

@push('scripts')
    <meta name="robots" content="noindex, nofollow">
@endpush

<div class="space-y-6">
    @if($unusable)
        {{-- Expired, revoked or used up: say which, so they know to ask again --}}
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h1 class="mt-4 text-lg font-semibold text-slate-900">{{ __('This link is no longer available') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $unusable }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('Ask whoever sent it for a new link.') }}</p>
        </div>

    @elseif(! $unlocked)
        {{-- Password gate --}}
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-8 max-w-md mx-auto">
            <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <h1 class="mt-4 text-center text-lg font-semibold text-slate-900">{{ __('This link is protected') }}</h1>
            <p class="mt-1 text-center text-sm text-slate-600">{{ __('Enter the password you were given.') }}</p>

            <form wire:submit="unlock" class="mt-6 space-y-3">
                <input type="password" wire:model="password" autofocus
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]"
                    placeholder="{{ __('Password') }}">
                @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-[#3F5189] text-white text-sm font-medium hover:bg-[#4A5A96]">
                    {{ __('Open') }}
                </button>
            </form>
        </div>

    @elseif($share->isFolderShare())
        {{-- A whole folder --}}
        <div class="bg-white rounded-lg shadow-sm border border-slate-200">
            <div class="px-6 py-5 border-b border-slate-200">
                <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('Shared folder') }}</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ $share->folder->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ trans_choice('{0} No documents|{1} :count document|[2,*] :count documents', $documents->count(), ['count' => $documents->count()]) }}
                    @if($share->expires_at) · {{ __('Available until :date', ['date' => $share->expires_at->format('d/m/Y')]) }} @endif
                </p>
            </div>

            @if($documents->isEmpty())
                <p class="px-6 py-10 text-center text-sm text-slate-500">{{ __('There is nothing in this folder yet.') }}</p>
            @else
                <ul class="divide-y divide-slate-200">
                    @foreach($documents as $item)
                        <li class="px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <x-document-icon :document="$item" />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-900 truncate">{{ $item->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $item->formattedSize() }}
                                        @if($item->folder && $item->folder_id !== $share->folder_id) · {{ $item->folder->name }} @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                @if($item->isPreviewable())
                                    <a href="{{ route('documents.share.view', ['token' => $share->token, 'document' => $item]) }}"
                                       target="_blank" class="text-sm text-[#3F5189] hover:underline">{{ __('View') }}</a>
                                @endif
                                @if($share->allow_download)
                                    <a href="{{ route('documents.share.download', ['token' => $share->token, 'document' => $item]) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#3F5189] text-white text-sm font-medium hover:bg-[#4A5A96]">
                                        {{ __('Download') }}
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    @else
        {{-- A single document --}}
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 flex items-start gap-4">
                <x-document-icon :document="$document" size="w-10 h-10" />
                <div class="min-w-0">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('Shared document') }}</p>
                    <h1 class="mt-0.5 text-xl font-semibold text-slate-900 break-words">{{ $document->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $document->formattedSize() }}
                        · {{ __('updated :date', ['date' => $document->updated_at->format('d/m/Y')]) }}
                        @if($share->expires_at) · {{ __('Available until :date', ['date' => $share->expires_at->format('d/m/Y')]) }} @endif
                    </p>
                </div>
            </div>

            @if($document->isPreviewable())
                <div class="bg-slate-100">
                    @if($document->isPdf())
                        <iframe src="{{ route('documents.share.view', $share->token) }}" class="w-full h-[70vh]" title="{{ $document->name }}"></iframe>
                    @elseif($document->isImage())
                        <div class="flex items-center justify-center p-4">
                            <img src="{{ route('documents.share.view', $share->token) }}" alt="{{ $document->name }}" class="max-h-[70vh] w-auto rounded">
                        </div>
                    @else
                        <video controls preload="metadata" class="w-full max-h-[70vh] bg-black">
                            <source src="{{ route('documents.share.view', $share->token) }}" type="{{ $document->current_mime_type }}">
                        </video>
                    @endif
                </div>
            @endif

            <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">
                    @if($share->allow_download)
                        @if($share->max_downloads)
                            {{ trans_choice('{1} :count download left|[2,*] :count downloads left', $share->remainingDownloads(), ['count' => $share->remainingDownloads()]) }}
                        @else
                            {{ __('You can download this file.') }}
                        @endif
                    @else
                        {{ __('This link is for viewing only.') }}
                    @endif
                </p>
                @if($share->allow_download)
                    <a href="{{ route('documents.share.download', $share->token) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#3F5189] text-white text-sm font-medium hover:bg-[#4A5A96]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        {{ __('Download') }}
                    </a>
                @elseif(! $document->isPreviewable())
                    <span class="text-sm text-slate-500">{{ __('This file type cannot be shown in the browser.') }}</span>
                @endif
            </div>
        </div>
    @endif
</div>
