<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $invoice->invoice_number }}
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $invoice->status_color }}">
                        {{ $invoice->status_label }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $invoice->client->company_name }}
                    @if($invoice->project)
                        / {{ $invoice->project->project_name }}
                    @endif
                    @if($invoice->jobSite)
                        / {{ $invoice->jobSite->job_site_name }}
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button variant="secondary" href="{{ route('invoices.index') }}" icon="arrow-left">
                    Back to List
                </x-ui.button>
                @if($invoice->canBeEdited())
                    <x-ui.button variant="secondary" href="{{ route('invoices.edit', $invoice->id) }}" icon="edit">
                        Edit
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
            <!-- Invoice Details Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Invoice Details</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Client</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->client->company_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Invoice Number</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->invoice_number }}</dd>
                        </div>
                        @if($invoice->project)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Project</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->project->project_name }}</dd>
                            </div>
                        @endif
                        @if($invoice->jobSite)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Job Site</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->jobSite->job_site_name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Invoice Date</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->invoice_date->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Terms</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->terms_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Due Date</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white {{ $invoice->isPastDue() ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">
                                {{ $invoice->due_date->format('M d, Y') }}
                                @if($invoice->isPastDue())
                                    (Past Due)
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Created By</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->createdBy?->name ?? 'Unknown' }}</dd>
                        </div>
                        @if($invoice->estimate)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">From Estimate</dt>
                                <dd class="mt-1 text-sm">
                                    <a href="{{ route('estimates.show', $invoice->estimate_id) }}" class="text-[#3F5189] hover:underline">
                                        {{ $invoice->estimate->estimate_number }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        @if($invoice->sent_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Sent At</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->sent_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($invoice->paid_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Paid At</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->paid_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($invoice->notes)
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Internal Notes</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $invoice->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Items Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Item</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Unit Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Discount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Tax</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($invoice->items as $item)
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
                            <span class="text-slate-600 dark:text-slate-400">Subtotal</span>
                            <span class="text-slate-900 dark:text-white">${{ number_format($invoice->subtotal, 2) }}</span>
                        </div>
                        @if($invoice->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">
                                    Discount
                                    @if($invoice->discount_type === 'percentage')
                                        ({{ number_format($invoice->discount_value, 2) }}%)
                                    @endif
                                </span>
                                <span class="text-red-600 dark:text-red-400">-${{ number_format($invoice->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        @if($invoice->tax_total > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">Tax</span>
                                <span class="text-slate-900 dark:text-white">${{ number_format($invoice->tax_total, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-semibold pt-2 border-t border-slate-200 dark:border-slate-700">
                            <span class="text-slate-900 dark:text-white">Total</span>
                            <span class="text-slate-900 dark:text-white">${{ number_format($invoice->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Card -->
            @if($invoice->message_body)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ $invoice->message_title ?? 'Message' }}
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="prose prose-xs dark:prose-invert max-w-none text-sm text-slate-600 dark:text-slate-300">
                            {!! $invoice->message_body !!}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Email History Card -->
            @if($invoice->emailsSent->count() > 0)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Email History</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Sent By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">To</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">CC</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Subject</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Opened</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach($invoice->emailsSent as $email)
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                            {{ $email->sent_at->format('M d, Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-900 dark:text-white whitespace-nowrap">
                                            {{ $email->sentBy?->name ?? 'Unknown' }}
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
                                                    Opened {{ $email->opened_at->format('M d, Y H:i') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    Not opened yet
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
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Actions</h3>
                </div>
                <div class="p-6 space-y-3">
                    @if($invoice->isDraft())
                        <x-ui.button
                            variant="primary"
                            class="w-full justify-center"
                            x-on:click="$dispatch('open-modal', 'send-email-modal')"
                            icon="paper-airplane">
                            Email Invoice
                        </x-ui.button>
                        <x-ui.button
                            variant="outline"
                            class="w-full justify-center"
                            wire:click="markAsSent"
                            wire:confirm="Mark this invoice as sent (without emailing)?"
                            icon="check">
                            Mark as Sent
                        </x-ui.button>
                        <div class="flex gap-2">
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.view', $invoice->id) }}"
                                target="_blank"
                                class="flex-1 justify-center"
                                icon="eye">
                                View PDF
                            </x-ui.button>
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.download', $invoice->id) }}"
                                class="flex-1 justify-center"
                                icon="arrow-down-tray">
                                Download
                            </x-ui.button>
                        </div>
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('invoices.edit', $invoice->id) }}"
                            class="w-full justify-center"
                            icon="edit">
                            Edit Invoice
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="deleteInvoice"
                            wire:confirm="Are you sure you want to delete this invoice? This cannot be undone."
                            icon="trash">
                            Delete
                        </x-ui.button>

                    @elseif($invoice->isSent())
                        <x-ui.button
                            variant="primary"
                            class="w-full justify-center"
                            x-on:click="$dispatch('open-modal', 'send-email-modal')"
                            icon="paper-airplane">
                            Email Invoice
                        </x-ui.button>
                        <x-ui.button
                            variant="warning"
                            class="w-full justify-center"
                            wire:click="markAsPending"
                            wire:confirm="Mark this invoice as pending payment?"
                            icon="clock">
                            Mark as Pending
                        </x-ui.button>
                        <x-ui.button
                            variant="success"
                            class="w-full justify-center"
                            wire:click="markAsPaid"
                            wire:confirm="Mark this invoice as paid?"
                            icon="check">
                            Mark as Paid
                        </x-ui.button>
                        <div class="flex gap-2">
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.view', $invoice->id) }}"
                                target="_blank"
                                class="flex-1 justify-center"
                                icon="eye">
                                View PDF
                            </x-ui.button>
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.download', $invoice->id) }}"
                                class="flex-1 justify-center"
                                icon="arrow-down-tray">
                                Download
                            </x-ui.button>
                        </div>
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('invoices.edit', $invoice->id) }}"
                            class="w-full justify-center"
                            icon="edit">
                            Edit Invoice
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="deleteInvoice"
                            wire:confirm="Are you sure you want to delete this invoice? This cannot be undone."
                            icon="trash">
                            Delete
                        </x-ui.button>

                    @elseif($invoice->isPending())
                        @if($invoice->isPastDue())
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-center gap-2 text-sm font-medium text-red-800 dark:text-red-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Past Due
                                </div>
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                                    Due date was {{ $invoice->due_date->format('M d, Y') }}
                                </p>
                            </div>
                        @endif
                        <x-ui.button
                            variant="success"
                            class="w-full justify-center"
                            wire:click="markAsPaid"
                            wire:confirm="Mark this invoice as paid?"
                            icon="check">
                            Mark as Paid
                        </x-ui.button>
                        <x-ui.button
                            variant="primary"
                            class="w-full justify-center"
                            x-on:click="$dispatch('open-modal', 'send-email-modal')"
                            icon="paper-airplane">
                            Email Invoice
                        </x-ui.button>
                        <div class="flex gap-2">
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.view', $invoice->id) }}"
                                target="_blank"
                                class="flex-1 justify-center"
                                icon="eye">
                                View PDF
                            </x-ui.button>
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.download', $invoice->id) }}"
                                class="flex-1 justify-center"
                                icon="arrow-down-tray">
                                Download
                            </x-ui.button>
                        </div>

                    @elseif($invoice->isPaid())
                        <div class="text-center">
                            <svg class="w-8 h-8 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm font-medium text-green-600 dark:text-green-400">Paid</p>
                            @if($invoice->paid_at)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    {{ $invoice->paid_at->format('M d, Y H:i') }}
                                </p>
                            @endif
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.view', $invoice->id) }}"
                                target="_blank"
                                class="flex-1 justify-center"
                                icon="eye">
                                View PDF
                            </x-ui.button>
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('invoices.pdf.download', $invoice->id) }}"
                                class="flex-1 justify-center"
                                icon="arrow-down-tray">
                                Download
                            </x-ui.button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Summary Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Summary</h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Items</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-white">{{ $invoice->items->count() }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Subtotal</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($invoice->subtotal, 2) }}</dd>
                        </div>
                        @if($invoice->discount_amount > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Discount</dt>
                                <dd class="text-sm font-medium text-red-600 dark:text-red-400">-${{ number_format($invoice->discount_amount, 2) }}</dd>
                            </div>
                        @endif
                        @if($invoice->tax_total > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Tax</dt>
                                <dd class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($invoice->tax_total, 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                            <dt class="text-base font-semibold text-slate-900 dark:text-white">Total</dt>
                            <dd class="text-base font-bold text-slate-900 dark:text-white">${{ number_format($invoice->total_amount, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Source Estimate Card -->
            @if($invoice->estimate)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Source Estimate</h3>
                    </div>
                    <div class="p-6">
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-2">Created from estimate:</p>
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('estimates.show', $invoice->estimate_id) }}"
                            class="w-full justify-center"
                            icon="eye">
                            {{ $invoice->estimate->estimate_number }}
                        </x-ui.button>
                    </div>
                </div>
            @endif

            <!-- Status History Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Status History</h3>
                </div>
                <div class="p-6">
                    @if($invoice->statusHistories->count() > 0)
                        <div class="flow-root">
                            <ul class="-mb-8">
                                @foreach($invoice->statusHistories as $history)
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
                                                            @case('pending') bg-yellow-400 @break
                                                            @case('paid') bg-green-500 @break
                                                        @endswitch
                                                    ">
                                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            @switch($history->new_status)
                                                                @case('paid')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
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
                                                            by {{ $history->changedBy?->name ?? 'System' }}
                                                        </p>
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $history->created_at->format('M d, Y H:i') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">No status changes recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Send Email Modal -->
    @if(!$invoice->isPaid())
        <livewire:invoice.invoice-send-email :invoice="$invoice" />
    @endif
</div>
