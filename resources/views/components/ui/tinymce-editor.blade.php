@props([
    'wireModel' => null,
    'id' => 'tinymce-' . uniqid(),
    'height' => 300,
    'modalName' => 'task-modal',
    // Opt-in: adds the image button and uploads what is dropped in to cloud
    // storage. Off by default so the screens already using this editor keep
    // exactly the toolbar they had.
    'uploads' => false,
    'uploadUrl' => null,
    'uploadContext' => null,
])

<div
    x-data="{
        value: @entangle($wireModel ?? 'content').live,
        instance: null,
        editorId: '{{ $id }}',
        initialized: false,
        loadTinymce() {
            return new Promise((resolve) => {
                if (typeof tinymce !== 'undefined') {
                    resolve();
                    return;
                }
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.2/tinymce.min.js';
                script.onload = () => resolve();
                document.head.appendChild(script);
            });
        },
        initEditor() {
            let component = this;

            this.loadTinymce().then(() => {
                // Force remove any existing instance
                if (tinymce.get(component.editorId)) {
                    tinymce.get(component.editorId).remove();
                }

                // Wait for DOM to be ready
                setTimeout(() => {
                    const textarea = document.getElementById(component.editorId);
                    if (!textarea) {
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
                            @if($uploads), 'image' @endif
                        ],
                        toolbar: 'undo redo | blocks | ' +
                            'bold italic underline | bullist numlist | ' +
                            @if($uploads) 'link image table | ' + @endif
                            'removeformat',
@if($uploads)
                        // Wrap rather than hide: a guide editor is narrow, and
                        // TinyMCE otherwise buries the image button behind an
                        // overflow chevron nobody finds.
                        toolbar_mode: 'wrap',
                        automatic_uploads: true,
                        file_picker_types: 'image',
                        images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
                            const body = new FormData();
                            body.append('file', blobInfo.blob(), blobInfo.filename());
                            @if($uploadContext)
                                body.append('article_id', '{{ $uploadContext }}');
                            @endif

                            fetch('{{ $uploadUrl }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content ?? '',
                                },
                                body,
                            })
                            .then(async (response) => {
                                const data = await response.json().catch(() => ({}));

                                if (! response.ok) {
                                    // TinyMCE shows this to the person writing.
                                    reject({ message: data.message || 'Upload failed', remove: true });
                                    return;
                                }

                                resolve(data.location);
                            })
                            .catch(() => reject({ message: 'Upload failed', remove: true }));
                        }),
@endif
                        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif; font-size: 14px; }',
                        setup: function(editor) {
                            component.instance = editor;

                            editor.on('init', function() {
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
            });
        },
        destroyEditor() {
            if (typeof tinymce !== 'undefined' && tinymce.get(this.editorId)) {
                tinymce.get(this.editorId).remove();
            }
            this.instance = null;
            this.initialized = false;
        }
    }"
    x-init="
        // Auto-initialize when component is mounted into DOM
        $nextTick(() => { initEditor(); });
    "
    @modal-opened.window="
        if ($event.detail === '{{ $modalName }}') {
            setTimeout(() => {
                destroyEditor();
                setTimeout(() => initEditor(), 100);
            }, 100);
        }
    "
    @modal-closed.window="
        if ($event.detail === '{{ $modalName }}') {
            destroyEditor();
        }
    ">
    <div wire:ignore>
        <textarea
            id="{{ $id }}"
            style="visibility: hidden;">{{ $slot }}</textarea>
    </div>
</div>

