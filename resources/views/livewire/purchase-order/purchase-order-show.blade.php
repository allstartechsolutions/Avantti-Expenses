@php
    // The approval ceiling is a fact about this order's money, so the button
    // obeys it and the sidebar says why when it does not.
    $resolver = app(\App\Services\PermissionResolver::class);
    $mayApprove = auth()->user()->can('purchase-orders.approve', $purchaseOrder);
    $withinCeiling = $resolver->withinApprovalLimit(auth()->user(), $purchaseOrder->totalInCents(), $purchaseOrder);
    $ceiling = $resolver->approvalLimit(auth()->user(), $purchaseOrder);

    // Delivery columns only mean something once the order has been approved —
    // nothing arrives against a draft.
    $showsDelivery = $purchaseOrder->receiptStatus() !== null;
@endphp
<div>
    {{-- Breadcrumbs --}}
    @php
        $breadcrumbs = [
            ['label' => __('Projects'), 'url' => route('projects.index')],
            ['label' => $purchaseOrder->project->project_name, 'url' => route('projects.overview', $purchaseOrder->project)],
        ];

        if ($purchaseOrder->jobSite) {
            $breadcrumbs[] = ['label' => __('Job Sites'), 'url' => route('projects.jobsites', $purchaseOrder->project)];
            $breadcrumbs[] = ['label' => $purchaseOrder->jobSite->job_site_name, 'url' => route('jobsites.overview', $purchaseOrder->jobSite)];
            $breadcrumbs[] = ['label' => __('Purchase Orders'), 'url' => route('jobsites.purchase-orders', $purchaseOrder->jobSite)];
        } else {
            $breadcrumbs[] = ['label' => __('Purchase Orders'), 'url' => route('projects.purchase-orders', $purchaseOrder->project)];
        }

        $breadcrumbs[] = ['label' => __('Purchase Order') . ' #' . ($purchaseOrder->po_number ?: $purchaseOrder->id)];
    @endphp
    <x-ui.breadcrumb :items="$breadcrumbs" />

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ __('Purchase Order') }} #{{ $purchaseOrder->id }}
                        @if($purchaseOrder->po_number)
                            <span class="text-slate-500">({{ $purchaseOrder->po_number }})</span>
                        @endif
                    </h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @switch($purchaseOrder->status)
                            @case('draft') bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 @break
                            @case('pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 @break
                            @case('approved') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 @break
                            @case('rejected') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 @break
                            @case('cancelled') bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300 @break
                        @endswitch
                    ">
                        {{ $purchaseOrder->getStatusLabel() }}
                        @if($purchaseOrder->revision_number > 1)
                            (Rev. {{ $purchaseOrder->revision_number }})
                        @endif
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $purchaseOrder->project->project_name }}
                    @if($purchaseOrder->jobSite)
                        / {{ $purchaseOrder->jobSite->job_site_name }}
                    @else
                        / {{ __('Project Level') }}
                    @endif
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ $purchaseOrder->job_site_id ? route('jobsites.show', ['jobSite' => $purchaseOrder->job_site_id, 'tab' => 'purchaseorders']) : route('projects.purchase-orders', $purchaseOrder->project_id) }}"
                    icon="arrow-left">
                    {{ __('Back to List') }}
                </x-ui.button>

                @if($purchaseOrder->canBeEdited())
                    @can('purchase-orders.edit', $purchaseOrder)
                        <x-ui.button
                            variant="secondary"
                            href="{{ route('purchase-orders.edit', $purchaseOrder->id) }}"
                            icon="edit">
                            {{ __('Edit') }}
                        </x-ui.button>
                    @endcan
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
            <!-- PO Details Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Purchase Order Details') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Date') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->po_date->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Supplier') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->supplier?->name ?? __('Not specified') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Created By') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->createdBy?->name ?? __('Unknown') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Created At') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        @if($purchaseOrder->approvedBy)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Approved By') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->approvedBy->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Approved At') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->approved_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                        @if($purchaseOrder->notes)
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Notes') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->notes }}</dd>
                            </div>
                        @endif
                        @if($purchaseOrder->receipt_path)
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Quote/Document') }}</dt>
                                <dd class="mt-1">
                                    <a href="{{ route('files.show', ['path' => $purchaseOrder->receipt_path]) }}" target="_blank" class="inline-flex items-center text-sm text-[#3F5189] hover:text-[#2F3F6F]">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        {{ __('View Document') }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Attachments Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="p-6">
                    <livewire:shared.attachments
                        model-type="purchase-order"
                        :model-id="$purchaseOrder->id"
                        :key="'po-attachments-'.$purchaseOrder->id" />
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Cost Code') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Item') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Ordered') }}</th>
                                @if($showsDelivery)
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Received') }}</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Outstanding') }}</th>
                                @endif
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Unit Price') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($purchaseOrder->items as $item)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white">
                                        {{ $item->budgetItem?->code ?? __('Misc') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->item_name }}</div>
                                        @if($item->description)
                                            <div class="text-xs text-slate-500">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white text-right">
                                        {{ $item->quantity }} {{ $item->unit }}
                                    </td>
                                    @if($showsDelivery)
                                        <td class="px-6 py-4 text-sm text-right {{ $item->isFullyReceived() ? 'text-green-600 dark:text-green-400 font-medium' : 'text-slate-900 dark:text-white' }}">
                                            {{ (float) $item->received_quantity > 0 ? rtrim(rtrim(number_format((float) $item->received_quantity, 2, '.', ''), '0'), '.').' '.$item->unit : '—' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-right {{ $item->isFullyReceived() ? 'text-slate-400 dark:text-slate-500' : 'text-amber-600 dark:text-amber-400 font-medium' }}">
                                            {{ $item->isFullyReceived() ? '—' : rtrim(rtrim(number_format($item->outstandingQuantity(), 2, '.', ''), '0'), '.').' '.$item->unit }}
                                        </td>
                                    @endif
                                    <td class="px-6 py-4 text-sm text-slate-900 dark:text-white text-right">
                                        {{ Number::currency($item->unit_price, config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white text-right">
                                        {{ Number::currency($item->total_amount, config('app.currency'), config('app.locale')) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-900/50">
                            {{-- Freight, tax and a discount come from the winning
                                 proposal and sit on the order, not on its lines,
                                 so the lines keep the vendor's quoted prices.
                                 They are shown here or the total looks wrong. --}}
                            @php($hasExtras = $purchaseOrder->freight_amount || $purchaseOrder->tax_amount || $purchaseOrder->discount_amount)
                            @if($hasExtras)
                                <tr>
                                    <td colspan="{{ $showsDelivery ? 6 : 4 }}" class="px-6 pt-4 text-sm text-slate-500 dark:text-slate-400 text-right">{{ __('Items:') }}</td>
                                    <td class="px-6 pt-4 text-sm text-slate-600 dark:text-slate-300 text-right">
                                        {{ Number::currency($purchaseOrder->itemsTotal(), config('app.currency'), config('app.locale')) }}
                                    </td>
                                </tr>
                                @foreach([
                                    ['label' => __('Freight:'), 'amount' => $purchaseOrder->freight_amount, 'sign' => 1],
                                    ['label' => __('Tax:'), 'amount' => $purchaseOrder->tax_amount, 'sign' => 1],
                                    ['label' => __('Discount:'), 'amount' => $purchaseOrder->discount_amount, 'sign' => -1],
                                ] as $extra)
                                    @if($extra['amount'] > 0)
                                        <tr>
                                            <td colspan="{{ $showsDelivery ? 6 : 4 }}" class="px-6 py-1 text-sm text-slate-500 dark:text-slate-400 text-right">{{ $extra['label'] }}</td>
                                            <td class="px-6 py-1 text-sm text-slate-600 dark:text-slate-300 text-right">
                                                {{ $extra['sign'] < 0 ? '−' : '' }}{{ Number::currency($extra['amount'], config('app.currency'), config('app.locale')) }}
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endif
                            <tr>
                                <td colspan="{{ $showsDelivery ? 6 : 4 }}" class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white text-right">{{ __('Total:') }}</td>
                                <td class="px-6 py-4 text-lg font-bold text-slate-900 dark:text-white text-right">
                                    {{ Number::currency($purchaseOrder->total_amount, config('app.currency'), config('app.locale')) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payment Information Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Payment Information') }}</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Payment Method') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->getPaymentMethodLabel() ?? __('Not specified') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Installments') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                @if($purchaseOrder->total_installments > 1)
                                    {{ $purchaseOrder->total_installments }}x ({{ \App\Models\PurchaseOrder::paymentFrequencyLabel($purchaseOrder->payment_frequency) }})
                                @else
                                    {{ __('Single payment') }}
                                @endif
                            </dd>
                        </div>
                        @if($purchaseOrder->payment_due_date)
                            <div>
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Due Date') }}</dt>
                                <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->payment_due_date->format('M d, Y') }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Auto Payment') }}</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $purchaseOrder->is_auto_payment ? __('Yes') : __('No') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Actions Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Actions') }}</h3>
                </div>
                <div class="p-6 space-y-3">
                    @if($purchaseOrder->isDraft())
                        <!-- Draft Actions -->
                        @can('purchase-orders.edit', $purchaseOrder)
                            <x-ui.button
                                variant="primary"
                                class="w-full justify-center"
                                wire:click="submitForApproval"
                                wire:confirm="{{ __('Submit this purchase order for approval?') }}"
                                icon="paper-airplane">
                                {{ __('Submit for Approval') }}
                            </x-ui.button>
                            <x-ui.button
                                variant="danger"
                                class="w-full justify-center"
                                wire:click="openCancelModal"
                                icon="x">
                                {{ __('Cancel PO') }}
                            </x-ui.button>
                        @endcan

                    @elseif($purchaseOrder->isPending())
                        <!-- Pending: approving commits the money -->
                        @if($mayApprove && $withinCeiling)
                            <x-ui.button
                                variant="success"
                                class="w-full justify-center"
                                wire:click="approve"
                                wire:confirm="{{ __('Approve this purchase order? This will create an expense.') }}"
                                icon="check">
                                {{ __('Approve') }}
                            </x-ui.button>
                        @elseif($mayApprove)
                            <p class="text-xs text-amber-700 dark:text-amber-400 text-center">
                                {{ __('This order commits :amount, which is above the :ceiling you may approve. Somebody with a higher ceiling has to approve it.', [
                                    'amount' => Number::currency($purchaseOrder->total_amount, config('app.currency'), config('app.locale')),
                                    'ceiling' => Number::currency($ceiling / 100, config('app.currency'), config('app.locale')),
                                ]) }}
                            </p>
                        @endif
                        @if($mayApprove)
                            <x-ui.button
                                variant="danger"
                                class="w-full justify-center"
                                wire:click="openRejectModal"
                                icon="x">
                                {{ __('Reject') }}
                            </x-ui.button>
                        @endif
                        @can('purchase-orders.edit', $purchaseOrder)
                            <x-ui.button
                                variant="secondary"
                                class="w-full justify-center"
                                wire:click="openCancelModal"
                                icon="ban">
                                {{ __('Cancel PO') }}
                            </x-ui.button>
                        @endcan

                    @elseif($purchaseOrder->isApproved())
                        <!-- Delivery -->
                        <div class="rounded-lg border p-3 text-center
                            {{ match($purchaseOrder->receiptStatus()) {
                                'received' => 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20',
                                'partial' => 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20',
                                default => 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40',
                            } }}">
                            <p class="text-xs font-semibold uppercase tracking-wider
                                {{ match($purchaseOrder->receiptStatus()) {
                                    'received' => 'text-green-700 dark:text-green-400',
                                    'partial' => 'text-amber-700 dark:text-amber-400',
                                    default => 'text-slate-500 dark:text-slate-400',
                                } }}">
                                {{ $purchaseOrder->receiptStatusLabel() }}
                            </p>
                            @if($purchaseOrder->receipts->isNotEmpty())
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Last delivery :date by :name', [
                                        'date' => $purchaseOrder->receipts->first()->received_at?->format('M d, Y'),
                                        'name' => $purchaseOrder->receipts->first()->receivedBy?->name ?? __('Unknown'),
                                    ]) }}
                                </p>
                            @endif
                        </div>

                        @if($purchaseOrder->canBeReceived())
                            @can('purchase-orders.receive', $purchaseOrder)
                                <x-ui.button
                                    variant="primary"
                                    class="w-full justify-center"
                                    wire:click="openReceiptModal"
                                    icon="check">
                                    {{ __('Record Receipt') }}
                                </x-ui.button>
                            @endcan
                        @endif

                        <!-- Approved - Show linked expense info -->
                        @if($purchaseOrder->canChangeStatusFromApproved() && $mayApprove)
                            <x-ui.button
                                variant="warning"
                                class="w-full justify-center"
                                wire:click="openRejectModal"
                                icon="x">
                                {{ __('Reject PO') }}
                            </x-ui.button>
                            <x-ui.button
                                variant="danger"
                                class="w-full justify-center"
                                wire:click="openCancelModal"
                                icon="ban">
                                {{ __('Cancel PO') }}
                            </x-ui.button>
                            <p class="text-xs text-slate-500 dark:text-slate-400 text-center">
                                {{ __('Warning: These actions will delete the linked expense.') }}
                            </p>
                        @else
                            <div class="text-center text-sm text-slate-500 dark:text-slate-400">
                                <svg class="w-8 h-8 mx-auto mb-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                {{ __('Status cannot be changed because the linked expense has payments.') }}
                            </div>
                        @endif

                    @elseif($purchaseOrder->isRejected())
                        <!-- Rejected Actions -->
                        @can('purchase-orders.edit', $purchaseOrder)
                            <x-ui.button
                                variant="primary"
                                class="w-full justify-center"
                                wire:click="reviseAndResubmit"
                                wire:confirm="{{ __('Resubmit this purchase order for approval? This will increment the revision number.') }}"
                                icon="refresh">
                                {{ __('Revise & Resubmit') }}
                            </x-ui.button>
                            <p class="text-xs text-slate-500 dark:text-slate-400 text-center">
                                {{ __('You can edit the PO before resubmitting.') }}
                            </p>
                            <x-ui.button
                                variant="secondary"
                                href="{{ route('purchase-orders.edit', $purchaseOrder->id) }}"
                                class="w-full justify-center"
                                icon="edit">
                                {{ __('Edit PO') }}
                            </x-ui.button>
                        @endcan

                    @elseif($purchaseOrder->isCancelled())
                        <!-- Cancelled - No actions -->
                        <div class="text-center text-sm text-slate-500 dark:text-slate-400">
                            {{ __('This purchase order has been cancelled.') }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Linked Expense Card (if approved) -->
            @if($purchaseOrder->isApproved() && $purchaseOrder->expense)
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg shadow-sm border border-green-200 dark:border-green-800">
                    <div class="px-6 py-4">
                        <h3 class="text-lg font-semibold text-green-800 dark:text-green-300">{{ __('Linked Expense') }}</h3>
                        <p class="mt-2 text-sm text-green-700 dark:text-green-400">
                            {{ __('This PO was approved and an expense was automatically created.') }}
                        </p>
                        <div class="mt-4">
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-green-700 dark:text-green-400">{{ __('Expense ID:') }}</dt>
                                    <dd class="text-sm font-medium text-green-800 dark:text-green-300">#{{ $purchaseOrder->expense->id }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-green-700 dark:text-green-400">{{ __('Status:') }}</dt>
                                    <dd class="text-sm font-medium text-green-800 dark:text-green-300">{{ $purchaseOrder->expense->getStatusLabel() }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm text-green-700 dark:text-green-400">{{ __('Amount:') }}</dt>
                                    <dd class="text-sm font-medium text-green-800 dark:text-green-300">{{ Number::currency($purchaseOrder->expense->total_amount, config('app.currency'), config('app.locale')) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Status History Card -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Status History') }}</h3>
                </div>
                <div class="p-6">
                    @if($purchaseOrder->statusHistories->count() > 0)
                        <div class="flow-root">
                            <ul class="-mb-8">
                                @foreach($purchaseOrder->statusHistories as $index => $history)
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
                                                            @case('pending') bg-yellow-400 @break
                                                            @case('approved') bg-green-500 @break
                                                            @case('rejected') bg-red-500 @break
                                                            @case('cancelled') bg-slate-400 @break
                                                        @endswitch
                                                    ">
                                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            @switch($history->new_status)
                                                                @case('approved')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                    @break
                                                                @case('rejected')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                    @break
                                                                @case('cancelled')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
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
                                                            {{ __('by') }} {{ $history->changedBy?->name ?? __('System') }}
                                                        </p>
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $history->created_at->format('M d, Y H:i') }}
                                                    </div>
                                                    @if($history->reason)
                                                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900 rounded p-2">
                                                            {{ $history->reason }}
                                                        </div>
                                                    @endif
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

    <!-- Reject Modal -->
    @if($showRejectModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeRejectModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Reject Purchase Order') }}</h2>
                        <button type="button" wire:click="closeRejectModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Reason for Rejection (Optional)') }}</label>
                            <textarea
                                wire:model="rejectReason"
                                rows="3"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Enter the reason for rejecting this PO...') }}"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeRejectModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="danger" wire:click="reject">
                            {{ __('Reject PO') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Cancel Modal -->
    @if($showCancelModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeCancelModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Cancel Purchase Order') }}</h2>
                        <button type="button" wire:click="closeCancelModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    @if($purchaseOrder->isApproved())
                        <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                            <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                <strong>{{ __('Warning:') }}</strong> {{ __('Cancelling this approved PO will also delete the linked expense (:id).', ['id' => '#' . $purchaseOrder->expense?->id]) }}
                            </p>
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Reason for Cancellation (Optional)') }}</label>
                            <textarea
                                wire:model="cancelReason"
                                rows="3"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="{{ __('Enter the reason for cancelling this PO...') }}"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeCancelModal">
                            {{ __('Keep PO') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="danger" wire:click="cancel">
                            {{ __('Cancel PO') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Deliveries so far. A part-delivery is the normal case on site, so the
         history is what makes "25 of 40 arrived" defensible later. --}}
    @if($purchaseOrder->receipts->isNotEmpty())
        <div class="mt-6 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ __('Delivery history') }}
            </h3>
            <ul class="space-y-4">
                @foreach($purchaseOrder->receipts as $receipt)
                    <li class="border-l-2 border-slate-200 pl-4 dark:border-slate-700">
                        <p class="text-sm text-slate-900 dark:text-white">
                            <span class="font-medium">{{ $receipt->received_at?->format('M d, Y') }}</span>
                            <span class="text-slate-500 dark:text-slate-400">
                                &middot; {{ $receipt->receivedBy?->name ?? __('Unknown') }}
                            </span>
                        </p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach($receipt->lines as $line)
                                <li class="text-xs text-slate-600 dark:text-slate-300">
                                    {{ rtrim(rtrim(number_format((float) $line->quantity, 2, '.', ''), '0'), '.') }}
                                    {{ $line->item?->unit }} &middot; {{ $line->item?->item_name }}
                                </li>
                            @endforeach
                        </ul>
                        @if($receipt->note)
                            <p class="mt-1 text-xs italic text-slate-500 dark:text-slate-400">{{ $receipt->note }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Record a delivery --}}
    @if($showReceiptModal)
        <x-ui.modal name="po-receipt-modal" maxWidth="2xl" :show="true">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Record Receipt') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Enter what actually arrived. Leave a line blank, or at zero, if none of it came.') }}
                </p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Delivery date') }}</label>
                        <input type="date" wire:model="receiptDate"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                        @error('receiptDate') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Note') }} <span class="text-slate-400">({{ __('optional') }})</span></label>
                        <input type="text" wire:model="receiptNote" placeholder="{{ __('e.g. rest due Friday') }}"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500 dark:text-slate-400">{{ __('Item') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-slate-500 dark:text-slate-400">{{ __('Outstanding') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-slate-500 dark:text-slate-400">{{ __('Arriving now') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($purchaseOrder->items as $item)
                                <tr class="{{ $item->isFullyReceived() ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">{{ $item->item_name }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-slate-600 dark:text-slate-300">
                                        {{ rtrim(rtrim(number_format($item->outstandingQuantity(), 2, '.', ''), '0'), '.') }} {{ $item->unit }}
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        @if($item->isFullyReceived())
                                            <span class="text-xs text-slate-400">{{ __('Complete') }}</span>
                                        @else
                                            <input type="number" step="0.01" min="0"
                                                max="{{ $item->outstandingQuantity() }}"
                                                wire:model="receiptQuantities.{{ $item->id }}"
                                                class="w-28 px-2 py-1 text-right text-sm border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('receiptQuantities') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <x-ui.button type="button" variant="secondary" wire:click="closeReceiptModal">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="button" variant="primary" wire:click="recordReceipt" icon="check">
                        {{ __('Record Receipt') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif
</div>
