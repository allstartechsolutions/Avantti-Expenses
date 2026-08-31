@props([
    'label' => null,        // the line inside the zone
    'hint' => null,         // replaces the default "up to :size per file" line
    'accept' => null,       // defaults to whatever the document settings allow
    'multiple' => true,
    'disabled' => false,
])

@php
    // The Livewire property the files land on, so the zone can show its own
    // progress and disable itself while an upload is in flight.
    $model = $attributes->wire('model')->value();
    $uid = 'file-drop-'.\Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', (string) $model));

    // An explicit empty string means "take anything" — a field whose server
    // rule has no type restriction must not be narrowed by its picker.
    $accept = $accept === null ? App\Services\DocumentSettings::acceptAttribute() : $accept;
    $maxLabel = App\Services\DocumentSettings::formatBytes(app(App\Services\FileUploadService::class)->maxBytes());
@endphp

{{--
    Drop zone for a form's own uploads — the ones held against a record that
    may not exist yet, so they travel with `wire:model` and are stored when the
    form saves.

    For files attached to a record that already exists, use
    `<x-ui.file-uploader>` instead: it sends the bytes straight to storage and
    never through PHP, which is what makes a 2 GB drawing possible.

    Dropping and choosing are the same act — the drop hands its FileList to the
    hidden input and lets Livewire do the rest, so nothing here needs to know
    how uploads work.
--}}
<div {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'space-y-3']) }}
     x-data="{
         dragging: false,
         uploading: false,
         progress: 0,
         multiple: {{ $multiple ? 'true' : 'false' }},
         notice: null,
         drop(event) {
             this.dragging = false;
             this.notice = null;

             // A one-file field takes the first and says so, rather than
             // letting Livewire drop the rest without a word.
             let files = Array.from(event.dataTransfer.files);

             if (! this.multiple && files.length > 1) {
                 files = files.slice(0, 1);
                 this.notice = @js(__('This field takes one file, so only the first was kept.'));
             }

             const dropped = new DataTransfer();
             files.forEach((file) => dropped.items.add(file));

             if (! dropped.files.length) { return; }

             // Handing the files to the input and firing its change event is
             // all Livewire needs; a dropped file and a chosen one then follow
             // exactly the same path.
             this.$refs.input.files = dropped.files;
             this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
         },
     }"
     x-on:livewire-upload-start="uploading = true; progress = 0"
     x-on:livewire-upload-finish="uploading = false; progress = 100"
     x-on:livewire-upload-cancel="uploading = false"
     x-on:livewire-upload-error="uploading = false"
     x-on:livewire-upload-progress="progress = $event.detail.progress">

    <label for="{{ $uid }}"
           @dragover.prevent="dragging = true"
           @dragenter.prevent="dragging = true"
           @dragleave.prevent="dragging = false"
           @drop.prevent="drop($event)"
           :class="dragging
               ? 'border-[#3F5189] bg-[#3F5189]/5 dark:bg-[#4A5A96]/10'
               : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500'"
           @class([
               'block rounded-lg border-2 border-dashed px-4 py-6 text-center transition-colors focus-within:ring-2 focus-within:ring-[#3F5189]',
               'cursor-pointer' => ! $disabled,
               'opacity-60 pointer-events-none' => $disabled,
           ])>

        <svg class="mx-auto h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.9A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>

        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
            {{ $label ?? __('Drop files here, or') }}
            <span class="text-[#3F5189] dark:text-[#8B9DD6] font-medium hover:underline">{{ __('choose them') }}</span>
        </p>

        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
            {{ $hint ?? __('Up to :size per file.', ['size' => $maxLabel]) }}
        </p>

        <input id="{{ $uid }}"
               x-ref="input"
               type="file"
               @if($multiple) multiple @endif
               @if($disabled) disabled @endif
               @if($accept !== '') accept="{{ $accept }}" @endif
               {{ $attributes->whereStartsWith('wire:model') }}
               class="sr-only">
    </label>

    <p x-show="notice" x-cloak class="text-xs text-amber-600 dark:text-amber-400" x-text="notice"></p>

    {{-- What Livewire is carrying up right now --}}
    <div x-show="uploading" x-cloak class="space-y-1">
        <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
            <div class="h-full rounded-full bg-[#3F5189] dark:bg-[#4A5A96] transition-all" :style="`width: ${progress}%`"></div>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            {{ __('Uploading...') }} <span x-text="progress + '%'"></span>
        </p>
    </div>

    {{ $slot }}
</div>
