{{-- The upload / renew, history and archive dialogs for the documents tab (ManagesVendorDocuments). --}}
{{-- Chip colours, labels and grants (same as the subcontractor page). --}}
@php
    $expiryChip = [
        'expired' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'expiring_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'valid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'undated' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'missing' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
    $expiryDot = [
        'expired' => 'bg-red-500',
        'expiring_soon' => 'bg-amber-500',
        'valid' => 'bg-green-500',
        'undated' => 'bg-slate-400',
        'missing' => 'bg-slate-400',
    ];
    $expiryText = fn (string $state) => $state === 'missing'
        ? __('Missing')
        : \App\Models\SubcontractorDocument::expiryStatusLabel($state);
    $stageText = fn ($stage) => $stage === 'expired' ? __('the day after') : __(':days days before', ['days' => $stage]);
    $canRenew = auth()->user()->can('vendors.renew_documents');
    $canArchive = auth()->user()->can('vendors.archive_documents');
    $canDelete = auth()->user()->can('vendors.delete');
@endphp
    <!-- Upload / Renew Document -->
    @if($showUploadForm)
        @php $renewing = $this->renewingDocument; @endphp
        <x-ui.modal name="document-form-modal" :show="true" maxWidth="2xl">
            <form wire:submit="uploadDocument">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ $renewing ? __('Renew Document') : __('Upload Document') }}
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $documentVendorName }}</p>
                    </div>
                    <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="cancelUploadForm" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
                </div>

                <div class="p-6 space-y-4">
                    @if($renewing)
                        <div class="rounded-lg border border-[#3F5189]/30 bg-[#3F5189]/5 dark:bg-[#3F5189]/15 px-4 py-3 text-sm">
                            <p class="font-medium text-slate-900 dark:text-white">{{ __('Renewing :type', ['type' => __($renewing->documentType->name)]) }}</p>
                            <p class="text-slate-600 dark:text-slate-300 mt-0.5">
                                @if($renewing->expiration_date)
                                    {{ __('Replaces :file, expiring :date', ['file' => $renewing->file_name, 'date' => $renewing->expiration_date->appDate()]) }}
                                @else
                                    {{ __('Replaces :file', ['file' => $renewing->file_name]) }}
                                @endif
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('The replaced document stays in the history. The type is kept from the document being renewed.') }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Document Type -->
                        <div>
                            <label for="document_type_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Document Type') }} <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="document_type_id"
                                wire:model.live="document_type_id"
                                @if($renewing) disabled @endif
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white disabled:opacity-60">
                                <option value="">{{ __('Select document type...') }}</option>
                                @foreach($documentTypes as $type)
                                    <option value="{{ $type->id }}">
                                        {{ __($type->name) }}@if($type->requires_expiration) ({{ __('requires expiration') }})@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('document_type_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Expiration Date -->
                        <div>
                            <label for="expiration_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Expiration Date') }}
                                @if($this->selectedDocumentType && $this->selectedDocumentType->requires_expiration)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <x-ui.date-input id="expiration_date" wire:model="expiration_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                            @error('expiration_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- File -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Document File') }} <span class="text-red-500">*</span>
                        </label>

                        @if(\App\Services\DocumentSettings::isCloudConfigured())
                            {{-- Straight to storage, never through PHP: the file goes up now and the row is written on save. --}}
                            @php $pending = $this->pendingFile; @endphp
                            @if($pending)
                                <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-green-200 dark:border-green-900/50 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                    <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">{{ $pending->original_name }}</span>
                                    <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $pending->formattedSize() }} &middot; {{ __('stored') }}</span>
                                    <x-ui.icon-button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        type="button"
                                        wire:click="discardPendingFile"
                                        title="{{ __('Remove :file', ['file' => $pending->original_name]) }}"
                                        aria-label="{{ __('Remove :file', ['file' => $pending->original_name]) }}"
                                        class="hover:text-red-600 dark:hover:text-red-400" />
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Drop another file to replace it.') }}</p>
                            @endif
                            <div class="{{ $pending ? 'mt-2' : '' }}" wire:key="vendor-uploader-{{ $documentVendorId }}-{{ $renewing_document_id ?? 'new' }}">
                                <x-ui.file-uploader targetType="vendor" :targetId="$documentVendorId" completed="documentFileUploaded" :label="__('Drop the document here, or')" />
                            </div>
                        @else
                            {{-- No bucket on this install: through Livewire, stored on save. --}}
                            <x-ui.file-drop
                                wire:model="document_file"
                                :multiple="false"
                                :label="__('Drop the document here, or')">

                                @if($document_file)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                        <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                            {{ $document_file->getClientOriginalName() }}
                                        </span>
                                        <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                            {{ \App\Services\DocumentSettings::formatBytes($document_file->getSize()) }}
                                        </span>
                                        <x-ui.icon-button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            type="button"
                                            wire:click="clearDocumentFile"
                                            title="{{ __('Remove :file', ['file' => $document_file->getClientOriginalName()]) }}"
                                            aria-label="{{ __('Remove :file', ['file' => $document_file->getClientOriginalName()]) }}"
                                            class="hover:text-red-600 dark:hover:text-red-400" />
                                    </div>
                                @endif
                            </x-ui.file-drop>
                        @endif

                        @error('document_file') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="document_notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Notes') }}
                        </label>
                        <textarea
                            id="document_notes"
                            wire:model="document_notes"
                            rows="2"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Optional notes about this document...') }}"
                        ></textarea>
                        @error('document_notes') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelUploadForm" icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        icon="upload"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        wire:target="document_file,uploadDocument">
                        <span wire:loading.remove wire:target="document_file,uploadDocument">{{ $renewing ? __('Renew Document') : __('Upload Document') }}</span>
                        <span wire:loading wire:target="document_file">{{ __('Uploading file...') }}</span>
                        <span wire:loading wire:target="uploadDocument">{{ __('Saving...') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <!-- Document history -->
    @if($historyDocument)
        <x-ui.modal name="document-history-modal" :show="true" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white break-all">{{ __('Document history') }} — {{ $historyDocument->file_name }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ __($historyDocument->documentType->name) }} &middot; {{ $documentVendorName }} &middot;
                        {{ trans_choice('This document and :count earlier version, newest first.|This document and :count earlier versions, newest first.', $historyEntries->count() - 1, ['count' => $historyEntries->count() - 1]) }}
                    </p>
                </div>
                <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="closeHistory" title="{{ __('Close') }}" aria-label="{{ __('Close') }}" />
            </div>

            <div class="max-h-[70vh] overflow-y-auto">
                <ol class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($historyEntries as $entry)
                        @php
                            $lifecycleChip = match ($entry->status) {
                                'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                'archived' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                            };
                        @endphp
                        <li class="px-6 py-4 {{ $entry->isActive() ? '' : 'bg-slate-50/60 dark:bg-slate-900/30' }}" wire:key="hist-{{ $entry->id }}">
                            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $lifecycleChip }}">{{ $entry->status_label }}</span>
                                        @if($entry->isActive() && $entry->documentType->requires_expiration && $entry->documentType->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$entry->expiry_status] }}">{{ $entry->expiry_status_label }}</span>
                                        @endif
                                        <p class="text-sm font-medium text-slate-900 dark:text-white break-all">{{ $entry->file_name }}</p>
                                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $entry->formatted_file_size }}</span>
                                    </div>

                                    <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-xs">
                                        <div class="flex gap-2">
                                            <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Expiration') }}</dt>
                                            <dd class="text-slate-900 dark:text-white">
                                                @if($entry->expiration_date)
                                                    {{ $entry->expiration_date->appDate() }}
                                                    @php $days = $entry->days_until_expiry; @endphp
                                                    <span class="text-slate-500 dark:text-slate-400">
                                                        &middot;
                                                        @if($days === 0) {{ __('Expires today') }}
                                                        @elseif($days > 0) {{ trans_choice('Expires in :count day|Expires in :count days', $days, ['count' => $days]) }}
                                                        @else {{ trans_choice('Expired :count day ago|Expired :count days ago', abs($days), ['count' => abs($days)]) }}
                                                        @endif
                                                    </span>
                                                @else
                                                    {{ __('No expiration') }}
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="flex gap-2">
                                            <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Uploaded') }}</dt>
                                            <dd class="text-slate-900 dark:text-white">{{ __('by :name', ['name' => $entry->uploadedBy->name ?? __('Unknown')]) }} &middot; {{ $entry->created_at->appDateTime() }}</dd>
                                        </div>
                                        @if($entry->supersedes)
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Replaced') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">{{ $entry->supersedes->file_name }}</dd>
                                            </div>
                                        @endif
                                        @if($entry->isSuperseded() && $entry->supersededBy)
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Replaced by') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">{{ $entry->supersededBy->file_name }} &middot; {{ $entry->supersededBy->created_at->appDateTime() }}</dd>
                                            </div>
                                        @endif
                                        @if($entry->isArchived())
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Archived') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">
                                                    {{ __('by :name', ['name' => $entry->archivedBy->name ?? __('Unknown')]) }} &middot; {{ $entry->archived_at?->appDateTime() ?? '—' }}
                                                    @if($entry->archive_reason)
                                                        &middot; <span class="italic">{{ $entry->archive_reason }}</span>
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif
                                        @if($entry->reminded_stages !== [])
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Reminders') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">{{ collect($entry->reminded_stages)->map($stageText)->join(', ') }}</dd>
                                            </div>
                                        @endif
                                        @if($entry->notes)
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Notes') }}</dt>
                                                <dd class="text-slate-900 dark:text-white whitespace-pre-line">{{ $entry->notes }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>

                                <div class="flex items-center gap-1 sm:justify-end shrink-0">
                                    <x-ui.icon-button variant="ghost" size="sm" icon="download" href="{{ $entry->downloadUrl() }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}" />
                                    @if($entry->isActive() && $canRenew)
                                        <x-ui.button variant="ghost" size="sm" wire:click="startRenewal({{ $entry->id }})">{{ __('Renew') }}</x-ui.button>
                                    @endif
                                    @if($entry->isActive() && $canArchive)
                                        <x-ui.button variant="ghost" size="sm" wire:click="startArchive({{ $entry->id }})">{{ __('Archive') }}</x-ui.button>
                                    @endif
                                    @if($entry->isArchived() && $canArchive)
                                        <x-ui.button variant="ghost" size="sm" wire:click="reactivateDocument({{ $entry->id }})">{{ __('Reactivate') }}</x-ui.button>
                                    @endif
                                    @if($canDelete)
                                        <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="deleteDocument({{ $entry->id }})" wire:confirm="{{ __('Are you sure you want to delete this document?') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" class="hover:text-red-600 dark:hover:text-red-400" />
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                <x-ui.button type="button" variant="secondary" wire:click="closeHistory" icon="x">{{ __('Close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    <!-- Archive Document -->
    @if($archiving_document_id)
        <x-ui.modal name="archive-document-modal" :show="true" maxWidth="lg">
            <form wire:submit="archiveDocument" class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">{{ __('Archive Document') }}</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                    {{ __('Archiving takes this document out of the expiry watch: no badge, no reminder e-mails. It stays here in the history and can be reactivated.') }}
                </p>

                <label for="archive_reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Reason') }} <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="archive_reason"
                    wire:model="archive_reason"
                    rows="3"
                    maxlength="255"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                    placeholder="{{ __('Why is this document no longer required?') }}"
                ></textarea>
                @error('archive_reason') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                <div class="flex justify-end gap-3 mt-6">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelArchive" icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit" variant="warning">
                        {{ __('Archive Document') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
