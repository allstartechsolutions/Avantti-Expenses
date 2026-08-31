@props([
    'document',
    'src',
    'allowWide' => true,
    'heading' => null,
])

{{--
    The preview stage for a stored file.

    A PDF in a two-thirds column is unreadable, so the stage carries its own
    controls: widen (the details column steps aside), full screen (the native
    API, so the file gets the whole monitor) and open in a new tab for anyone
    who would rather use the browser's own viewer.

    Widening is announced upwards with a `viewer-wide` event carrying the new
    state — the page decides what to hide. Full screen and the stage height
    are handled here.
--}}

@php
    $control = 'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-[#3F5189]/40';

    /*
     * The file behind this URL changes when a new version is uploaded; the URL
     * does not. Without a marker, Livewire's morph sees an <iframe> whose
     * attributes are unchanged and leaves it alone — and even if it did not,
     * the browser would answer from its own cache — so the reader goes on
     * looking at the version they opened while the history beside it already
     * lists the new one. The marker changes the src and the wire:key together,
     * which reloads the file and replaces the node.
     */
    $versionMark = $document->current_version_id;
    $stageSrc = $src.(str_contains($src, '?') ? '&' : '?').'v='.$versionMark;

    // Normal, widened and full screen. The widened height leaves room for the
    // modal's header, the card header and the sticky footer.
    $height = "fs ? 'h-full' : (wide ? 'h-[calc(100vh-14rem)] min-h-[520px]' : 'h-[70vh]')";
    $mediaHeight = "fs ? 'max-h-full' : (wide ? 'max-h-[calc(100vh-14rem)]' : 'max-h-[70vh]')";
@endphp

<div
    data-document-viewer
    x-data="{
        wide: false,
        fs: false,
        toggleWide() {
            this.wide = ! this.wide;
            this.$dispatch('viewer-wide', this.wide);
        },
        toggleFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();
                return;
            }

            const stage = this.$refs.stage;
            (stage.requestFullscreen || stage.webkitRequestFullscreen)?.call(stage);
        },
    }"
    x-on:fullscreenchange.document="fs = !! document.fullscreenElement"
    x-on:webkitfullscreenchange.document="fs = !! document.webkitFullscreenElement"
    {{ $attributes->merge(['class' => 'overflow-hidden']) }}
>
    <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $heading ?? __('Preview') }}</h3>

        @if($document->isPreviewable() && ! $document->trashed())
            <div class="flex flex-wrap items-center gap-1">
                @if($allowWide)
                    <button type="button" class="{{ $control }} hidden lg:inline-flex" x-on:click="toggleWide()"
                        x-bind:title="wide ? @js(__('Bring the details column back')) : @js(__('Give the file the full width of the page'))">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="! wide" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7l-4 5 4 5m8-10l4 5-4 5" />
                            <path x-show="wide" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7l4 5-4 5m16-10l-4 5 4 5" />
                        </svg>
                        <span x-text="wide ? @js(__('Show details')) : @js(__('Hide details'))"></span>
                    </button>
                @endif

                <button type="button" class="{{ $control }}" x-on:click="toggleFullscreen()"
                    x-bind:title="fs ? @js(__('Leave full screen (Esc)')) : @js(__('Show this file on the whole screen'))">
                    <svg x-show="! fs" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                    <svg x-show="fs" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4" />
                    </svg>
                    <span x-text="fs ? @js(__('Exit full screen')) : @js(__('Full screen'))"></span>
                </button>

                <a href="{{ $src }}" target="_blank" rel="noopener" class="{{ $control }}" title="{{ __('Open the file in a browser tab of its own') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    <span class="hidden sm:inline">{{ __('Open in new tab') }}</span>
                </a>
            </div>
        @endif
    </div>

    <div x-ref="stage" class="relative bg-slate-100 dark:bg-slate-900/60"
        x-bind:class="fs ? 'h-screen w-screen bg-slate-900 flex flex-col items-center justify-center' : ''">

        {{-- Full screen hides the toolbar above, so the way out travels with the file. --}}
        <button type="button" x-show="fs" x-cloak x-on:click="toggleFullscreen()"
            class="absolute top-4 right-4 z-10 inline-flex items-center gap-1.5 rounded-lg bg-slate-900/80 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-900">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9L4 4m0 0v4m0-4h4m7 5l5-5m0 0v4m0-4h-4M9 15l-5 5m0 0v-4m0 4h4m7-5l5 5m0 0v-4m0 4h-4" />
            </svg>
            {{ __('Exit full screen') }}
        </button>

        @if($document->trashed())
            <div class="px-6 py-16 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('This document is in the trash. Restore it to open the file again.') }}</p>
            </div>
        @elseif($document->isPdf())
            <iframe wire:key="preview-pdf-{{ $document->id }}-{{ $versionMark }}"
                src="{{ $stageSrc }}" title="{{ $document->name }}" class="w-full border-0"
                x-bind:class="{{ $height }}"></iframe>
        @elseif($document->isImage())
            <div class="flex w-full items-center justify-center p-4" x-bind:class="fs ? 'h-full p-0' : ''">
                <img wire:key="preview-image-{{ $document->id }}-{{ $versionMark }}"
                    src="{{ $stageSrc }}" alt="{{ $document->name }}" class="w-auto rounded"
                    x-bind:class="{{ $mediaHeight }}">
            </div>
        @elseif($document->isVideo())
            <video wire:key="preview-video-{{ $document->id }}-{{ $versionMark }}"
                controls preload="metadata" class="w-full bg-black" x-bind:class="{{ $mediaHeight }}">
                <source src="{{ $stageSrc }}" type="{{ $document->current_mime_type }}">
            </video>
        @else
            <div class="px-6 py-16 text-center">
                <x-document-icon :document="$document" size="w-12 h-12 mx-auto" />
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ __('This file type cannot be shown in the browser.') }}</p>
                @if($slot->isNotEmpty())
                    <div class="mt-4">{{ $slot }}</div>
                @endif
            </div>
        @endif
    </div>
</div>
