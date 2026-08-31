@php
    $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
    $dateTimeFormat = config('app.country') === 'BR' ? 'd/m/Y H:i' : 'm/d/Y g:i A';
    $isBR = config('app.country') === 'BR';

    $statusTone = match ($rfi->status) {
        \App\Models\Rfi::DRAFT => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        \App\Models\Rfi::OPEN => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        \App\Models\Rfi::ANSWERED => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        \App\Models\Rfi::CLOSED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
@endphp

<div>
    <x-ui.breadcrumb :items="[
        ['label' => __('Projects'), 'url' => route('projects.index')],
        ['label' => $rfi->project->project_name, 'url' => route('projects.overview', $rfi->project)],
        ['label' => __('RFIs'), 'url' => $rfi->job_site_id
            ? route('jobsites.rfis', $rfi->job_site_id)
            : route('projects.rfis', $rfi->project)],
        ['label' => $rfi->number],
    ]" />

    @if(session('rfi_upload_refused'))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            {{ session('rfi_upload_refused') }}
        </div>
    @endif

    @if(session('rfi_message'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200">
            {{ session('rfi_message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $rfi->number }}</span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusTone }}">{{ $rfi->getStatusLabel() }}</span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $rfi->getPriorityLabel() }}</span>
                    @if($rfi->isOverdue())
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200">
                            {{ trans_choice(':count day late|:count days late', $rfi->daysOverdue(), ['count' => $rfi->daysOverdue()]) }}
                        </span>
                    @endif
                </div>

                <h1 class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">{{ $rfi->subject }}</h1>

                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $rfi->project->project_name }}
                    · {{ $rfi->jobSite?->job_site_name ?? __('Project (General)') }}
                    @if($rfi->discipline) · {{ $rfi->getDisciplineLabel() }}@endif
                </p>
            </div>

            <div class="flex flex-wrap gap-2 shrink-0">
                @if($this->canAnswer)
                    <x-ui.button variant="primary" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'rfi-answer')">
                        {{ $rfi->isAnswered() ? __('collaboration.label.new_reply') : __('collaboration.label.answer') }}
                    </x-ui.button>
                @endif

                @if($this->canClose)
                    <x-ui.button variant="success" size="sm" wire:click="close"
                        wire:confirm="{{ __('collaboration.help.close_rfi_question_answer_frozen') }}">
                        {{ __('collaboration.label.close_rfi') }}
                    </x-ui.button>
                @endif

                @if($this->canReopen)
                    <x-ui.button variant="secondary" size="sm" wire:click="reopen">{{ __('Reopen') }}</x-ui.button>
                @endif

                {{-- Withdrawing keeps the record; deleting removes it, and is
                     offered only where there is nothing outside to preserve. --}}
                @if($this->canVoid)
                    <x-ui.button variant="warning" size="sm" x-on:click="$dispatch('open-modal', 'rfi-void')">
                        {{ __('collaboration.label.void_rfi') }}
                    </x-ui.button>
                @endif

                @if($this->canDelete)
                    <x-ui.button variant="danger" size="sm" icon="trash" wire:click="deleteRfi"
                        wire:confirm="{{ __('collaboration.help.delete_rfi_permanently') }}">
                        {{ __('Delete') }}
                    </x-ui.button>
                @endif

                @if($this->canExport)
                    <x-ui.button variant="secondary" size="sm" :href="route('rfis.pdf.download', $rfi)">
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
                    <x-ui.button variant="secondary" size="sm" x-on:click="$dispatch('open-modal', 'rfi-ball')">
                        {{ __('collaboration.label.ball_court') }}
                    </x-ui.button>

                    <x-ui.button variant="secondary" size="sm" icon="edit" :href="route('rfis.edit', $rfi)">
                        {{ __('Edit') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: the conversation --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.question') }}</h2>
                </div>
                <div class="px-5 py-4">
                    <p class="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line">{{ $rfi->question }}</p>
                </div>
            </div>

            @include('livewire.rfi.partials.replies', ['replies' => $replies])

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
                            ['label' => __('collaboration.label.ball_court'), 'value' => $rfi->ballInCourt?->name],
                            ['label' => __('collaboration.label.due'), 'value' => $rfi->due_date?->format($dateFormat)],
                            ['label' => __('collaboration.label.discipline'), 'value' => $rfi->discipline ? $rfi->getDisciplineLabel() : null],
                        ];

                        // Both markets' reference field exists on every install;
                        // the screen shows the one this country uses. Presentation,
                        // never a branch in business logic.
                        $rows[] = $isBR
                            ? ['label' => __('collaboration.label.drawing'), 'value' => $rfi->drawing_ref]
                            : ['label' => __('collaboration.label.spec_section'), 'value' => $rfi->spec_section];

                        $rows[] = ['label' => __('Priority'), 'value' => $rfi->getPriorityLabel()];
                    @endphp

                    @foreach($rows as $row)
                        <div class="px-5 py-3 flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $row['label'] }}</dt>
                            <dd class="text-right text-slate-900 dark:text-white">
                                {{ $row['value'] ?: __('Not set') }}
                            </dd>
                        </div>
                    @endforeach

                    @if($this->canSeeImpact)
                        <div class="px-5 py-3 flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.cost_impact') }}</dt>
                            <dd class="text-right text-slate-900 dark:text-white">
                                @if($rfi->cost_impact)
                                    @if($rfi->cost_impact_amount !== null)
                                        {{-- A rollup: the figure is the company's,
                                             so can_see_money masks it even from
                                             somebody who may know a cost exists. --}}
                                        <x-ui.money :amount="$rfi->cost_impact_amount" :scope="$rfi->jobSite ?? $rfi->project" rollup />
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('collaboration.label.estimated') }}</span>
                                    @else
                                        {{ __('Yes') }}
                                    @endif
                                @else
                                    {{ __('No') }}
                                @endif
                            </dd>
                        </div>
                        <div class="px-5 py-3 flex items-start justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.schedule_impact') }}</dt>
                            <dd class="text-right text-slate-900 dark:text-white">
                                @if($rfi->schedule_impact)
                                    {{ $rfi->schedule_impact_days
                                        ? trans_choice(':count day|:count days', $rfi->schedule_impact_days, ['count' => $rfi->schedule_impact_days])
                                        : __('Yes') }}
                                @else
                                    {{ __('No') }}
                                @endif
                            </dd>
                        </div>

                        @if($rfi->changeOrder)
                            <div class="px-5 py-3 flex items-start justify-between gap-4">
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.change_order') }}</dt>
                                <dd class="text-right">
                                    <a href="{{ $rfi->job_site_id
                                            ? route('jobsites.change-orders', $rfi->job_site_id)
                                            : route('projects.change-orders', $rfi->project) }}"
                                        class="text-[#3F5189] dark:text-indigo-400 hover:underline">
                                        {{ $rfi->changeOrder->co_number }}
                                    </a>
                                    @if($rfi->change_order_linked_at)
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                            {{ $rfi->change_order_linked_at->format($dateFormat) }}
                                        </span>
                                    @endif
                                </dd>
                            </div>

                            {{-- The aditivo was argued from the answer as it read
                                 then. If it has been corrected since, say so
                                 rather than showing one wording and implying the
                                 other still stands. --}}
                            @if($rfi->answerChangedSinceChangeOrder())
                                <div class="px-5 py-3 bg-amber-50 dark:bg-amber-900/20">
                                    <p class="text-xs font-medium text-amber-900 dark:text-amber-200">
                                        {{ __('collaboration.help.answer_corrected_since') }}
                                    </p>
                                    <p class="mt-2 text-xs text-amber-900 dark:text-amber-200 whitespace-pre-line border-l-2 border-amber-300 dark:border-amber-700 pl-2">
                                        {{ $rfi->change_order_answer }}
                                    </p>
                                </div>
                            @endif
                        @endif
                    @endif
                </dl>

                {{-- Offered, never done automatically: every money-touching
                     artifact is confirmed by a person. --}}
                @if($this->canSeeImpact && $rfi->isClosed() && $rfi->suggestsChangeOrder())
                    <div class="px-5 py-4 border-t border-slate-200 dark:border-slate-700 bg-amber-50 dark:bg-amber-900/20">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            {{ __('collaboration.help.rfi_closed_impact_recorded_change') }}
                        </p>
                        @can('change-orders.create', $rfi->jobSite ?? $rfi->project)
                            <div class="mt-3">
                                <x-ui.button variant="warning" size="sm"
                                    :href="($rfi->job_site_id
                                        ? route('jobsites.change-orders', $rfi->job_site_id)
                                        : route('projects.change-orders', $rfi->project)).'?fromRfi='.$rfi->id">
                                    {{ __('collaboration.label.create_change_order') }}
                                </x-ui.button>
                            </div>
                        @endcan
                    </div>
                @endif
            </div>

            {{-- Distribution --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Distribution') }}</h2>
                </div>
                @php
                    $lastSend = $rfi->lastDistribution();
                    $awaiting = $rfi->recipientsAwaitingFirstSend();
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
                    <p class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.message.nobody_copied_rfi') }}</p>
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

            {{-- Attachments --}}
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

            @include('livewire.collaboration.partials.sign-distribute', [
                'document' => $rfi,
                'signatures' => $signatures,
            ])

            {{-- Audit facts. If the database knows it, the detail view shows it. --}}
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="font-semibold text-slate-900 dark:text-white">{{ __('Record') }}</h2>
                </div>
                <dl class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                    <div class="px-5 py-3 flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.raised') }}</dt>
                        <dd class="text-right text-slate-900 dark:text-white">{{ $rfi->createdBy?->name ?? __('collaboration.label.removed_user') }}</dd>
                    </div>
                    <div class="px-5 py-3 flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('collaboration.label.raised_3') }}</dt>
                        <dd class="text-right text-slate-900 dark:text-white">{{ $rfi->created_at?->format($dateTimeFormat) }}</dd>
                    </div>
                    <div class="px-5 py-3 flex items-start justify-between gap-4">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Last updated') }}</dt>
                        <dd class="text-right text-slate-900 dark:text-white">{{ $rfi->updated_at?->format($dateTimeFormat) }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    {{-- A handful of fields on one record: a dialog, not a full-page modal. --}}
    @if($this->canAnswer)
        <x-ui.modal name="rfi-answer" maxWidth="2xl">
            <form wire:submit="recordAnswer" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.answer_rfi') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $rfi->number }} — {{ $rfi->subject }}</p>

                <textarea wire:model="answerText" rows="8"
                    class="mt-4 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                    placeholder="{{ __('collaboration.placeholder.write_answer') }}"></textarea>
                @error('answerText') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                {{-- Attached to this reply, not to the SI as a whole: somebody
                     answering with a marked-up prancha has both in front of
                     them at once, and the file belongs to what was said. --}}
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.attach_to_this_reply') }}</label>
                    <x-ui.file-drop wire:model="newReplyUploads" class="mt-1 space-y-2">
                        {{-- Three keys, three different refusals: the answer's
                             own check, this box's size check, and Livewire's
                             temporary-upload rules. --}}
                        @error('replyUploads.*') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        @error('newReplyUploads') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        @error('newReplyUploads.*') <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                        @if(count($replyUploads) > 0)
                            <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                @foreach($replyUploads as $index => $upload)
                                    <li wire:key="reply-upload-{{ $index }}" class="px-3 py-2 flex items-center justify-between gap-3">
                                        <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                            {{ $upload->getClientOriginalName() }}
                                        </span>
                                        <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                            {{ \App\Services\DocumentSettings::formatBytes($upload->getSize()) }}
                                        </span>
                                        <x-ui.icon-button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            type="button"
                                            wire:click="removeReplyUpload({{ $index }})"
                                            title="{{ __('Remove :file', ['file' => $upload->getClientOriginalName()]) }}"
                                            aria-label="{{ __('Remove :file', ['file' => $upload->getClientOriginalName()]) }}"
                                            class="hover:text-red-600 dark:hover:text-red-400" />
                                    </li>
                                @endforeach
                            </ul>

                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ trans_choice(':count file goes up with this answer.|:count files go up with this answer.', count($replyUploads), ['count' => count($replyUploads)]) }}
                            </p>
                        @endif
                    </x-ui.file-drop>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'rfi-answer')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit">{{ __('collaboration.label.save_answer') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if($this->canEdit)
        <x-ui.modal name="rfi-void" maxWidth="lg">
            <form wire:submit="voidRfi" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.void_rfi') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.prompt.void_keeps_the_record') }}
                </p>

                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">
                    {{ __('collaboration.field.void_reason') }}
                </label>
                <textarea wire:model="voidReason" rows="3"
                    placeholder="{{ __('collaboration.prompt.void_reason_placeholder') }}"
                    class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500"></textarea>
                @error('voidReason') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'rfi-void')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="warning" type="submit">{{ __('collaboration.label.void_rfi') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal name="rfi-ball" maxWidth="lg">
            <form wire:submit="passBall" class="p-6">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('collaboration.label.ball_court') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('collaboration.prompt.who_rfi_waiting_when') }}</p>

                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.waiting_2') }}</label>
                <select wire:model="passToUserId"
                    class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    <option value="">{{ __('collaboration.label.nobody') }}</option>
                    @foreach($assignableUsers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('passToUserId') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                @if(empty($assignableUsers))
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.help.nobody_been_added_project_there') }}
                    </p>
                @endif

                <label class="block mt-4 text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.due') }}</label>
                <input type="date" wire:model="passToDueDate"
                    class="mt-1 w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                @error('passToDueDate') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'rfi-ball')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit">{{ __('Save') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
