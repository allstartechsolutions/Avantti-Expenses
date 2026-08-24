@props([
    'targetType',
    'targetId',
    'completed' => 'taskFileUploaded',
    'label' => null,
])

@php
    // Same transport as the document repository — the bytes go straight to
    // storage and never through PHP. Only the target differs.
    $config = [
        'endpoints' => [
            'init' => route('uploads.init'),
            'parts' => route('uploads.parts'),
            'complete' => route('uploads.complete'),
            'abort' => route('uploads.abort'),
        ],
        'fields' => ['target_type' => $targetType, 'target_id' => $targetId],
        'completedMethod' => $completed,
        'completedKey' => 'file_id',
        'droppedFilesKey' => '__taskDroppedFiles',
        'accept' => App\Services\DocumentSettings::acceptAttribute(),
        'maxBytes' => app(App\Services\FileUploadService::class)->maxBytes(),
        'partSize' => App\Services\DocumentSettings::partSize(),
        'messages' => [
            'type' => __('This file type is not allowed.'),
            'size' => __('This file is larger than the :size limit.', [
                'size' => App\Services\DocumentSettings::formatBytes(app(App\Services\FileUploadService::class)->maxBytes()),
            ]),
            'empty' => __('This file is empty.'),
            'network' => __('The connection dropped during the upload.'),
            'cancelled' => __('Upload cancelled.'),
            'failed' => __('The upload failed. Please try again.'),
            // Read by createUploader() in resources/js/app.js when the bucket
            // response carries no ETag. Without this key the user sees "undefined".
            'etag' => __('Storage did not return a file signature. Check the bucket CORS policy.'),
        ],
    ];

    $maxLabel = App\Services\DocumentSettings::formatBytes(app(App\Services\FileUploadService::class)->maxBytes());
@endphp

<div
    x-data="fileUploader(@js($config))"
    wire:ignore.self
    {{ $attributes->merge(['class' => 'space-y-3']) }}
>
    <div
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="onDrop($event); startAll()"
        :class="dragging
            ? 'border-[#3F5189] bg-[#3F5189]/5 dark:bg-[#4A5A96]/10'
            : 'border-slate-300 dark:border-slate-600'"
        class="rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors"
    >
        <svg class="mx-auto h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>

        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
            {{ $label ?? __('Drop files here, or') }}
            <label class="text-[#3F5189] dark:text-[#4A5A96] font-medium cursor-pointer hover:underline">
                {{ __('choose them') }}
                <input type="file" multiple class="hidden"
                       :accept="config.accept"
                       x-on:change="onPick($event); startAll()">
            </label>
        </p>

        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
            {{ __('Up to :size per file. Anything larger belongs in the project documents.', ['size' => $maxLabel]) }}
        </p>
    </div>

    <!-- What is going up right now -->
    <template x-if="items.length">
        <div class="space-y-2">
            <template x-for="item in items" :key="item.id">
                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-slate-700 dark:text-slate-200" x-text="item.name"></p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <span x-text="formatBytes(item.size)"></span>
                                <span x-show="item.status === 'finishing'"> · {{ __('finishing…') }}</span>
                                <span x-show="item.status === 'done'" class="text-green-600 dark:text-green-400"> · {{ __('stored') }}</span>
                            </p>
                        </div>

                        <button type="button" x-show="item.status !== 'done'" x-on:click="remove(item)"
                                class="shrink-0 text-slate-400 hover:text-red-600" title="{{ __('Cancel') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div x-show="item.status === 'uploading' || item.status === 'finishing'"
                         class="mt-2 h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-[#3F5189] dark:bg-[#4A5A96] transition-all"
                             :style="`width: ${item.progress}%`"></div>
                    </div>

                    <p x-show="item.status === 'error'" class="mt-1 text-xs text-red-600 dark:text-red-400">
                        <span x-text="item.error"></span>
                        <button type="button" x-on:click="retry(item)" class="ml-1 underline">{{ __('Try again') }}</button>
                    </p>
                </div>
            </template>
        </div>
    </template>
</div>
