{{--
    Document detail — full page. Shows every stored field, the whole version
    history and the audit trail, not a subset.
--}}
@php
    $document = $this->viewingDocument;
@endphp

<x-ui.modal name="document-detail-modal" maxWidth="full" layer="top">
    @if($document)
        @php
            $current = $document->currentVersion;
            $canWrite = $this->canManageDocuments() && ! $document->trashed();
            $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
            $term = 'text-xs font-medium text-slate-500 dark:text-slate-400';
            $value = 'text-sm text-slate-900 dark:text-white break-words';
        @endphp

        <div class="flex min-h-screen flex-col">
            {{-- Header --}}
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-document-icon :document="$document" size="w-8 h-8" />
                        <div class="min-w-0">
                            <h2 class="text-xl font-semibold text-slate-900 dark:text-white truncate">{{ $document->name }}</h2>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                <x-document-category-badge :category="$document->category" />
                                @if($document->is_internal)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">{{ __('Internal') }}</span>
                                @endif
                                @if($document->trashed())
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">{{ __('In the trash') }}</span>
                                @endif
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $document->formattedSize() }} · {{ __('version :number', ['number' => $document->current_version_number]) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @unless($document->trashed())
                            <x-ui.button variant="secondary" size="sm" icon="download" href="{{ route('documents.download', $document) }}">
                                {{ __('Download') }}
                            </x-ui.button>
                            @if($canWrite)
                                <x-ui.button variant="secondary" size="sm" wire:click="openShareModal({{ $document->id }})">
                                    {{ __('Share') }}
                                </x-ui.button>
                                <x-ui.button variant="secondary" size="sm" wire:click="openUploadModal({{ $document->id }})">
                                    {{ __('New Version') }}
                                </x-ui.button>
                                <x-ui.button variant="primary" size="sm" icon="edit" wire:click="openEditModal({{ $document->id }})">
                                    {{ __('Edit') }}
                                </x-ui.button>
                            @endif
                        @endunless
                        <button type="button" wire:click="closeDetail" title="{{ __('Close') }}"
                            class="ml-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 bg-slate-50 dark:bg-slate-900">
                {{-- `wide` is set by the preview stage when the user asks the file for more room. --}}
                <div class="mx-auto max-w-7xl px-6 py-6 space-y-6"
                     x-data="{ wide: false }"
                     x-on:viewer-wide="wide = $event.detail">

                    @if(session('error'))
                        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {{-- Preview --}}
                        <x-document-preview
                            :document="$document"
                            :src="route('documents.preview', $document)"
                            class="lg:col-span-2 {{ $card }} overflow-hidden"
                            x-bind:class="wide ? 'lg:col-span-3' : ''">
                            <x-ui.button variant="secondary" size="sm" icon="download" href="{{ route('documents.download', $document) }}">
                                {{ __('Download to open it') }}
                            </x-ui.button>
                        </x-document-preview>

                        {{-- Every stored field --}}
                        <div class="{{ $card }}" x-show="! wide">
                            <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Details') }}</h3>
                            </div>
                            <dl class="p-5 space-y-4">
                                <div>
                                    <dt class="{{ $term }}">{{ __('Description') }}</dt>
                                    <dd class="{{ $value }}">{{ $document->description ?: __('None') }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $term }}">{{ __('Location') }}</dt>
                                    <dd class="{{ $value }}">{{ $document->locationLabel() }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $term }}">{{ __('Folder') }}</dt>
                                    <dd class="{{ $value }}">
                                        @if($document->folder)
                                            {{ $document->folder->parent ? $document->folder->parent->name.' / ' : '' }}{{ $document->folder->name }}
                                        @else
                                            {{ __('No folder (root)') }}
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="{{ $term }}">{{ __('Tags') }}</dt>
                                    <dd class="mt-1 flex flex-wrap gap-1">
                                        @forelse($document->tags as $tag)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $tag->name }}</span>
                                        @empty
                                            <span class="{{ $value }}">{{ __('None') }}</span>
                                        @endforelse
                                    </dd>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="{{ $term }}">{{ __('Size') }}</dt>
                                        <dd class="{{ $value }}">{{ $document->formattedSize() }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $term }}">{{ __('Versions') }}</dt>
                                        <dd class="{{ $value }}">{{ $document->availableVersions->count() }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $term }}">{{ __('File type') }}</dt>
                                        <dd class="{{ $value }}">{{ $document->current_mime_type ?: strtoupper($document->extension()) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $term }}">{{ __('Visibility') }}</dt>
                                        <dd class="{{ $value }}">{{ $document->is_internal ? __('Internal only') : __('Everyone with access to this project') }}</dd>
                                    </div>
                                </div>
                                @if($current)
                                    <div>
                                        <dt class="{{ $term }}">{{ __('Stored as') }}</dt>
                                        <dd class="text-xs text-slate-500 dark:text-slate-400 break-all">{{ $current->object_key }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $term }}">{{ __('Storage / checksum') }}</dt>
                                        <dd class="text-xs text-slate-500 dark:text-slate-400 break-all">
                                            {{ strtoupper($current->disk) }} · {{ $current->checksum ?: __('not recorded') }}
                                        </dd>
                                    </div>
                                @endif

                                <div class="pt-3 border-t border-slate-200 dark:border-slate-700 space-y-3">
                                    <div>
                                        <dt class="{{ $term }}">{{ __('Uploaded by') }}</dt>
                                        <dd class="{{ $value }}">
                                            {{ $document->uploadedBy?->name ?? __('unknown') }}
                                            <span class="text-slate-500 dark:text-slate-400">· {{ $document->created_at->format('d/m/Y H:i') }}</span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $term }}">{{ __('Last updated') }}</dt>
                                        <dd class="{{ $value }}">
                                            {{ $document->updatedBy?->name ?? __('unknown') }}
                                            <span class="text-slate-500 dark:text-slate-400">· {{ $document->updated_at->format('d/m/Y H:i') }}</span>
                                        </dd>
                                    </div>
                                    @if($document->trashed())
                                        <div>
                                            <dt class="{{ $term }}">{{ __('Deleted by') }}</dt>
                                            <dd class="{{ $value }}">
                                                {{ $document->deletedBy?->name ?? __('unknown') }}
                                                <span class="text-slate-500 dark:text-slate-400">· {{ $document->deleted_at->format('d/m/Y H:i') }}</span>
                                            </dd>
                                        </div>
                                    @endif
                                </div>
                            </dl>
                        </div>
                    </div>

                    {{-- Version history --}}
                    <div class="{{ $card }}">
                        <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Version history') }}</h3>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Every upload is kept. Restoring an older version adds it to the top rather than overwriting anything.') }}
                            </span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-900/40">
                                    <tr>
                                        <th class="px-5 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Version') }}</th>
                                        <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('File') }}</th>
                                        <th class="px-3 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Size') }}</th>
                                        <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Notes') }}</th>
                                        <th class="px-3 py-2.5 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Uploaded by') }}</th>
                                        <th class="px-5 py-2.5 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                    @foreach($document->versions as $version)
                                        <tr class="{{ $version->id === $document->current_version_id ? 'bg-[#3F5189]/5 dark:bg-[#3F5189]/15' : '' }}">
                                            <td class="px-5 py-3 whitespace-nowrap">
                                                <span class="text-sm font-medium text-slate-900 dark:text-white">v{{ $version->version_number }}</span>
                                                @if($version->id === $document->current_version_id)
                                                    <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-[#3F5189] text-white">{{ __('Current') }}</span>
                                                @elseif(! $version->isAvailable())
                                                    <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                        {{ $version->isPending() ? __('Uploading') : __('Failed') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300 max-w-xs truncate">{{ $version->original_name }}</td>
                                            <td class="px-3 py-3 text-sm text-right text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ $version->formattedSize() }}</td>
                                            <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $version->notes ?: '—' }}</td>
                                            <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                                {{ $version->uploadedBy?->name ?? __('unknown') }}
                                                <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $version->created_at->format('d/m/Y H:i') }}</span>
                                            </td>
                                            <td class="px-5 py-3">
                                                <div class="flex items-center justify-end gap-2">
                                                    @if($version->isAvailable() && ! $document->trashed())
                                                        <a href="{{ route('documents.versions.download', [$document, $version]) }}"
                                                           class="text-sm text-[#3F5189] dark:text-[#8B9DD6] hover:underline">{{ __('Download') }}</a>
                                                        @if($canWrite && $version->id !== $document->current_version_id)
                                                            <button type="button" wire:click="restoreVersion({{ $version->id }})"
                                                                wire:confirm="{{ __('Make version :number the current one? It is copied to a new version; nothing is overwritten.', ['number' => $version->version_number]) }}"
                                                                class="text-sm text-slate-600 dark:text-slate-300 hover:underline">{{ __('Make current') }}</button>
                                                        @endif
                                                    @else
                                                        <span class="text-sm text-slate-400 dark:text-slate-500">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Share links --}}
                    @php($activeShares = $document->shares->sortByDesc('created_at'))
                    @if($activeShares->isNotEmpty())
                        <div class="{{ $card }}">
                            <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Share links') }}</h3>
                                @if($canWrite)
                                    <button type="button" wire:click="openShareModal({{ $document->id }})"
                                        class="text-sm text-[#3F5189] dark:text-[#8B9DD6] hover:underline">{{ __('Manage') }}</button>
                                @endif
                            </div>
                            <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($activeShares as $share)
                                    <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <span class="text-sm text-slate-900 dark:text-white">{{ $share->recipient_label ?: __('Unnamed link') }}</span>
                                            @if($share->isUsable())
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">{{ __('Active') }}</span>
                                            @else
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                    {{ $share->isRevoked() ? __('Revoked') : ($share->isExpired() ? __('Expired') : __('Limit reached')) }}
                                                </span>
                                            @endif
                                        </div>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">
                                            @if($share->expires_at) {{ __('Expires :date', ['date' => $share->expires_at->format('d/m/Y')]) }} @else {{ __('No expiry') }} @endif
                                            · {{ trans_choice('{0} never opened|{1} :count download|[2,*] :count downloads', $share->download_count, ['count' => $share->download_count]) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Audit trail --}}
                    <div class="{{ $card }}">
                        <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('History') }}</h3>
                        </div>
                        @if($document->activities->isEmpty())
                            <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing recorded yet.') }}</p>
                        @else
                            <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($document->activities->take(50) as $activity)
                                    <li class="px-5 py-3 flex flex-wrap items-baseline justify-between gap-2">
                                        <div class="min-w-0">
                                            <span class="text-sm text-slate-900 dark:text-white">{{ $activity->label() }}</span>
                                            @if($activity->context)
                                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                                    @if(isset($activity->context['version'])) · {{ __('version :number', ['number' => $activity->context['version']]) }} @endif
                                                    @if(isset($activity->context['restored_from'])) · {{ __('from version :number', ['number' => $activity->context['restored_from']]) }} @endif
                                                    @if(isset($activity->context['from'], $activity->context['to'])) · {{ $activity->context['from'] }} → {{ $activity->context['to'] }} @endif
                                                    @if(isset($activity->context['tags']) && $activity->context['tags']) · {{ implode(', ', $activity->context['tags']) }} @endif
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                            {{ $activity->user?->name ?? __('a share link') }}
                                            · {{ $activity->created_at->format('d/m/Y H:i') }}
                                            @if($activity->ip_address) · {{ $activity->ip_address }} @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            @if($document->activities->count() > 50)
                                <p class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-700">
                                    {{ __('Showing the 50 most recent of :count entries.', ['count' => $document->activities->count()]) }}
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Download links expire after :seconds seconds.', ['seconds' => \App\Services\DocumentSettings::presignTtl()]) }}
                    </p>
                    <x-ui.button variant="secondary" wire:click="closeDetail">{{ __('Close') }}</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</x-ui.modal>
