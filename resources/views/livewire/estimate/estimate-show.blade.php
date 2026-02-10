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
                    Back to List
                </x-ui.button>
                @if($estimate->canBeEdited())
                    <x-ui.button variant="secondary" href="{{ route('estimates.edit', $estimate->id) }}" icon="edit">
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
            <!-- Estimate Details Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Estimate Details</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Client</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->client->company_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Estimate Number</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->estimate_number }}</dd>
                        </div>
                        @if($estimate->project)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Project</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->project->project_name }}</dd>
                            </div>
                        @endif
                        @if($estimate->jobSite)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Job Site</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->jobSite->job_site_name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Estimate Date</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->estimate_date->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Terms</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->terms_label }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Due Date</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->due_date->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Created By</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->createdBy?->name ?? 'Unknown' }}</dd>
                        </div>
                        @if($estimate->sent_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Sent At</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->sent_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($estimate->accepted_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Accepted At</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->accepted_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($estimate->declined_at)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Declined At</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->declined_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($estimate->notes)
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">Internal Notes</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $estimate->notes }}</dd>
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
                            <span class="text-slate-600 dark:text-slate-400">Subtotal</span>
                            <span class="text-slate-900 dark:text-white">${{ number_format($estimate->subtotal, 2) }}</span>
                        </div>
                        @if($estimate->discount_amount > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">
                                    Discount
                                    @if($estimate->discount_type === 'percentage')
                                        ({{ number_format($estimate->discount_value, 2) }}%)
                                    @endif
                                </span>
                                <span class="text-red-600 dark:text-red-400">-${{ number_format($estimate->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        @if($estimate->tax_total > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">Tax</span>
                                <span class="text-slate-900 dark:text-white">${{ number_format($estimate->tax_total, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-semibold pt-2 border-t border-slate-200 dark:border-slate-700">
                            <span class="text-slate-900 dark:text-white">Total</span>
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
                            {{ $estimate->message_title ?? 'Message' }}
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="prose prose-sm dark:prose-invert max-w-none">
                            {!! $estimate->message_body !!}
                        </div>
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
                    @if($estimate->isDraft())
                        <x-ui.button
                            variant="primary"
                            class="w-full justify-center"
                            wire:click="markAsSent"
                            wire:confirm="Mark this estimate as sent?"
                            icon="paper-airplane">
                            Mark as Sent
                        </x-ui.button>
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('estimates.edit', $estimate->id) }}"
                            class="w-full justify-center"
                            icon="edit">
                            Edit Estimate
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="deleteEstimate"
                            wire:confirm="Are you sure you want to delete this estimate? This cannot be undone."
                            icon="trash">
                            Delete
                        </x-ui.button>

                    @elseif($estimate->isSent())
                        <x-ui.button
                            variant="success"
                            class="w-full justify-center"
                            wire:click="markAsAccepted"
                            wire:confirm="Mark this estimate as accepted?"
                            icon="check">
                            Mark Accepted
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="markAsDeclined"
                            wire:confirm="Mark this estimate as declined?"
                            icon="x">
                            Mark Declined
                        </x-ui.button>
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('estimates.edit', $estimate->id) }}"
                            class="w-full justify-center"
                            icon="edit">
                            Edit Estimate
                        </x-ui.button>
                        <x-ui.button
                            variant="danger"
                            class="w-full justify-center"
                            wire:click="deleteEstimate"
                            wire:confirm="Are you sure you want to delete this estimate? This cannot be undone."
                            icon="trash">
                            Delete
                        </x-ui.button>

                    @elseif($estimate->isAccepted())
                        <div class="text-center">
                            <svg class="w-8 h-8 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-slate-500 dark:text-slate-400">This estimate has been accepted.</p>
                        </div>

                    @elseif($estimate->isDeclined())
                        <div class="text-center">
                            <svg class="w-8 h-8 mx-auto mb-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-slate-500 dark:text-slate-400">This estimate has been declined.</p>
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
                            <dd class="text-sm font-medium text-slate-900 dark:text-white">{{ $estimate->items->count() }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Subtotal</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($estimate->subtotal, 2) }}</dd>
                        </div>
                        @if($estimate->discount_amount > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Discount</dt>
                                <dd class="text-sm font-medium text-red-600 dark:text-red-400">-${{ number_format($estimate->discount_amount, 2) }}</dd>
                            </div>
                        @endif
                        @if($estimate->tax_total > 0)
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Tax</dt>
                                <dd class="text-sm font-medium text-slate-900 dark:text-white">${{ number_format($estimate->tax_total, 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between pt-3 border-t border-slate-200 dark:border-slate-700">
                            <dt class="text-base font-semibold text-slate-900 dark:text-white">Total</dt>
                            <dd class="text-base font-bold text-slate-900 dark:text-white">${{ number_format($estimate->total_amount, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
