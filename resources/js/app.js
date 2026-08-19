// US phone input mask (active only when the app country is US).
// Usage: <input x-data x-phone-mask wire:model="phone">
document.addEventListener('alpine:init', () => {
    window.Alpine.directive('phone-mask', (el, _directive, { cleanup }) => {
        if (document.documentElement.dataset.country !== 'US') {
            return;
        }

        let formatting = false;

        const handler = () => {
            if (formatting) {
                return;
            }

            const value = el.value;

            // Leave international numbers as typed
            if (value.startsWith('+')) {
                return;
            }

            let digits = value.replace(/\D/g, '');

            // 11 digits with a leading 1 -> treat as the 10-digit number
            if (digits.length === 11 && digits.startsWith('1')) {
                digits = digits.slice(1);
            }

            // Cap at 10 digits so the field always holds a well-formed value
            // (longer numbers should be entered with a + prefix)
            if (digits.length > 10) {
                digits = digits.slice(0, 10);
            }

            let out = digits;
            if (digits.length > 6) {
                out = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
            } else if (digits.length > 3) {
                out = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
            } else if (digits.length > 0) {
                out = '(' + digits;
            }

            if (value !== out) {
                // Keep the caret next to the digit it was at before reformatting
                const digitsBeforeCaret = value
                    .slice(0, el.selectionStart ?? value.length)
                    .replace(/\D/g, '')
                    .length;

                formatting = true;
                el.value = out;

                let pos = 0;
                let seen = 0;
                while (pos < out.length && seen < digitsBeforeCaret) {
                    if (/\d/.test(out[pos])) {
                        seen++;
                    }
                    pos++;
                }
                try {
                    el.setSelectionRange(pos, pos);
                } catch (e) {
                    // selection API unavailable for this input type; caret falls to the end
                }

                el.dispatchEvent(new Event('input', { bubbles: true }));
                formatting = false;
            }
        };

        el.addEventListener('input', handler);
        cleanup(() => el.removeEventListener('input', handler));
    });
});

