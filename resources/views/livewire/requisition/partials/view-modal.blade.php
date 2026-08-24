{{--
    Requisition detail — every field the record holds, on a full page.
    Expects: $viewingRequisition, $canReview, $selfApproval
--}}
<x-ui.modal name="requisition-view-modal" maxWidth="full">
    @if($viewingRequisition)
        @php
            $badge = [
                'gray' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300',
                'green' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300',
                'red' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300',
                'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300',
            ];
            $factLabel = 'text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400';
            $factValue = 'mt-1 text-sm text-slate-900 dark:text-white';
            $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
        @endphp

        <div class="flex min-h-screen flex-col">
            <!-- Header -->
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-semibold text-slate-900 dark:text-white truncate">{{ $viewingRequisition->title }}</h2>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[$viewingRequisition->getStatusColor()] }}">
                                {{ $viewingRequisition->getStatusLabel() }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[$viewingRequisition->getPriorityColor()] }}">
                                {{ $viewingRequisition->getPriorityLabel() }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ $viewingRequisition->requisition_number ?? '#'.$viewingRequisition->id }}
                            &middot; {{ $viewingRequisition->getTypeLabel() }}
                            &middot; {{ $viewingRequisition->getLocationDisplay() }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeViewModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        title="{{ __('Close') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
                <!-- Where it stands in the chain -->
                <div class="{{ $card }} mb-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">{{ __('Progress') }}</h3>
                    @php
                        $chain = ['draft' => __('Draft'), 'pending' => __('Pending Approval'), 'approved' => __('Approved'), 'quoted' => __('Quoted'), 'fulfilled' => __('Fulfilled')];
                        $order = array_keys($chain);
                        $currentIndex = array_search($viewingRequisition->status, $order, true);
                        $stopped = in_array($viewingRequisition->status, ['rejected', 'cancelled'], true);
                    @endphp
                    @if($stopped)
                        <p class="text-sm text-slate-900 dark:text-white">
                            {{ $viewingRequisition->status === 'rejected'
                                ? __('This requisition was rejected and goes no further.')
                                : __('This requisition was cancelled and goes no further.') }}
                        </p>
                    @else
                        <ol class="flex flex-wrap items-center gap-x-2 gap-y-2">
                            @foreach($chain as $key => $stepLabel)
                                @php $reached = $currentIndex !== false && $loop->index <= $currentIndex; @endphp
                                <li class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $reached ? 'bg-[#3F5189] text-white' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }}">
                                        {{ $stepLabel }}
                                    </span>
                                    @unless($loop->last)
                                        <span class="text-slate-300 dark:text-slate-600">&rarr;</span>
                                    @endunless
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: the facts -->
                    <div class="space-y-6">
                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">{{ __('Details') }}</h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Number') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->requisition_number ?? '#'.$viewingRequisition->id }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Type') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->getTypeLabel() }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Location') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->getLocationDisplay() }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Priority') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->getPriorityLabel() }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Needed By') }}</dt>
                                    <dd class="{{ $factValue }} {{ $viewingRequisition->isOverdue() ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                        {{ $viewingRequisition->needed_by?->format('M d, Y') ?? '—' }}
                                        @if($viewingRequisition->isOverdue())
                                            <span class="block text-xs">{{ __('Overdue') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Requested By') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->getRequesterName() }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="{{ $factLabel }}">{{ __('Budget Item') }}</dt>
                                    <dd class="{{ $factValue }}">
                                        @if($viewingRequisition->budgetItem)
                                            {{ $viewingRequisition->budgetItem->code }} — {{ $viewingRequisition->budgetItem->name }}
                                        @else
                                            <span class="text-slate-500 dark:text-slate-400">{{ __('Not linked to the budget') }}</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">{{ __('Record') }}</h3>
                            <dl class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Created By') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->createdBy?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Created') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->created_at?->format('M d, Y H:i') ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Reviewed By') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->reviewedBy?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $factLabel }}">{{ __('Reviewed') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->reviewed_at?->format('M d, Y H:i') ?? '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="{{ $factLabel }}">{{ __('Review Notes') }}</dt>
                                    <dd class="{{ $factValue }} whitespace-pre-line">{{ $viewingRequisition->review_notes ?: '—' }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="{{ $factLabel }}">{{ __('Last Updated') }}</dt>
                                    <dd class="{{ $factValue }}">{{ $viewingRequisition->updated_at?->format('M d, Y H:i') ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Right: justification, items, attachments, history -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Justification') }}</h3>
                            @if($viewingRequisition->justification)
                                <p class="text-sm text-slate-900 dark:text-white whitespace-pre-line">{{ $viewingRequisition->justification }}</p>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('No justification was written for this requisition.') }}</p>
                            @endif
                        </div>

                        <div class="{{ $card }}">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Items') }}</h3>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $viewingRequisition->items->count() }} {{ trans_choice('line|lines', $viewingRequisition->items->count()) }}
                                </span>
                            </div>
                            @if($viewingRequisition->items->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                        <thead>
                                            <tr>
                                                <th class="py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Item') }}</th>
                                                <th class="py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Qty') }}</th>
                                                <th class="py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider pl-4">{{ __('Unit') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                            @foreach($viewingRequisition->items as $item)
                                                <tr>
                                                    <td class="py-3 pr-4">
                                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->item_name }}</div>
                                                        @if($item->description)
                                                            <div class="text-xs text-slate-500 dark:text-slate-400 whitespace-pre-line">{{ $item->description }}</div>
                                                        @endif
                                                        @if($item->catalogItem)
                                                            <div class="text-xs text-slate-400 dark:text-slate-500">{{ __('From the catalog') }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="py-3 text-right text-sm text-slate-900 dark:text-white whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                                                    <td class="py-3 pl-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $item->unit ?? '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('This requisition has no items yet.') }}</p>
                            @endif
                        </div>

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Quotation Rounds') }}</h3>
                            @if($viewingRequisition->quotations->count() > 0)
                                @if($viewingRequisition->isAlreadyQuoted())
                                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                                        {{ trans_choice(
                                            'One round already covers this requisition. Raise another only to split the scope between different vendors — say, the steel and the concrete asked separately.|:count rounds already cover this requisition. Raise another only to split the scope between different vendors.',
                                            $viewingRequisition->liveQuotations()->count(),
                                            ['count' => $viewingRequisition->liveQuotations()->count()]
                                        ) }}
                                    </p>
                                @else
                                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Every round raised from this requisition was cancelled. Raising a new one starts again from these items.') }}
                                    </p>
                                @endif
                                <ul class="divide-y divide-slate-100 dark:divide-slate-700">
                                    @foreach($viewingRequisition->quotations as $round)
                                        <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-sm text-slate-900 dark:text-white">
                                                <span class="font-medium">{{ $round->quotation_number ?? '#'.$round->id }}</span>
                                                — {{ $round->title }}
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge[$round->getStatusColor()] }}">
                                                {{ $round->getStatusLabel() }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">
                                    {{ $viewingRequisition->canBeQuoted()
                                        ? __('Not quoted yet. Use Quote it to start a round from these items.')
                                        : __('This requisition has not been quoted.') }}
                                </p>
                            @endif
                        </div>

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Attachments') }}</h3>
                            <livewire:shared.attachments
                                modelType="requisition"
                                :modelId="$viewingRequisition->id"
                                :key="'requisition-attachments-'.$viewingRequisition->id" />
                        </div>

                        <div class="{{ $card }}">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('History') }}</h3>
                            @if($viewingRequisition->statusHistories->count() > 0)
                                <ul class="space-y-3">
                                    @foreach($viewingRequisition->statusHistories as $history)
                                        <li class="flex items-start gap-3">
                                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-[#3F5189] dark:bg-[#4A5A96]"></span>
                                            <div>
                                                <p class="text-sm text-slate-900 dark:text-white">
                                                    {{ $history->old_status
                                                        ? __(':old to :new', ['old' => \App\Models\PurchaseRequisition::statusLabel($history->old_status), 'new' => \App\Models\PurchaseRequisition::statusLabel($history->new_status)])
                                                        : __('Created as :status', ['status' => \App\Models\PurchaseRequisition::statusLabel($history->new_status)]) }}
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $history->changedBy?->name ?? __('Unknown') }} &middot; {{ $history->created_at?->format('M d, Y H:i') }}
                                                </p>
                                                @if($history->reason)
                                                    <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $history->reason }}</p>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('Nothing has happened to this requisition yet.') }}</p>
                            @endif
                        </div>

                        <!-- Review -->
                        @if($viewingRequisition->canBeReviewed())
                            <div class="{{ $card }}">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Approval') }}</h3>
                                @if($canReview && ! $selfApproval)
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Review Notes') }}</label>
                                    <textarea
                                        wire:model="reviewNotes"
                                        rows="3"
                                        placeholder="{{ __('Optional when approving, required when rejecting.') }}"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500"></textarea>
                                    @error('reviewNotes') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        <x-ui.button variant="success" icon="check" wire:click="approveRequisition({{ $viewingRequisition->id }})">
                                            {{ __('Approve') }}
                                        </x-ui.button>
                                        <x-ui.button variant="danger" icon="x" wire:click="rejectRequisition({{ $viewingRequisition->id }})">
                                            {{ __('Reject') }}
                                        </x-ui.button>
                                    </div>
                                @elseif($canReview && $selfApproval)
                                    {{-- N2: the reviewer must not be the requester. Say which
                                         of the two roles they are in, and that it is a grant. --}}
                                    <p class="text-sm text-amber-700 dark:text-amber-400">
                                        {{ __('You raised this requisition, so somebody else has to approve it.') }}
                                    </p>
                                    <div class="mt-3">
                                        <x-ui.button variant="danger" icon="x" wire:click="rejectRequisition({{ $viewingRequisition->id }})">
                                            {{ __('Reject') }}
                                        </x-ui.button>
                                    </div>
                                @else
                                    <p class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ __('This requisition is waiting for somebody who can approve it.') }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3">
                        @if($viewingRequisition->status === 'draft')
                            @can('requisitions.submit', $viewingRequisition)
                                <x-ui.button variant="primary" icon="save" wire:click="submitForApproval({{ $viewingRequisition->id }})">
                                    {{ __('Submit for Approval') }}
                                </x-ui.button>
                            @endcan
                        @endif
                        {{-- N1: a submitted requisition is locked. This is the way back. --}}
                        @if($viewingRequisition->canReturnToDraft()
                            && ($viewingRequisition->created_by === auth()->id() || $canReview)
                            && auth()->user()->can('requisitions.edit', $viewingRequisition))
                            <x-ui.button
                                variant="warning"
                                wire:click="returnToDraft({{ $viewingRequisition->id }})"
                                wire:confirm="{{ __('Return this requisition to draft? It loses its place in the approval queue and will need approving again.') }}">
                                {{ __('Return to Draft') }}
                            </x-ui.button>
                        @endif
                        @if($viewingRequisition->canBeQuoted())
                            <x-ui.button
                                variant="{{ $viewingRequisition->isAlreadyQuoted() ? 'secondary' : 'primary' }}"
                                href="{{ $quotationsRoute }}"
                                icon="arrow-right">
                                {{ $viewingRequisition->isAlreadyQuoted() ? __('Raise Another Round') : __('Quote it') }}
                            </x-ui.button>
                        @endif
                        @if($viewingRequisition->canBeEdited())
                            @can('requisitions.edit', $viewingRequisition)
                                <x-ui.button variant="secondary" icon="edit" wire:click="openEditModal({{ $viewingRequisition->id }})">
                                    {{ __('Edit') }}
                                </x-ui.button>
                            @endcan
                        @endif
                        {{-- N1: raise a near-identical ask without touching a signed document.
                             Offered from any status, approved and rejected included. --}}
                        @can('requisitions.duplicate', $viewingRequisition)
                            <x-ui.button
                                variant="secondary"
                                icon="copy"
                                wire:click="duplicateRequisition({{ $viewingRequisition->id }})">
                                {{ __('Duplicate') }}
                            </x-ui.button>
                        @endcan
                        @if($viewingRequisition->canBeCancelled())
                            @can('requisitions.edit', $viewingRequisition)
                                @if($viewingRequisition->status !== 'approved' || $canReview)
                                    <x-ui.button
                                        variant="warning"
                                        wire:click="cancelRequisition({{ $viewingRequisition->id }})"
                                        wire:confirm="{{ __('Cancel this requisition?') }}">
                                        {{ __('Cancel Requisition') }}
                                    </x-ui.button>
                                @endif
                            @endcan
                        @endif
                        @if($viewingRequisition->canBeDeleted())
                            @can('requisitions.delete', $viewingRequisition)
                                <x-ui.button
                                    variant="danger"
                                    icon="trash"
                                    wire:click="deleteRequisition({{ $viewingRequisition->id }})"
                                    wire:confirm="{{ __('Delete this requisition permanently?') }}">
                                    {{ __('Delete') }}
                                </x-ui.button>
                            @endcan
                        @endif
                    </div>
                    <x-ui.button variant="secondary" wire:click="closeViewModal">{{ __('Close') }}</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</x-ui.modal>
