@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
    $dateTimeFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A';
    $isBR = config('app.country') === 'BR';
    $input = 'mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';

    $statusTone = match ($approval->status) {
        \App\Models\Approval::DRAFT => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        \App\Models\Approval::IN_REVIEW => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        \App\Models\Approval::APPROVED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        \App\Models\Approval::REJECTED => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
@endphp

<div>
    <x-ui.breadcrumb :items="[
        ['label' => __('Projects'), 'url' => route('projects.index')],
        ['label' => $approval->project->project_name, 'url' => route('projects.overview', $approval->project)],
        ['label' => __('Approvals'), 'url' => $approval->job_site_id
            ? route('jobsites.approvals', $approval->job_site_id)
            : route('projects.approvals', $approval->project)],
        ['label' => $approval->number],
    ]" />

    @if(session('approval_upload_refused'))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            {{ session('approval_upload_refused') }}
        </div>
    @endif

    @if(session('approval_message'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">
            {{ session('approval_message') }}
        </div>
    @endif

    @error('submit') <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-200">{{ $message }}</div> @enderror
    @error('response') <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-200">{{ $message }}</div> @enderror
    @error('reviewers') <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-200">{{ $message }}</div> @enderror

    {{-- Header --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $approval->number }}</span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusTone }}">{{ $approval->getStatusLabel() }}</span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $approval->getTypeLabel() }}</span>
                    <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ __('collaboration.message.rev') }} {{ $approval->current_revision }}</span>
                    @if($approval->isOverdue())
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200">
                            {{ trans_choice(':count day late|:count days late', $approval->daysOverdue(), ['count' => $approval->daysOverdue()]) }}
                        </span>
                    @endif
                </div>

                <h1 class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">{{ $approval->title }}</h1>

                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $approval->project->project_name }}
                    · {{ $approval->jobSite?->job_site_name ?? __('Project (General)') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2 shrink-0">
                @if($this->canRespond)
                    <x-ui.button variant="primary" size="sm" x-on:click="$dispatch('open-modal', 'approval-respond')">
                        {{ __('Record a response') }}
                    </x-ui.button>
                @endif

                @if($this->canSubmit)
                    <x-ui.button variant="success" size="sm" x-on:click="$dispatch('open-modal', 'approval-submit')">
                        {{ $approval->revisions->isEmpty() ? __('collaboration.label.submit_review') : __('collaboration.label.submit_new_revision') }}
                    </x-ui.button>
                @endif

                {{-- Offered only where there is nothing outside to preserve: a
                     draft nobody submitted, or one already void. --}}
                @if($this->canDelete)
                    <x-ui.button variant="danger" size="sm" icon="trash" wire:click="deleteApproval"
                        wire:confirm="{{ __('collaboration.help.delete_approval_permanently') }}">
                        {{ __('Delete') }}
                    </x-ui.button>
                @endif

                @if($this->canExport)
                    <x-ui.button variant="secondary" size="sm" :href="route('approvals.pdf.download', $approval)">
                        {{ __('PDF') }}
                    </x-ui.button>
                @endif

                @if($this->canSign)
                    <x-ui.button variant="secondary" size="sm" x-on:click="$dispatch('open-modal', 'document-sign')">
                        {{ __('collaboration.label.sign') }}
                    </x-ui.button>
                @endif

                @if($this->canDistribute)
                    <x-ui.button variant="secondary" size="sm" x-on:click="$dispatch('open-modal', 'document-distribute')">
                        {{ __('collaboration.label.send') }}
                    </x-ui.button>
                @endif

                @if($this->canEdit)
                    <x-ui.button variant="secondary" size="sm" icon="edit" :href="route('approvals.edit', $approval)">
                        {{ __('Edit') }}
                    </x-ui.button>
                @endif
            </div>
        </div>

        {{-- A certificate about to lapse is the most useful thing this page
             can say, so it says it at the top rather than in a side panel. --}}
        @if($approval->certificateNeedsAttention())
            <div class="mt-4 rounded-lg px-4 py-3 text-sm
                {{ $approval->certificate->hasExpired()
                    ? 'bg-rose-50 text-rose-900 dark:bg-rose-900/20 dark:text-rose-200'
                    : 'bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-200' }}">
                {{ $approval->certificate->hasExpired()
                    ? __('collaboration.message.certificate_expired', ['date' => $approval->certificate->valid_until->format($dateFormat)])
                    : __('collaboration.message.certificate_expires', ['date' => $approval->certificate->valid_until->format($dateFormat)]) }}
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: the rounds --}}
        <div class="lg:col-span-2 space-y-6">
            @if($approval->description)
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.what_being_submitted') }}</h2>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line">{{ $approval->description }}</p>
                    </div>
                </div>
            @endif

            <div>
                <h2 class="mb-3 font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.revisions') }}</h2>

                @if($revisions->isEmpty())
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-8 text-center">
                        <p class="font-medium text-slate-900 dark:text-white">{{ __('collaboration.message.submitted') }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('collaboration.help.name_who_should_review_submit') }}
                        </p>
                        @if($this->canSubmit)
                            <div class="mt-4">
                                <x-ui.button variant="primary" size="sm" x-on:click="$dispatch('open-modal', 'approval-submit')">
                                    {{ __('collaboration.label.submit_review') }}
                                </x-ui.button>
                            </div>
                        @endif
                    </div>
                @else
                    @include('livewire.approval.partials.revisions', ['revisions' => $revisions])
                @endif
            </div>

            @include('livewire.rfi.partials.activity', ['activity' => $activity])
        </div>

        {{-- Right: everything else the record knows --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Details') }}</h2>
                </div>

                <dl class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                    @php
                        $rows = [
                            ['label' => __('Type'), 'value' => $approval->getTypeLabel()],
                            ['label' => __('collaboration.label.due'), 'value' => $approval->due_date?->format($dateFormat)],
                            ['label' => __('collaboration.label.budget_line'), 'value' => $approval->budgetItem
                                ? trim($approval->budgetItem->code.' '.$approval->budgetItem->name) : null],
                            ['label' => __('collaboration.label.catalog_item'), 'value' => $approval->catalogItem?->name],
                            ['label' => __('Supplier'), 'value' => $approval->supplier?->name],
                            ['label' => __('collaboration.label.package'), 'value' => $approval->package?->number],
                        ];

                        if (! $isBR) {
                            $rows[] = ['label' => __('collaboration.label.spec_section'), 'value' => $approval->spec_section];
                        }
                    @endphp

                    @foreach($rows as $row)
                        <div class="px-5 py-3 flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $row['label'] }}</dt>
                            <dd class="text-right text-slate-900 dark:text-white">{{ $row['value'] ?: __('Not set') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- The laudo's own facts. Only for the type that has them. --}}
            @if($approval->type === \App\Models\Approval::TYPE_CERTIFICATE)
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.certificate') }}</h2>
                    </div>

                    @if($approval->certificate)
                        <dl class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                            <div class="px-5 py-3 flex items-start justify-between gap-4">
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.issued') }}</dt>
                                <dd class="text-right text-slate-900 dark:text-white">{{ $approval->certificate->issuing_body }}</dd>
                            </div>
                            <div class="px-5 py-3 flex items-start justify-between gap-4">
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.certificate_number') }}</dt>
                                <dd class="text-right text-slate-900 dark:text-white">{{ $approval->certificate->certificate_number ?: __('Not set') }}</dd>
                            </div>
                            <div class="px-5 py-3 flex items-start justify-between gap-4">
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.issued_2') }}</dt>
                                <dd class="text-right text-slate-900 dark:text-white">{{ $approval->certificate->issued_at?->format($dateFormat) ?: __('Not set') }}</dd>
                            </div>
                            <div class="px-5 py-3 flex items-start justify-between gap-4">
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.valid_until') }}</dt>
                                <dd class="text-right {{ $approval->certificate->hasExpired() ? 'text-rose-600 dark:text-rose-400 font-medium' : 'text-slate-900 dark:text-white' }}">
                                    {{ $approval->certificate->valid_until?->format($dateFormat) ?: __('Not set') }}
                                </dd>
                            </div>
                        </dl>
                    @else
                        <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('collaboration.help.certificate_details_recorded_add_issuing') }}
                        </p>
                    @endif
                </div>
            @endif

            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Attachments') }}</h2>
                </div>
                @if($files->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.message.files_attached') }}</p>
                @else
                    <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        @foreach($files as $file)
                            <li class="px-5 py-3">
                                <button type="button" wire:click="downloadFile({{ $file->id }})"
                                    class="text-left text-[#3F5189] dark:text-indigo-400 hover:underline truncate w-full">
                                    {{ $file->original_name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Distribution') }}</h2>
                </div>
                @php
                    $lastSend = $approval->lastDistribution();
                    $awaiting = $approval->recipientsAwaitingFirstSend();
                @endphp

                {{-- Say whether anything was actually sent. The list on its own
                     is intent; nothing leaves until somebody presses Send. --}}
                <div class="px-5 py-3 border-b border-slate-200 dark:border-slate-700 text-xs">
                    @if($lastSend)
                        <p class="text-slate-600 dark:text-slate-300">
                            {{ __('collaboration.label.last_sent_on', [
                                'when' => $lastSend->created_at?->format($dateTimeFormat),
                                'count' => $lastSend->context['sent'] ?? 0,
                            ]) }}
                        </p>
                    @else
                        <p class="text-amber-700 dark:text-amber-300 font-medium">
                            {{ __('collaboration.label.never_sent') }}
                        </p>
                    @endif

                    @if($awaiting->isNotEmpty() && $lastSend)
                        <p class="mt-1 text-amber-700 dark:text-amber-300">
                            {{ trans_choice('collaboration.count.added_since_last_send', $awaiting->count(), ['count' => $awaiting->count()]) }}
                            — {{ $awaiting->values()->join(', ') }}
                        </p>
                    @endif
                </div>

                @if($distribution->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.message.nobody_copied_approval') }}</p>
                @else
                    <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        @foreach($distribution as $entry)
                            <li class="px-5 py-3">
                                <p class="text-slate-900 dark:text-white">{{ $entry->getName() }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $entry->getEmail() }}@if($entry->role) · {{ $entry->getRoleLabel() }}@endif
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @include('livewire.collaboration.partials.sign-distribute', [
                'document' => $approval,
                'signatures' => $signatures,
            ])

            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Record') }}</h2>
                </div>
                <dl class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                    <div class="px-5 py-3 flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.raised') }}</dt>
                        <dd class="text-right text-slate-900 dark:text-white">{{ $approval->createdBy?->name ?? __('collaboration.label.removed_user') }}</dd>
                    </div>
                    <div class="px-5 py-3 flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.raised_3') }}</dt>
                        <dd class="text-right text-slate-900 dark:text-white">{{ $approval->created_at?->format($dateTimeFormat) }}</dd>
                    </div>
                    <div class="px-5 py-3 flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Last updated') }}</dt>
                        <dd class="text-right text-slate-900 dark:text-white">{{ $approval->updated_at?->format($dateTimeFormat) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- Submit a revision --}}
    @if($this->canSubmit)
        <x-ui.modal name="approval-submit" maxWidth="2xl">
            <form wire:submit="submitRevision" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $approval->revisions->isEmpty() ? __('collaboration.label.submit_review') : __('collaboration.label.submit_new_revision') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.reviewers_same_step_number_look') }}
                </p>

                <div class="mt-4 space-y-3">
                    @foreach($reviewerRows as $index => $row)
                        <div wire:key="rev-{{ $index }}" class="grid grid-cols-12 gap-2 items-start">
                            <div class="col-span-6">
                                <select wire:model="reviewerRows.{{ $index }}.user_id" class="{{ $input }} mt-0">
                                    <option value="">{{ __('collaboration.label.choose_reviewer') }}</option>
                                    @foreach($assignableUsers as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-span-2">
                                <input type="number" min="1" wire:model="reviewerRows.{{ $index }}.sequence"
                                    class="{{ $input }} mt-0" title="{{ __('collaboration.label.step') }}">
                            </div>

                            <div class="col-span-3">
                                <select wire:model="reviewerRows.{{ $index }}.role" class="{{ $input }} mt-0">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Collaboration\DistributionEntry::roleOptions() as $value => $text)
                                        <option value="{{ $value }}">{{ $text }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-span-1 flex justify-end">
                                <x-ui.button variant="ghost" size="sm" type="button" icon="trash"
                                    wire:click="removeReviewerRow({{ $index }})">
                                    <span class="sr-only">{{ __('Remove') }}</span>
                                </x-ui.button>
                            </div>
                        </div>
                    @endforeach

                    @error('reviewerRows.*.sequence') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                    @if(empty($assignableUsers))
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('collaboration.help.nobody_been_added_project_there_2') }}
                        </p>
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.button variant="secondary" size="sm" type="button" icon="plus" wire:click="addReviewerRow">
                        {{ __('collaboration.label.add_reviewer') }}
                    </x-ui.button>

                    @if($approval->revisions->isNotEmpty())
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="reuseLastReviewers">
                            {{ __('collaboration.label.same_reviewers_last_time') }}
                        </x-ui.button>
                    @endif
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'approval-submit')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit">{{ __('collaboration.label.submit') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    {{-- Record a response --}}

    {{-- Submittal package: several approvals sent to the architect as one
         bundle. The table and the column shipped with the module; this is what
         finally uses them. --}}
    @if($this->canManagePackages)
        <div class="mt-6 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
                {{ __('collaboration.label.submittal_package') }}
            </h3>

            @if($approval->package)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 px-4 py-3 mb-4">
                    <div>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                            {{ $approval->package->number }} — {{ $approval->package->title }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $approval->package->getStatusLabel() }}
                            &middot; {{ trans_choice(':count approval|:count approvals', $approval->package->approvals()->count(), ['count' => $approval->package->approvals()->count()]) }}
                        </p>
                    </div>
                    <x-ui.button variant="secondary" size="sm" wire:click="togglePackageStatus">
                        {{ $approval->package->isOpen()
                            ? __('collaboration.label.close_package')
                            : __('collaboration.label.reopen_package') }}
                    </x-ui.button>
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    {{ __('collaboration.help.approval_not_in_a_package') }}
                </p>
            @endif

            <label for="approval-package" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                {{ __('collaboration.field.package') }}
            </label>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <select id="approval-package" wire:model="packageId"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('collaboration.label.no_package') }}</option>
                    @foreach($this->availablePackages() as $package)
                        <option value="{{ $package->id }}">
                            {{ $package->number }} — {{ $package->title }} ({{ $package->approvals_count }})
                        </option>
                    @endforeach
                </select>
                <x-ui.button variant="primary" size="sm" icon="save" wire:click="setPackage">{{ __('Save') }}</x-ui.button>
            </div>
            @error('packageId') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

            <div class="mt-5 pt-4 border-t border-slate-200 dark:border-slate-700">
                <label for="new-package" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('collaboration.field.package_title') }}
                </label>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <input id="new-package" type="text" wire:model="newPackageTitle"
                        placeholder="{{ __('collaboration.prompt.package_title_placeholder') }}"
                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                    <x-ui.button variant="secondary" size="sm" icon="plus" wire:click="createPackage">
                        {{ __('collaboration.label.start_package') }}
                    </x-ui.button>
                </div>
                @error('newPackageTitle') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.package_numbered_per_project') }}
                </p>
            </div>
        </div>
    @endif

    @if($this->canRespond)
        <x-ui.modal name="approval-respond" maxWidth="2xl">
            <form wire:submit="recordResponse" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Record a response') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.label.revision_2', ['revision' => $approval->current_revision, 'number' => $approval->number]) }}
                </p>

                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.response') }}</label>
                <select wire:model="responseCodeId" class="{{ $input }}">
                    <option value="">{{ __('collaboration.label.choose_response') }}</option>
                    @foreach($responseCodes as $code)
                        <option value="{{ $code->id }}">{{ $code->getLabel() }}</option>
                    @endforeach
                </select>
                @error('responseCodeId') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Comments') }}</label>
                <textarea wire:model="responseComments" rows="5" class="{{ $input }}"
                    placeholder="{{ __('collaboration.help.what_change_what_acceptance_depends') }}"></textarea>
                @error('responseComments') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'approval-respond')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit">{{ __('collaboration.label.record_response_2') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