/*
 * Document repository uploader.
 *
 * Sends files straight from the browser to Cloudflare R2 using presigned
 * URLs, in parts when the file is large. Nothing but metadata passes through
 * PHP, which is what makes multi-gigabyte uploads possible at all.
 *
 * Usage: <div x-data="documentUploader(config)"> — see
 * resources/views/livewire/documents/partials/upload-modal.blade.php.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('documentUploader', (config) => ({
        config,
        items: [],
        dragging: false,
        nextId: 1,

        // How many parts of one file are pushed at the same time. Three keeps
        // a site connection saturated without starving the rest of the page.
        concurrency: 3,

        init() {
            // Files dropped on the page before the panel existed.
            if (window.__documentDroppedFiles) {
                this.addFiles(window.__documentDroppedFiles);
                window.__documentDroppedFiles = null;
            }
        },

        get busy() {
            return this.items.some((item) => item.status === 'uploading' || item.status === 'finishing');
        },

        get queued() {
            return this.items.filter((item) => item.status === 'queued');
        },

        get totalProgress() {
            const active = this.items.filter((item) => item.status !== 'error');
            if (! active.length) return 0;
            return Math.round(active.reduce((sum, item) => sum + item.progress, 0) / active.length);
        },

        formatBytes(bytes) {
            if (! bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            const value = bytes / Math.pow(1024, power);
            return `${power === 0 ? value : value.toFixed(1)} ${units[power]}`;
        },

        onDrop(event) {
            this.dragging = false;
            this.addFiles(event.dataTransfer.files);
        },

        onPick(event) {
            this.addFiles(event.target.files);
            event.target.value = '';
        },

        addFiles(fileList) {
            const allowed = this.config.accept
                .split(',')
                .map((extension) => extension.trim().toLowerCase());

            Array.from(fileList).forEach((file) => {
                const extension = '.' + (file.name.split('.').pop() || '').toLowerCase();

                const item = {
                    id: this.nextId++,
                    file,
                    name: file.name,
                    size: file.size,
                    progress: 0,
                    status: 'queued',
                    error: null,
                    versionId: null,
                    documentId: null,
                    uploadId: null,
                    key: null,
                    xhrs: [],
                };

                if (! allowed.includes(extension)) {
                    item.status = 'error';
                    item.error = this.config.messages.type;
                } else if (file.size > this.config.maxBytes) {
                    item.status = 'error';
                    item.error = this.config.messages.size;
                } else if (file.size === 0) {
                    item.status = 'error';
                    item.error = this.config.messages.empty;
                }

                this.items.push(item);
            });
        },

        remove(item) {
            if (item.status === 'uploading' || item.status === 'finishing') {
                this.cancel(item);
                return;
            }
            this.items = this.items.filter((candidate) => candidate.id !== item.id);
        },

        clearFinished() {
            this.items = this.items.filter((item) => item.status !== 'done');
        },

        async startAll() {
            for (const item of this.queued) {
                await this.upload(item);
            }
        },

        async retry(item) {
            item.status = 'queued';
            item.error = null;
            item.progress = 0;
            await this.upload(item);
        },

        async upload(item) {
            item.status = 'uploading';
            item.error = null;

            try {
                const plan = await this.post(this.config.endpoints.init, {
                    project_id: this.config.projectId,
                    job_site_id: this.config.jobSiteId,
                    folder_id: this.config.folderId,
                    document_id: this.config.documentId,
                    file_name: item.name,
                    size_bytes: item.size,
                    mime_type: item.file.type || null,
                    // Read at send time: the user may change these while a
                    // queue is still going up.
                    category: this.$wire.uploadCategory,
                    is_internal: this.$wire.uploadIsInternal,
                    notes: this.$wire.uploadVersionNotes,
                });

                item.versionId = plan.version_id;
                item.documentId = plan.document_id;
                item.uploadId = plan.upload_id;
                item.key = plan.key;

                if (plan.mode === 'single') {
                    await this.putWhole(item, plan.urls[1]);
                    await this.finish(item, []);
                } else if (plan.mode === 'multipart') {
                    const parts = await this.putParts(item, plan);
                    await this.finish(item, parts);
                } else {
                    throw new Error(this.config.messages.failed);
                }
            } catch (error) {
                if (item.status === 'cancelled') {
                    return;
                }

                item.status = 'error';
                item.error = error.message || this.config.messages.failed;

                if (item.versionId) {
                    this.post(this.config.endpoints.abort, { version_id: item.versionId }).catch(() => {});
                    item.versionId = null;
                }
            }
        },

        /**
         * One PUT for a small file, with real progress rather than a spinner.
         */
        putWhole(item, url) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                item.xhrs.push(xhr);

                xhr.open('PUT', url, true);
                if (item.file.type) {
                    xhr.setRequestHeader('Content-Type', item.file.type);
                }

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        item.progress = Math.round((event.loaded / event.total) * 100);
                    }
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        item.progress = 100;
                        resolve();
                    } else {
                        reject(new Error(this.config.messages.failed));
                    }
                };

                xhr.onerror = () => reject(new Error(this.config.messages.network));
                xhr.onabort = () => reject(new Error(this.config.messages.cancelled));
                xhr.send(item.file);
            });
        },

        /**
         * Multipart: fixed-size slices, a few at a time, each retried twice
         * before the whole upload is called a failure. R2 requires every part
         * to be the same size except the last.
         */
        async putParts(item, plan) {
            const partSize = plan.part_size;
            const partCount = plan.part_count;
            const urls = { ...plan.urls };
            const done = [];
            const loaded = new Array(partCount + 1).fill(0);

            const updateProgress = () => {
                const sum = loaded.reduce((total, value) => total + value, 0);
                item.progress = Math.min(99, Math.round((sum / item.size) * 100));
            };

            let next = 1;

            const worker = async () => {
                while (next <= partCount) {
                    const partNumber = next++;

                    if (! urls[partNumber]) {
                        const batch = [];
                        for (let n = partNumber; n < partNumber + 50 && n <= partCount; n++) {
                            if (! urls[n]) batch.push(n);
                        }

                        const signed = await this.post(this.config.endpoints.parts, {
                            version_id: item.versionId,
                            part_numbers: batch,
                        });

                        Object.assign(urls, signed.urls);
                    }

                    const start = (partNumber - 1) * partSize;
                    const blob = item.file.slice(start, Math.min(start + partSize, item.size));

                    let attempt = 0;
                    for (;;) {
                        try {
                            const etag = await this.putPart(item, urls[partNumber], blob, (bytes) => {
                                loaded[partNumber] = bytes;
                                updateProgress();
                            });
                            done.push({ PartNumber: partNumber, ETag: etag });
                            break;
                        } catch (error) {
                            if (item.status === 'cancelled') throw error;
                            if (++attempt > 2) throw error;
                            await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
                        }
                    }
                }
            };

            await Promise.all(
                Array.from({ length: Math.min(this.concurrency, partCount) }, () => worker())
            );

            return done.sort((a, b) => a.PartNumber - b.PartNumber);
        },

        putPart(item, url, blob, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                item.xhrs.push(xhr);

                xhr.open('PUT', url, true);

                xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) onProgress(event.loaded);
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        // Requires the bucket's CORS policy to expose ETag —
                        // see docs/deployment-cloudflare-r2.md §2.3.
                        const etag = xhr.getResponseHeader('ETag');
                        if (! etag) {
                            reject(new Error(this.config.messages.etag));
                            return;
                        }
                        onProgress(blob.size);
                        resolve(etag.replace(/"/g, ''));
                    } else {
                        reject(new Error(this.config.messages.failed));
                    }
                };

                xhr.onerror = () => reject(new Error(this.config.messages.network));
                xhr.onabort = () => reject(new Error(this.config.messages.cancelled));
                xhr.send(blob);
            });
        },

        async finish(item, parts) {
            item.status = 'finishing';
            item.progress = 100;

            const result = await this.post(this.config.endpoints.complete, {
                version_id: item.versionId,
                parts,
            });

            item.status = 'done';
            item.documentId = result.document_id;

            // Let the component apply tags and refresh the list.
            this.$wire.documentUploaded(result.document_id);
        },

        cancel(item) {
            item.status = 'cancelled';
            item.xhrs.forEach((xhr) => xhr.abort());

            if (item.versionId) {
                this.post(this.config.endpoints.abort, { version_id: item.versionId }).catch(() => {});
            }

            this.items = this.items.filter((candidate) => candidate.id !== item.id);
        },

        async post(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(body),
            });

            const data = await response.json().catch(() => ({}));

            if (! response.ok) {
                throw new Error(data.message || this.config.messages.failed);
            }

            return data;
        },
    }));
});

