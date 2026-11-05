{{--
    A vendor's compliance documents (ManagesVendorDocuments). Shared by the
    subcontractor and supplier pages; expects $documentVendorId and
    $documentVendorName plus the trait's documentViewData().
--}}
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

            <div class="space-y-6">
                <!-- Required documents: every type that asks for a date, and where this vendor stands -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Required documents') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every type that requires an expiration date, and where this vendor stands on each. Only active documents count toward the badge and the reminders.') }}</p>
                    </div>
                    <div class="p-6">
                        @if($requiredTypes->isEmpty())
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No document type requires an expiration date, so there is nothing to track here.') }}</p>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($requiredTypes as $required)
                                    <div class="flex items-start justify-between gap-3 rounded-lg border {{ $required['status'] === 'missing' ? 'border-dashed' : '' }} border-slate-200 dark:border-slate-700 px-4 py-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block w-2 h-2 rounded-full shrink-0 {{ $expiryDot[$required['status']] }}"></span>
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ __($required['type']->name) }}</p>
                                            </div>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 pl-4">
                                                @if($required['document'] && $required['document']->expiration_date)
                                                    {{ $required['document']->expiration_date->appDate() }}
                                                    @php $days = $required['document']->days_until_expiry; @endphp
                                                    &middot;
                                                    @if($days === 0)
                                                        {{ __('Expires today') }}
                                                    @elseif($days > 0)
                                                        {{ trans_choice('Expires in :count day|Expires in :count days', $days, ['count' => $days]) }}
                                                    @else
                                                        {{ trans_choice('Expired :count day ago|Expired :count days ago', abs($days), ['count' => abs($days)]) }}
                                                    @endif
                                                @elseif($required['document'])
                                                    {{ __('On file without an expiration date — renew it with one.') }}
                                                @else
                                                    {{ __('Nothing on file for this type.') }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="shrink-0 flex flex-col items-end gap-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$required['status']] }}">{{ $expiryText($required['status']) }}</span>
                                            @if($canRenew)
                                                @if($required['status'] === 'missing')
                                                    <button type="button" wire:click="startUpload({{ $required['type']->id }})" class="text-xs font-medium text-[#3F5189] hover:underline dark:text-[#8b9ad1]">{{ __('Upload') }}</button>
                                                @elseif($required['status'] !== 'valid')
                                                    <button type="button" wire:click="startRenewal({{ $required['document']->id }})" class="text-xs font-medium text-[#3F5189] hover:underline dark:text-[#8b9ad1]">{{ __('Renew') }}</button>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Documents on file -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Documents') }}</h3>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                <span>{{ trans_choice(':count active document|:count active documents', $documentCounts['active'], ['count' => $documentCounts['active']]) }}</span>
                                @if($documentCounts['expiring'] > 0)
                                    <span class="text-amber-700 dark:text-amber-400">{{ trans_choice(':count expiring soon|:count expiring soon', $documentCounts['expiring'], ['count' => $documentCounts['expiring']]) }}</span>
                                @endif
                                @if($documentCounts['expired'] > 0)
                                    <span class="text-red-700 dark:text-red-400">{{ trans_choice(':count expired|:count expired', $documentCounts['expired'], ['count' => $documentCounts['expired']]) }}</span>
                                @endif
                                @if($documentCounts['history'] > 0)
                                    <span>{{ trans_choice(':count in history|:count in history', $documentCounts['history'], ['count' => $documentCounts['history']]) }}</span>
                                @endif
                            </div>
                        </div>
                        @if($canRenew)
                            <x-ui.button variant="primary" wire:click="startUpload" icon="plus">
                                {{ __('Upload Document') }}
                            </x-ui.button>
                        @endif
                    </div>

                    <div class="p-6">
                        @if($documentGroups->isEmpty())
                            <!-- Empty State -->
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No documents yet') }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Upload documents like W9, insurance certificates, and licenses.') }}
                                </p>
                                @if($canRenew)
                                    <div class="mt-6">
                                        <x-ui.button variant="primary" wire:click="startUpload" icon="plus">
                                            {{ __('Upload Document') }}
                                        </x-ui.button>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="space-y-6">
                                @foreach($documentGroups as $group)
                                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                        <!-- Type heading -->
                                        <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50 flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="inline-block w-2 h-2 rounded-full shrink-0 {{ $group['active']->isEmpty() ? $expiryDot['missing'] : $expiryDot[$group['expiry']] }}"></span>
                                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ __($group['type']->name) }}</h4>
                                                @if(! $group['type']->is_active)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300" title="{{ __('This type was retired in System Settings: its documents are kept but no longer watched.') }}">{{ __('Retired type') }}</span>
                                                @elseif($group['type']->requires_expiration && $group['active']->isNotEmpty())
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$group['expiry']] }}">{{ $expiryText($group['expiry']) }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if($group['active']->isEmpty())
                                            <div class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-2">
                                                <span>{{ __('Nothing active for this type — everything filed under it was archived.') }}</span>
                                                @if($canRenew)
                                                    <button type="button" wire:click="startUpload({{ $group['type']->id }})" class="text-xs font-medium text-[#3F5189] hover:underline dark:text-[#8b9ad1]">{{ __('Upload') }}</button>
                                                @endif
                                            </div>
                                        @else
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                                    <thead>
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Document') }}</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Expiration') }}</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Uploaded') }}</th>
                                                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                                        @foreach($group['active'] as $document)
                                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50" wire:key="doc-{{ $document->id }}">
                                                                <td class="px-4 py-3">
                                                                    <div class="flex items-start">
                                                                        <svg class="w-8 h-8 text-slate-400 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                        </svg>
                                                                        <div class="min-w-0">
                                                                            <p class="text-sm font-medium text-slate-900 dark:text-white break-all">{{ $document->file_name }}</p>
                                                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $document->formatted_file_size }}</p>
                                                                            @if($document->notes)
                                                                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 whitespace-pre-line">{{ $document->notes }}</p>
                                                                            @endif
                                                                            @if($document->supersedes)
                                                                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('Replaced :file', ['file' => $document->supersedes->file_name]) }}</p>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap">
                                                                    @if($document->expiration_date)
                                                                        <div class="text-sm text-slate-900 dark:text-white">{{ $document->expiration_date->appDate() }}</div>
                                                                        @php $days = $document->days_until_expiry; @endphp
                                                                        <div class="text-xs {{ $days < 0 ? 'text-red-600 dark:text-red-400' : ($days <= $document->expiry_warning_days ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400') }}">
                                                                            @if($days === 0)
                                                                                {{ __('Expires today') }}
                                                                            @elseif($days > 0)
                                                                                {{ trans_choice('Expires in :count day|Expires in :count days', $days, ['count' => $days]) }}
                                                                            @else
                                                                                {{ trans_choice('Expired :count day ago|Expired :count days ago', abs($days), ['count' => abs($days)]) }}
                                                                            @endif
                                                                        </div>
                                                                    @else
                                                                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('No expiration') }}</span>
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap">
                                                                    @if($document->documentType->requires_expiration && $document->documentType->is_active)
                                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$document->expiry_status] }}">
                                                                            {{ $document->expiry_status_label }}
                                                                        </span>
                                                                    @else
                                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $expiryChip['valid'] }}">{{ __('Document active') }}</span>
                                                                    @endif
                                                                    @if($document->reminded_stages !== [])
                                                                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('Reminders sent: :list', ['list' => collect($document->reminded_stages)->map($stageText)->join(', ')]) }}</div>
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap">
                                                                    <div class="text-sm text-slate-900 dark:text-white">{{ $document->created_at->appDate() }}</div>
                                                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('by :name', ['name' => $document->uploadedBy->name ?? __('Unknown')]) }}</div>
                                                                </td>
                                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                                    <div class="flex items-center justify-end gap-1">
                                                                        <x-ui.icon-button variant="ghost" size="sm" icon="download" href="{{ $document->downloadUrl() }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}" />
                                                                        @php $versions = $chainLength($document); @endphp
                                                                        <x-ui.button variant="ghost" size="sm" icon="clock" wire:click="showHistory({{ $document->id }})" title="{{ trans_choice('History: this document and :count earlier version|History: this document and :count earlier versions', $versions - 1, ['count' => $versions - 1]) }}" aria-label="{{ __('History') }}">
                                                                            ({{ $versions }})
                                                                        </x-ui.button>
                                                                        @if($canRenew)
                                                                            <x-ui.button variant="outline" size="sm" icon="undo" wire:click="startRenewal({{ $document->id }})">{{ __('Renew') }}</x-ui.button>
                                                                        @endif
                                                                        @if($canArchive)
                                                                            <x-ui.button variant="ghost" size="sm" wire:click="startArchive({{ $document->id }})">{{ __('Archive') }}</x-ui.button>
                                                                        @endif
                                                                        @if($canDelete)
                                                                            <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="deleteDocument({{ $document->id }})" wire:confirm="{{ __('Are you sure you want to delete this document?') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" class="hover:text-red-600 dark:hover:text-red-400" />
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif

                                        <!-- Archived documents of this type: out of the watch, kept, reactivatable -->
                                        @if($group['archived']->isNotEmpty())
                                            <div class="border-t border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/30">
                                                <p class="px-4 pt-3 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Archived') }}</p>
                                                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                                                    @foreach($group['archived'] as $old)
                                                        <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3" wire:key="arch-{{ $old->id }}">
                                                            <div class="min-w-0 flex-1">
                                                                <p class="text-sm text-slate-700 dark:text-slate-300 break-all">{{ $old->file_name }} <span class="text-xs text-slate-400 dark:text-slate-500">{{ $old->formatted_file_size }}</span></p>
                                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                                    {{ __('Archived') }} {{ __('by :name', ['name' => $old->archivedBy->name ?? __('Unknown')]) }} &middot; {{ $old->archived_at?->appDateTime() ?? '—' }}
                                                                    @if($old->archive_reason)
                                                                        &middot; <span class="italic">{{ $old->archive_reason }}</span>
                                                                    @endif
                                                                </p>
                                                            </div>
                                                            <div class="flex items-center gap-1 sm:justify-end">
                                                                <x-ui.icon-button variant="ghost" size="sm" icon="download" href="{{ $old->downloadUrl() }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}" />
                                                                @php $versions = $chainLength($old); @endphp
                                                                <x-ui.button variant="ghost" size="sm" icon="clock" wire:click="showHistory({{ $old->id }})" title="{{ trans_choice('History: this document and :count earlier version|History: this document and :count earlier versions', $versions - 1, ['count' => $versions - 1]) }}" aria-label="{{ __('History') }}">
                                                                    ({{ $versions }})
                                                                </x-ui.button>
                                                                @if($canArchive)
                                                                    <x-ui.button variant="ghost" size="sm" wire:click="reactivateDocument({{ $old->id }})">{{ __('Reactivate') }}</x-ui.button>
                                                                @endif
                                                                @if($canDelete)
                                                                    <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="deleteDocument({{ $old->id }})" wire:confirm="{{ __('Are you sure you want to delete this document?') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" class="hover:text-red-600 dark:hover:text-red-400" />
                                                                @endif
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
