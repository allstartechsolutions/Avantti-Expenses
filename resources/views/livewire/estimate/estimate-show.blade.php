<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $estimate->estimate_number }}
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $estimate->status_color }}">
                        {{ $estimate->status_label }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $estimate->client->company_name }}
                    @if($estimate->project)
                        / {{ $estimate->project->project_name }}
                    @endif
                    @if($estimate->jobSite)
                        / {{ $estimate->jobSite->job_site_name }}
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button variant="secondary" href="{{ route('estimates.index') }}" icon="arrow-left">
                    {{ __('Back to List') }}
                </x-ui.button>
                @if($estimate->canBeEdited())
                    <x-ui.button variant="secondary" href="{{ route('estimates.edit', $estimate->id) }}" icon="edit">
                        {{ __('Edit') }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Estimate Details Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Estimate Details') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Client') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->client->company_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Estimate Number') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->estimate_number }}</dd>
                        </div>
                        @if($estimate->project)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Project') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->project->project_name }}</dd>
                            </div>
                        @endif
                        @if($estimate->jobSite)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Job Site') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->jobSite->job_site_name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Estimate Date') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->estimate_date->appDate() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Terms') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->terms_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Due Date') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->due_date->appDate() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Created By') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->createdBy?->name ?? __('Unknown') }}</dd>
                        </div>
                        @if($estimate->sent_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Sent At') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->sent_at->appDateTime() }}</dd>
                            </div>
                        @endif
                        @if($estimate->accepted_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Accepted At') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->accepted_at->appDateTime() }}</dd>
                            </div>
                        @endif
                        @if($estimate->declined_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Declined At') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->declined_at->appDateTime() }}</dd>
                            </div>
                        @endif
                        @if($estimate->notes)
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Internal Notes') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Items Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Items') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Item') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Qty') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Unit Price') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Discount') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Tax') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($estimate->items as $item)
                                <tr>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->item_name }}</div>
                                        <div class="text-xs text-slate-500">
                                            {{ $item->item_type === 'catalog' ? 'From Catalog' : 'Custom' }}
                                            @if($item->unit) &middot; {{ $item->unit }} @endif
                                        </div>
                                        @if($item->description)
                                            <div class="text-xs text-slate-500 mt-1">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white text-right">{{ $item->quantity }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white text-right">${{ number_format($item->unit_price, 2) }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white text-right">
                                        @if($item->discount_amount > 0)
                                            -${{ number_format($item->discount_amount, 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white text-right">
                                        @if($item->is_taxable && $item->tax_amount > 0)
                                            ${{ number_format($item->tax_amount, 2) }}
                                            <div class="text-xs text-slate-500">{{ number_format($item->tax_rate * 100, 2) }}%</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white text-right">${{ number_format($item->total_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                    <div class="max-w-xs ml-auto space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-600 dark:text-slate-400">{{ __('Subtotal') }}</span>
                            <span class="text-slate-900 dark:text-white">${{ number_format($estimate->subtotal, 2) }}</span>
                        </div>
                        @if($estimate->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">
                                    {{ __('Discount') }}
                                    @if($estimate->discount_type === 'percentage')
                                        ({{ number_format($estimate->discount_value, 2) }}%)
                                    @endif
                                </span>
                                <span class="text-red-600 dark:text-red-400">-${{ number_format($estimate->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        @if($estimate->tax_total > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">{{ __('Tax') }}</span>
                                <span class="text-slate-900 dark:text-white">${{ number_format($estimate->tax_total, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-semibold pt-2 border-t border-slate-200 dark:border-slate-700">
                            <span class="text-slate-900 dark:text-white">{{ __('Total') }}</span>
                            <span class="text-slate-900 dark:text-white">${{ number_format($estimate->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Card -->
            @if($estimate->message_body)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ $estimate->message_title ?? __('Message') }}
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="prose prose-xs dark:prose-invert max-w-none text-sm text-slate-600 dark:text-slate-300">
                            {!! App\Support\RichText::sanitize($estimate->message_body) !!}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Email History Card -->
            @if($estimate->emailsSent->count() > 0)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Email History') }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Date') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Sent By') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('To') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('CC') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Subject') }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Opened') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($estimate->emailsSent as $email)
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                            {{ $email->sent_at->appDateTime() }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                            {{ $email->sentBy?->name ?? __('Unknown') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                            {{ $email->sent_to }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">
                                            {{ $email->cc ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                            {{ $email->subject }}
                                        </td>
                                        <td class="px-6 py-4 text-sm whitespace-nowrap">
                                            @if($email->opened_at)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                                    Opened {{ $email->opened_at->appDateTime() }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    {{ __('Not opened yet') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Actions Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Actions') }}</h3>
                </div>
                <div class="p-6 space-y-3">
                    <!-- PDF & Email Actions (all statuses) -->
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('estimates.pdf.download', $estimate->id) }}"
                        class="w-full justify-center"
                        icon="arrow-down-tray">
                        {{ __('Download PDF') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="secondary"
                        href="{{ route('estimates.pdf.view', $estimate->id) }}"
                        target="_blank"
                        class="w-full justify-center"
                        icon="printer">
                        {{ __('Print / Preview') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="primary"
                        class="w-full justify-center"
                        wire:click="$dispatch('open-modal', 'send-email-modal')"
                        icon="envelope">
                        {{ __('Email to Client') }}
                    </x-ui.button>

                    <div class="border-t border-slate-200 dark:border-slate-700 my-3"></div>

                    @if($estimate->isDraft())
                        <x-ui.button
                            variant="primary"
                            class="w-full justify-center"
                            wire:click="markAsSent"
                            wire:confirm="{{ __('Mark this estimate as sent?') }}"
                            icon="paper-airplane">
                            {{ __('Mark as Sent') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('estimates.edit', $estimate->id) }}"
                            class="w-full justify-center"
                            icon="edit">
                            {{ __('Edit Estimate') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="deleteEstimate"
                            wire:confirm="{{ __('Are you sure you want to delete this estimate? This cannot be undone.') }}"
                            icon="trash">
                            {{ __('Delete') }}
                        </x-ui.button>

                    @elseif($estimate->isSent())
                        <x-ui.button
                            variant="success"
                            class="w-full justify-center"
                            wire:click="markAsAccepted"
                            wire:confirm="{{ __('Mark this estimate as accepted?') }}"
                            icon="check">
                            {{ __('Mark Accepted') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="markAsDeclined"
                            wire:confirm="{{ __('Mark this estimate as declined?') }}"
                            icon="x">
                            {{ __('Mark Declined') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('estimates.edit', $estimate->id) }}"
                            class="w-full justify-center"
                            icon="edit">
                            {{ __('Edit Estimate') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="deleteEstimate"
                            wire:confirm="{{ __('Are you sure you want to delete this estimate? This cannot be undone.') }}"
                            icon="trash">
                            {{ __('Delete') }}
                        </x-ui.button>

                    @elseif($estimate->isAccepted())
                        <div class="text-center mb-3">
                            <svg class="w-8 h-8 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('This estimate has been accepted.') }}</p>
                        </div>
                        @if($estimate->converted_to_invoice_id)
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.show', $estimate->converted_to_invoice_id) }}"
                                class="w-full justify-center"
                                icon="eye">
                                View Invoice {{ $estimate->invoice?->invoice_number }}
                            </x-ui.button>
                        @else
                            <x-ui.button
                                variant="success"
                                class="w-full justify-center"
                                wire:click="convertToInvoice"
                                wire:confirm="{{ __('Convert this estimate to a draft invoice?') }}"
                                icon="document-duplicate">
                                {{ __('Convert to Invoice') }}
                            </x-ui.button>
                        @endif

                    @elseif($estimate->isDeclined())
                        <div class="text-center">
                            <svg class="w-8 h-8 mx-auto mb-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('This estimate has been declined.') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Summary Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Summary') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">{{ __('Items') }}</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-white">{{ $estimate->items->count() }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">{{ __('Subtotal') }}</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($estimate->subtotal, 2) }}</dd>
                        </div>
                        @if($estimate->discount_amount > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ __('Discount') }}</dt>
                                <dd class="text-sm font-medium text-red-600 dark:text-red-400">-${{ number_format($estimate->discount_amount, 2) }}</dd>
                            </div>
                        @endif
                        @if($estimate->tax_total > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">{{ __('Tax') }}</dt>
                                <dd class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($estimate->tax_total, 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                            <dt class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Total') }}</dt>
                            <dd class="text-base font-bold text-slate-900 dark:text-white">${{ number_format($estimate->total_amount, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Status History Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Status History') }}</h3>
                </div>
                <div class="p-6">
                    @if($estimate->statusHistories->count() > 0)
                        <div class="flow-root">
                            <ul class="-mb-8">
                                @foreach($estimate->statusHistories as $history)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-slate-800
                                                        @switch($history->new_status)
                                                            @case('draft') bg-slate-400 @break
                                                            @case('sent') bg-blue-500 @break
                                                            @case('accepted') bg-green-500 @break
                                                            @case('declined') bg-red-500 @break
                                                        @endswitch
                                                    ">
                                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            @switch($history->new_status)
                                                                @case('accepted')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                    @break
                                                                @case('declined')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                    @break
                                                                @default
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            @endswitch
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div>
                                                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                                                            {{ $history->getChangeDescription() }}
                                                        </p>
                                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                                            by {{ $history->changedBy?->name ?? __('System') }}
                                                        </p>
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $history->created_at->appDateTime() }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No status changes recorded.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Send Email Modal -->
    @can('estimates.send')
        <livewire:estimate.estimate-send-email :estimate="$estimate" />
    @endcan
</div>
