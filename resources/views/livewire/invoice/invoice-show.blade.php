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
                            wire:click="openPaymentModal"
                            icon="banknotes">
                            Record Payment
                        </x-ui.button>
                        @if($cardPointegConfigured && $invoice->getBalanceDue() > 0)
                            <div x-data="{ copied: false }">
                                <button
                                    type="button"
                                    x-on:click="navigator.clipboard.writeText('{{ $invoice->getPaymentUrl() }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                    </svg>
                                    <svg x-show="copied" x-cloak class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span x-show="!copied">Copy Payment Link</span>
                                    <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400">Copied!</span>
                                </button>
                            </div>
                        @endif
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

                    @elseif($invoice->isPending() || $invoice->isPartial())
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
                        @if($invoice->isPartial())
                            <div class="p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-orange-800 dark:text-orange-300">Payment Progress</span>
                                    <span class="font-bold text-orange-800 dark:text-orange-300">{{ $invoice->getPaymentProgress() }}%</span>
                                </div>
                                <div class="w-full bg-orange-200 dark:bg-orange-900/50 rounded-full h-2 mt-2">
                                    <div class="bg-orange-500 h-2 rounded-full" style="width: {{ $invoice->getPaymentProgress() }}%"></div>
                                </div>
                                <p class="text-xs text-orange-600 dark:text-orange-400 mt-1">
                                    ${{ number_format($invoice->getAmountPaid(), 2) }} of ${{ number_format($invoice->total_amount, 2) }} paid
                                </p>
                            </div>
                        @endif
                        <x-ui.button
                            variant="success"
                            class="w-full justify-center"
                            wire:click="openPaymentModal"
                            icon="banknotes">
                            Record Payment
                        </x-ui.button>
                        @if($cardPointegConfigured && $invoice->getBalanceDue() > 0)
                            <div x-data="{ copied: false }">
                                <button
                                    type="button"
                                    x-on:click="navigator.clipboard.writeText('{{ $invoice->getPaymentUrl() }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                    </svg>
                                    <svg x-show="copied" x-cloak class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span x-show="!copied">Copy Payment Link</span>
                                    <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400">Copied!</span>
                                </button>
                            </div>
                        @endif
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
                            <p class="text-sm font-medium text-green-600 dark:text-green-400">Paid in Full</p>
                            @if($invoice->paid_at)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    {{ $invoice->paid_at->format('M d, Y H:i') }}
                                </p>
                            @endif
                            @if($invoice->payments->where('status', 'completed')->count() > 0)
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $invoice->payments->where('status', 'completed')->count() }} payment(s)
                                </p>
                            @endif
                        </div>
                        <x-ui.button
                            variant="primary"
                            class="w-full justify-center mt-3"
                            x-on:click="$dispatch('open-modal', 'send-email-modal')"
                            icon="paper-airplane">
                            Email Invoice
                        </x-ui.button>
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
                        @if($invoice->payments->where('status', 'completed')->count() > 0)
                            <div class="flex justify-between pt-2">
                                <dt class="text-sm text-green-600 dark:text-green-400">Amount Paid</dt>
                                <dd class="text-sm font-medium text-green-600 dark:text-green-400">${{ number_format($invoice->getAmountPaid(), 2) }}</dd>
                            </div>
                            <div class="flex justify-between pt-1">
                                <dt class="text-sm font-semibold {{ $invoice->getBalanceDue() > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">Balance Due</dt>
                                <dd class="text-sm font-bold {{ $invoice->getBalanceDue() > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }}">${{ number_format($invoice->getBalanceDue(), 2) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Payment History Card -->
            @if($invoice->payments->count() > 0)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Payment History</h3>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($invoice->payments as $payment)
                            <div class="p-4">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-slate-900 dark:text-white">
                                                #{{ $payment->payment_number }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $payment->getStatusColor() }}">
                                                {{ $payment->getStatusLabel() }}
                                            </span>
                                        </div>
                                        <div class="mt-1 text-sm text-slate-900 dark:text-white font-semibold">
                                            ${{ number_format($payment->amount, 2) }}
                                        </div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 space-y-0.5">
                                            <p>
                                                {{ $payment->payment_date->format('M d, Y') }} &middot;
                                                @if($payment->gateway === 'cardpointe')
                                                    {{ $payment->getCardDisplayName() }}
                                                @else
                                                    {{ $payment->getPaymentMethodLabel() }}
                                                @endif
                                            </p>
                                            @if($payment->gateway === 'cardpointe' && $payment->gateway_transaction_id)
                                                <p>Txn: {{ $payment->gateway_transaction_id }}</p>
                                            @endif
                                            @if($payment->reference_number)
                                                <p>Ref: {{ $payment->reference_number }}</p>
                                            @endif
                                            @if($payment->notes)
                                                <p>{{ $payment->notes }}</p>
                                            @endif
                                            <p>by {{ $payment->createdBy?->name ?? 'Unknown' }}</p>
                                        </div>
                                    </div>
                                    @if($payment->isCompleted())
                                        <x-ui.button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="voidPayment({{ $payment->id }})"
                                            wire:confirm="Are you sure you want to void this payment? The invoice status will be recalculated."
                                            icon="x">
                                            Void
                                        </x-ui.button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                                                            @case('partial') bg-orange-500 @break
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
    <livewire:invoice.invoice-send-email :invoice="$invoice" />

    <!-- Record Payment Modal -->
    @if($showPaymentModal)
        <x-ui.modal name="record-payment-modal" :show="true" maxWidth="lg">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Record Payment</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Balance due: <span class="font-semibold">${{ number_format($invoice->getBalanceDue(), 2) }}</span>
                </p>
            </div>

            {{-- Tabs --}}
            @if($cardPointegConfigured)
                <div class="flex border-b border-slate-200 dark:border-slate-700">
                    <button
                        type="button"
                        wire:click="$set('paymentType', 'manual')"
                        class="flex-1 px-4 py-3 text-sm font-medium text-center border-b-2 transition-colors
                            {{ $paymentType === 'manual'
                                ? 'border-[#3F5189] text-[#3F5189] dark:text-blue-400 dark:border-blue-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300' }}">
                        Manual Payment
                    </button>
                    <button
                        type="button"
                        wire:click="$set('paymentType', 'credit_card')"
                        class="flex-1 px-4 py-3 text-sm font-medium text-center border-b-2 transition-colors
                            {{ $paymentType === 'credit_card'
                                ? 'border-[#3F5189] text-[#3F5189] dark:text-blue-400 dark:border-blue-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300' }}">
                        Credit Card
                    </button>
                </div>
            @endif

            {{-- Manual Payment Tab --}}
            @if($paymentType === 'manual')
                <form wire:submit="recordPayment">
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="paymentAmount" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount *</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-500 sm:text-sm">$</span>
                                </div>
                                <input
                                    type="number"
                                    id="paymentAmount"
                                    wire:model="paymentAmount"
                                    step="0.01"
                                    min="0.01"
                                    max="{{ $invoice->getBalanceDue() }}"
                                    class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="0.00"
                                    required>
                            </div>
                            @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="paymentMethod" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Payment Method *</label>
                            <select
                                id="paymentMethod"
                                wire:model="paymentMethod"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                required>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                @if(config('app.country') === 'BR')
                                    <option value="pix">PIX</option>
                                @endif
                                <option value="other">Other</option>
                            </select>
                            @error('paymentMethod') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="paymentDate" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Payment Date *</label>
                            <input
                                type="date"
                                id="paymentDate"
                                wire:model="paymentDate"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                required>
                            @error('paymentDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="paymentReference" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Reference Number</label>
                            <input
                                type="text"
                                id="paymentReference"
                                wire:model="paymentReference"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Check #, transaction ID, etc.">
                            @error('paymentReference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="paymentNotes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
                            <textarea
                                id="paymentNotes"
                                wire:model="paymentNotes"
                                rows="2"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Optional notes about this payment"></textarea>
                            @error('paymentNotes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                        <x-ui.button variant="secondary" type="button" wire:click="$set('showPaymentModal', false)">
                            Cancel
                        </x-ui.button>
                        <x-ui.button variant="success" type="submit" icon="check">
                            Record Payment
                        </x-ui.button>
                    </div>
                </form>
            @endif

            {{-- Credit Card Tab --}}
            @if($paymentType === 'credit_card' && $cardPointegConfigured)
                <div
                    x-data="{
                        useNewCard: {{ count($clientPaymentMethods) > 0 ? 'false' : 'true' }},
                        tokenReceived: false,
                        displayAmount: '{{ number_format((float) $paymentAmount, 2) }}',
                        init() {
                            window.addEventListener('message', (event) => {
                                try {
                                    let data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                                    if (data.token) {
                                        this.tokenReceived = true;
                                        $wire.setCardToken(data.token);
                                    }
                                } catch (e) {
                                    // Ignore non-JSON messages
                                }
                            });
                        }
                    }"
                >
                    <div class="p-6 space-y-4">
                        {{-- Amount --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Amount *</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-500 sm:text-sm">$</span>
                                </div>
                                <input
                                    type="number"
                                    wire:model="paymentAmount"
                                    x-on:input="displayAmount = parseFloat($event.target.value || 0).toFixed(2)"
                                    step="0.01"
                                    min="0.01"
                                    max="{{ $invoice->getBalanceDue() }}"
                                    class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="0.00"
                                    required>
                            </div>
                            @error('paymentAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Saved Cards --}}
                        @if(count($clientPaymentMethods) > 0)
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Payment Method</label>
                                <div class="space-y-2">
                                    @foreach($clientPaymentMethods as $pm)
                                        <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                            :class="!useNewCard && $wire.selectedPaymentMethodId == {{ $pm['id'] }}
                                                ? 'border-[#3F5189] bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500'
                                                : 'border-slate-200 dark:border-slate-600 hover:border-slate-300 dark:hover:border-slate-500'">
                                            <input
                                                type="radio"
                                                name="card_selection"
                                                value="{{ $pm['id'] }}"
                                                wire:model="selectedPaymentMethodId"
                                                x-on:change="useNewCard = false; tokenReceived = false"
                                                class="text-[#3F5189] focus:ring-[#3F5189]">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                </svg>
                                                <span class="text-sm text-slate-900 dark:text-white">{{ $pm['display_name'] }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                    <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                        :class="useNewCard
                                            ? 'border-[#3F5189] bg-blue-50 dark:bg-blue-900/20 dark:border-blue-500'
                                            : 'border-slate-200 dark:border-slate-600 hover:border-slate-300 dark:hover:border-slate-500'">
                                        <input
                                            type="radio"
                                            name="card_selection"
                                            value="new"
                                            x-on:change="useNewCard = true; $wire.set('selectedPaymentMethodId', null)"
                                            :checked="useNewCard"
                                            class="text-[#3F5189] focus:ring-[#3F5189]">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                            <span class="text-sm text-slate-900 dark:text-white">Use a new card</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        @endif

                        {{-- New Card Fields --}}
                        <div x-show="useNewCard" x-transition class="space-y-4">
                            {{-- Card Number (iFrame tokenizer) --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Card Number *</label>
                                <div class="rounded-lg border border-slate-300 dark:border-slate-600 overflow-hidden bg-white">
                                    <iframe
                                        src="{{ $iframeUrl }}"
                                        frameborder="0"
                                        scrolling="no"
                                        style="width: 100%; height: 38px;"
                                    ></iframe>
                                </div>
                                <div x-show="tokenReceived" class="mt-1 flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Card tokenized
                                </div>
                            </div>

                            {{-- Name on Card --}}
                            <div>
                                <label for="cardName" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name on Card *</label>
                                <input
                                    type="text"
                                    id="cardName"
                                    wire:model="cardName"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="John Doe">
                                @error('cardName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Expiry + CVV (side by side) --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="cardExpiry" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Expiration *</label>
                                    <input
                                        type="text"
                                        id="cardExpiry"
                                        wire:model="cardExpiry"
                                        maxlength="4"
                                        inputmode="numeric"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="MMYY">
                                    @error('cardExpiry') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="cardCvv" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">CVV *</label>
                                    <input
                                        type="text"
                                        id="cardCvv"
                                        wire:model="cardCvv"
                                        maxlength="4"
                                        inputmode="numeric"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="123">
                                    @error('cardCvv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- Billing Zip Code --}}
                            <div>
                                <label for="cardZip" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Billing Zip Code *</label>
                                <input
                                    type="text"
                                    id="cardZip"
                                    wire:model="cardZip"
                                    maxlength="10"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="12345">
                                @error('cardZip') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Save card checkbox --}}
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="saveCard"
                                    class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Save this card for future payments</span>
                            </label>
                        </div>

                        {{-- Error message --}}
                        @if($cardPaymentError)
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-sm text-red-700 dark:text-red-300">{{ $cardPaymentError }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                        <x-ui.button variant="secondary" type="button" wire:click="$set('showPaymentModal', false)">
                            Cancel
                        </x-ui.button>
                        <button
                            type="button"
                            wire:click="processCardPayment"
                            wire:loading.attr="disabled"
                            wire:target="processCardPayment"
                            :disabled="useNewCard && !tokenReceived"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[#3F5189] text-white text-sm font-medium rounded-lg hover:bg-[#354570] focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <svg wire:loading wire:target="processCardPayment" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg wire:loading.remove wire:target="processCardPayment" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <span wire:loading wire:target="processCardPayment">Processing...</span>
                            <span wire:loading.remove wire:target="processCardPayment" x-text="'Pay $' + displayAmount"></span>
                        </button>
                    </div>
                </div>
            @endif
        </x-ui.modal>
    @endif
</div>
