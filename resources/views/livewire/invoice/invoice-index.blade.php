<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Invoices</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage your invoices and payments</p>
            </div>
            <div>
                <x-ui.button
                    variant="primary"
                    href="{{ route('invoices.create') }}"
                    icon="plus">
                    New Invoice
                </x-ui.button>
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

    <!-- Status Filter Tabs -->
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <button wire:click="setStatusFilter('')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === '' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    All <span class="ml-1 text-xs bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-0.5 rounded-full">{{ $statusCounts['all'] }}</span>
                </button>
                <button wire:click="setStatusFilter('draft')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'draft' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Draft <span class="ml-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full">{{ $statusCounts['draft'] }}</span>
                </button>
                <button wire:click="setStatusFilter('sent')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'sent' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Sent <span class="ml-1 text-xs bg-blue-100 dark:bg-blue-700 text-blue-600 dark:text-blue-300 px-2 py-0.5 rounded-full">{{ $statusCounts['sent'] }}</span>
                </button>
                <button wire:click="setStatusFilter('pending')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'pending' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Pending <span class="ml-1 text-xs bg-yellow-100 dark:bg-yellow-700 text-yellow-600 dark:text-yellow-300 px-2 py-0.5 rounded-full">{{ $statusCounts['pending'] }}</span>
                </button>
                <button wire:click="setStatusFilter('partial')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'partial' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Partial <span class="ml-1 text-xs bg-orange-100 dark:bg-orange-700 text-orange-600 dark:text-orange-300 px-2 py-0.5 rounded-full">{{ $statusCounts['partial'] }}</span>
                </button>
                <button wire:click="setStatusFilter('paid')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'paid' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Paid <span class="ml-1 text-xs bg-green-100 dark:bg-green-700 text-green-600 dark:text-green-300 px-2 py-0.5 rounded-full">{{ $statusCounts['paid'] }}</span>
                </button>
                <button wire:click="setStatusFilter('past_due')"
                    class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm {{ $statusFilter === 'past_due' ? 'border-[#3F5189] text-[#3F5189]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    Past Due <span class="ml-1 text-xs bg-red-100 dark:bg-red-700 text-red-600 dark:text-red-300 px-2 py-0.5 rounded-full">{{ $statusCounts['past_due'] }}</span>
                </button>
            </nav>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex-1 max-w-md">
                    <label for="search" class="sr-only">Search invoices</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input
                            type="text"
                            id="search"
                            wire:model.live.debounce.300ms="search"
                            class="block w-full pl-10 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md leading-5 bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189]"
                            placeholder="Search by invoice number or client name..."
                        >
                    </div>
                </div>

                @if($search)
                    <x-ui.button
                        variant="secondary"
                        wire:click="$set('search', '')"
                        icon="x">
                        Clear Search
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        @if($invoices->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-900">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Invoice #
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Client
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Project
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Due Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Total
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($invoices as $invoice)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        {{ $invoice->invoice_number }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $invoice->client->company_name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $invoice->project?->name ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $invoice->invoice_date->format('m/d/Y') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900 dark:text-white">
                                        {{ $invoice->due_date->format('m/d/Y') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $invoice->status_color }}">
                                        {{ $invoice->status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">
                                        ${{ number_format($invoice->total_amount, 2) }}
                                    </div>
                                    @if($invoice->isPartial())
                                        <div class="text-xs text-orange-600 dark:text-orange-400">
                                            Due: ${{ number_format($invoice->getBalanceDue(), 2) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <x-ui.view-edit-buttons
                                            :viewRoute="route('invoices.show', $invoice->id)"
                                            :editRoute="$invoice->canBeEdited() ? route('invoices.edit', $invoice->id) : null" />
                                        @if($invoice->canBeEdited())
                                            <x-ui.button
                                                variant="danger"
                                                size="sm"
                                                wire:click="deleteInvoice({{ $invoice->id }})"
                                                wire:confirm="Are you sure you want to delete this invoice?"
                                                icon="trash">
                                                Delete
                                            </x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $invoices->links() }}
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">
                    @if($search || $statusFilter)
                        No invoices found
                    @else
                        No invoices yet
                    @endif
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    @if($search || $statusFilter)
                        Try adjusting your search or filter.
                    @else
                        Get started by creating a new invoice.
                    @endif
                </p>
                @if(!$search && !$statusFilter)
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            href="{{ route('invoices.create') }}"
                            icon="plus">
                            New Invoice
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
