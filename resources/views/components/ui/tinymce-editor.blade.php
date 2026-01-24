@props([
    'wireModel' => null,
    'id' => 'tinymce-' . uniqid(),
    'height' => 300,
])

<div
    x-data="{
        value: @entangle($wireModel ?? 'content').live,
        instance: null,
        editorId: '{{ $id }}',
        initialized: false,
        initEditor() {
            let component = this;

            console.log('Initializing TinyMCE for', this.editorId);

            // Force remove any existing instance
            if (tinymce.get(this.editorId)) {
                console.log('Removing existing editor');
                tinymce.get(this.editorId).remove();
            }

            // Wait for DOM and Livewire to be ready
            setTimeout(() => {
                const textarea = document.getElementById(component.editorId);
                if (!textarea) {
                    console.error('Textarea not found:', component.editorId);
                    return;
                }

                tinymce.init({
                    target: textarea,
                    height: {{ $height }},
                    menubar: false,
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'charmap',
                        'searchreplace', 'visualblocks', 'code',
                        'insertdatetime', 'table', 'wordcount'
                    ],
                    toolbar: 'undo redo | blocks | ' +
                        'bold italic underline | bullist numlist | ' +
                        'removeformat',
                    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif; font-size: 14px; }',
                    setup: function(editor) {
                        component.instance = editor;
                        console.log('TinyMCE setup complete');

                        editor.on('init', function() {
                            console.log('TinyMCE initialized, setting content:', component.value);
                            editor.setContent(component.value || '');
                            component.initialized = true;
                        });

                        editor.on('blur', function() {
                            component.value = editor.getContent();
                        });

                        editor.on('change', function() {
                            component.value = editor.getContent();
                        });

                        editor.on('keyup', function() {
                            component.value = editor.getContent();
                        });
                    }
                });
            }, 200);
        },
        destroyEditor() {
            console.log('Destroying TinyMCE for', this.editorId);
            if (tinymce.get(this.editorId)) {
                tinymce.get(this.editorId).remove();
            }
            this.instance = null;
            this.initialized = false;
        }
    }"
    x-init="
        // Listen for modal events from the start
    "
    @modal-opened.window="
        console.log('Modal opened event received:', $event.detail);
        if ($event.detail === 'task-modal') {
            setTimeout(() => {
                destroyEditor();
                setTimeout(() => initEditor(), 100);
            }, 100);
        }
    "
    @modal-closed.window="
        console.log('Modal closed event received:', $event.detail);
        if ($event.detail === 'task-modal') {
            destroyEditor();
        }
    ">
    <div wire:ignore>
        <textarea
            id="{{ $id }}"
            style="visibility: hidden;">{{ $slot }}</textarea>
    </div>
</div>

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.2/tinymce.min.js"></script>
@endpush
@endonce