/*
 * Tag input: type a tag, press Enter, Tab or comma to commit it, Backspace on
 * an empty field to take the last one back. The Livewire side stays a plain
 * comma-separated string, so nothing server-side has to know about chips.
 *
 * Usage: <x-ui.tag-input wire:model="documentTags" :suggestions="..." />
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tagInput', (entangled, suggestions = [], max = 15) => ({
        value: entangled,
        suggestions,
        max,
        draft: '',
        focused: false,

        get tags() {
            return (this.value || '')
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean);
        },

        get full() {
            return this.tags.length >= this.max;
        },

        /** Tags not yet used, for the suggestion list. */
        get available() {
            const used = this.tags.map((tag) => tag.toLowerCase());
            const draft = this.draft.trim().toLowerCase();

            return this.suggestions
                .filter((tag) => ! used.includes(tag.toLowerCase()))
                .filter((tag) => ! draft || tag.toLowerCase().includes(draft))
                .slice(0, 8);
        },

        write(tags) {
            this.value = tags.join(', ');
        },

        add(raw) {
            const tag = (raw ?? this.draft).trim().replace(/,+$/, '').trim();
            this.draft = '';

            if (! tag || this.full) {
                return;
            }

            // Same tag twice, however it was capitalised, is still one tag.
            if (this.tags.some((existing) => existing.toLowerCase() === tag.toLowerCase())) {
                return;
            }

            this.write([...this.tags, tag]);
        },

        remove(tag) {
            this.write(this.tags.filter((existing) => existing !== tag));
        },

        onKeydown(event) {
            if (event.key === 'Enter' || event.key === ',') {
                // Enter must not submit the form the input sits in.
                event.preventDefault();
                this.add();
                return;
            }

            if (event.key === 'Tab' && this.draft.trim()) {
                // Only swallow Tab when there is something to commit —
                // otherwise it has to keep moving focus like any other field.
                event.preventDefault();
                this.add();
                return;
            }

            if (event.key === 'Backspace' && ! this.draft && this.tags.length) {
                event.preventDefault();
                const tags = this.tags;
                this.draft = tags.pop();
                this.write(tags);
            }
        },

        /** A tag typed but never committed would otherwise be lost. */
        onBlur() {
            this.focused = false;
            this.add();
        },
    }));
});

/*
 * Collapsed-sidebar flyouts.
 *
 * A 70px rail has no room for submenu items or even labels, so hovering (or
 * clicking, or tabbing to) a rail item shows them in a panel pinned to the
 * right of the rail. With the sidebar expanded the menu keeps its normal
 * inline accordion and this component stays out of the way.
 *
 * Usage: <div x-data="railFlyout" @mouseenter="rail && show()" @mouseleave="hide()">
 *        — `rail` is the layout's root-scope getter for "collapsed on desktop".
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('railFlyout', () => ({
        open: false,
        top: 0,
        timer: null,

        show() {
            clearTimeout(this.timer);
            this.open = true;
            this.place();
        },

        /**
         * Closing is deferred so the pointer can cross the gap between the
         * rail and the panel — the panel's own mouseenter cancels this.
         */
        hide() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => (this.open = false), 200);
        },

        toggle() {
            this.open ? this.hide() : this.show();
        },

        /**
         * Pin the panel to its anchor, pulled up when it would otherwise run
         * off the bottom of the window — the lowest groups have the longest
         * menus, so this is the normal case, not the edge case.
         */
        place() {
            const anchor = this.$el.getBoundingClientRect().top;
            this.top = anchor;

            this.$nextTick(() => {
                const panel = this.$refs.panel;
                if (! panel) return;

                const lowest = window.innerHeight - panel.offsetHeight - 12;
                this.top = Math.max(12, Math.min(anchor, lowest));
            });
        },
    }));
});
