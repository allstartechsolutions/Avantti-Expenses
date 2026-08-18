<x-jobsite-layout :jobSite="$jobSite" active="income" title="{{ __('Income') }}">
    <div class="space-y-6">
        <!-- Header with Search and Add Button -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1 flex flex-col sm:flex-row gap-4">
                <div class="relative max-w-md">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="incomeSearch"
                        placeholder="{{ __('Search income...') }}"
                        class="w-full pl-10 pr-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            <x-ui.button
                variant="primary"
                icon="plus"
                wire:click="openAddModal">
                {{ __('Add Income') }}
            </x-ui.button>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/80">{{ __('Total Received') }}</p>
                        <p class="text-2xl font-bold mt-1">{{ Number::currency($totalIncomeAmount, config('app.currency'), config('app.locale')) }}</p>
                    </div>
                    <div class="bg-white/10 rounded-full p-3">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-2 text-sm text-white/80">{{ $entries->count() }} {{ Str::plural('record', $entries->count()) }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('This Month') }}</p>
                        <p class="text-2xl font-bold mt-1 text-green-600 dark:text-green-400">{{ Number::currency($thisMonthAmount, config('app.currency'), config('app.locale')) }}</p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('To Receive') }}</p>
                        <p class="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">{{ Number::currency($expectedAmount, config('app.currency'), config('app.locale')) }}</p>
                        @if($overdueAmount > 0)
                            <p class="text-xs text-red-600 dark:text-red-400 mt-1">
                                {{ __('Overdue') }}: {{ Number::currency($overdueAmount, config('app.currency'), config('app.locale')) }}
                            </p>
                        @endif
                    </div>
                    <div class="bg-amber-100 dark:bg-amber-900/20 rounded-full p-3">
                        <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Income List -->
        @if($entries->count() > 0)
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Title') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Source') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($entries as $entry)
                                @php $income = $entry['income']; @endphp
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
                                        {{ $income->effectiveDate()?->format('M d, Y') }}
                                        @if($income->isExpected())
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Due') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $income->title }}</div>
                                                @if($income->description)
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ Str::limit($income->description, 60) }}</span>
                                                @endif
                                            </div>
                                            @if(! $entry['is_share'] && $income->attachments_count > 0)
                                                <span class="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400" title="{{ __('Attachments') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                    </svg>
                                                    {{ $income->attachments_count }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($entry['is_share'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300" title="{{ __('Share of a project-level income') }}">
                                                {{ __('Project share') }}
                                            </span>
                                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                {{ __('of :total', ['total' => Number::currency($income->amount, config('app.currency'), config('app.locale'))]) }}
                                            </div>
                                        @else
                                            <span class="text-sm text-slate-900 dark:text-white">{{ $jobSite->job_site_name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold {{ $income->isReceived() ? 'text-green-600 dark:text-green-400' : 'text-slate-700 dark:text-slate-300' }}">
                                        {{ Number::currency($entry['amount'], config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        @php
                                            $incomeBadge = [
                                                'green' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                                'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400',
                                                'red' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                                            ];
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $incomeBadge[$income->getStatusColor()] }}">
                                            {{ $income->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            @if($entry['is_share'])
                                                {{-- The project page owns this money: viewable here, editable there. --}}
                                                <button
                                                    wire:click="openShareModal({{ $income->id }})"
                                                    class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                    title="{{ __('View') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </button>
                                                <a
                                                    href="{{ route('projects.income', $jobSite->project) }}"
                                                    class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                    title="{{ __('Manage on the project') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                    </svg>
                                                </a>
                                            @else
                                                <button
                                                    wire:click="openViewModal({{ $income->id }})"
                                                    class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                    title="{{ __('View') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </button>
                                                @if($income->isExpected())
                                                    <button
                                                        wire:click="markReceived({{ $income->id }})"
                                                        wire:confirm="{{ __('Mark this income as received today?') }}"
                                                        class="text-green-600 dark:text-green-400 hover:text-green-700"
                                                        title="{{ __('Mark as received') }}">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </button>
                                                @endif
                                                <button
                                                    wire:click="openEditModal({{ $income->id }})"
                                                    class="text-slate-600 dark:text-slate-400 hover:text-[#3F5189] dark:hover:text-[#4A5A96]"
                                                    title="{{ __('Edit') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </button>
                                                @admin
                                                <button
                                                    wire:click="deleteIncome({{ $income->id }})"
                                                    wire:confirm="{{ __('Are you sure you want to delete this income record?') }}"
                                                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                                    title="{{ __('Delete') }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                                @endadmin
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No income recorded') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Get started by adding an income record.') }}</p>
                    <div class="mt-6">
                        <x-ui.button
                            variant="primary"
                            icon="plus"
                            wire:click="openAddModal">
                            {{ __('Add Income') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Income Form Modal -->
    <!-- Income form — full-page surface, same shape as the project level -->
    <x-ui.modal name="income-form-modal" maxWidth="full">
        <form wire:submit="saveIncome" class="flex min-h-screen flex-col">
            <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-5xl px-6 py-4 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                            {{ $editingIncomeId ? __('Edit Income') : __('Add Income') }}
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                            {{ $jobSite->job_site_name }} &middot; {{ $jobSite->project->project_name }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeFormModal"
                        class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        title="{{ __('Close') }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 mx-auto w-full max-w-5xl px-6 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5 space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Income Details') }}</h3>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Amount') }} <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="0" wire:model="income_amount" placeholder="0.00" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-lg font-semibold">
                            @error('income_amount') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Status') }} <span class="text-red-500">*</span></label>
                                <select wire:model.live="income_status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    <option value="received">{{ __('Received') }}</option>
                                    <option value="expected">{{ __('Expected') }}</option>
                                </select>
                                @error('income_status') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                    {{ $income_status === 'expected' ? __('Reference Date') : __('Date') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="date" wire:model="income_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                @error('income_date') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                            @if($income_status === 'expected')
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Due Date') }} <span class="text-red-500">*</span></label>
                                    <input type="date" wire:model="income_due_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                    @error('income_due_date') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                wire:model="income_title"
                                placeholder="{{ __('e.g. Client deposit, Draw payment') }}"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
                            @error('income_title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Description') }}</label>
                            <textarea wire:model="income_description" rows="4" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"></textarea>
                            @error('income_description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Location') }}</h3>
                            <p class="text-sm text-slate-900 dark:text-white">{{ $jobSite->job_site_name }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ __('Money added here belongs to this location. To share one payment between locations, add it at the project level and split it there.') }}
                            </p>
                            <a href="{{ route('projects.income', $jobSite->project) }}" class="mt-2 inline-block text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                {{ __('Go to project income') }}
                            </a>
                        </div>

                        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">{{ __('Attachments') }}</label>
                            <input
                                type="file"
                                wire:model="income_uploads"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="block w-full text-sm text-slate-500 dark:text-slate-400
                                    file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                                    file:text-sm file:font-medium file:bg-[#3F5189] file:text-white
                                    hover:file:bg-[#4A5A96] file:cursor-pointer">
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('PDF, JPG or PNG, up to 10MB each.') }}</p>
                            <div wire:loading wire:target="income_uploads" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Uploading...') }}</div>
                            @if(count($income_uploads) > 0)
                                <ul class="mt-2 space-y-1">
                                    @foreach($income_uploads as $upload)
                                        <li class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                            <svg class="w-4 h-4 flex-shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                            <span class="truncate">{{ $upload->getClientOriginalName() }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @error('income_uploads.*') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            @if($editingIncomeId)
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('New files are added to the existing attachments. Manage them from the income record view.') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <div class="mx-auto max-w-5xl px-6 py-4 flex items-center justify-end gap-4">
                    <x-ui.button type="button" variant="secondary" wire:click="closeFormModal">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save" wire:loading.attr="disabled" wire:target="income_uploads">{{ $editingIncomeId ? __('Update') : __('Save') }}</x-ui.button>
                </div>
            </div>
        </form>
    </x-ui.modal>

    <!-- Income View Modal -->
    <!-- Income detail — every field the record holds; read only for a project share -->
    <x-ui.modal name="income-view-modal" maxWidth="full">
        @if($viewingIncome)
            @php
                $viewBadge = [
                    'green' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                    'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400',
                    'red' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                ];
                $factLabel = 'text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400';
                $factValue = 'mt-1 text-sm text-slate-900 dark:text-white';
                $shownAmount = $viewingShare ? (float) $viewingShareAmount : (float) $viewingIncome->amount;
            @endphp
            <div class="flex min-h-screen flex-col">
                <!-- Header -->
                <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                    <div class="mx-auto max-w-7xl px-6 py-4 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h2 class="text-xl font-semibold text-slate-900 dark:text-white truncate">{{ $viewingIncome->title }}</h2>
                                <span class="shrink-0 inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $viewBadge[$viewingIncome->getStatusColor()] }}">
                                    {{ $viewingIncome->getStatusLabel() }}
                                </span>
                                @if($viewingShare)
                                    <span class="shrink-0 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
                                        {{ __('Project share') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 truncate">
                                {{ __('Income Details') }}
                                &middot;
                                {{ $jobSite->job_site_name }}
                                &middot;
                                {{ __('Record #:id', ['id' => $viewingIncome->id]) }}
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
                    @if($viewingShare)
                        <div class="mb-6 rounded-lg bg-blue-50 dark:bg-blue-900/20 px-4 py-3 text-sm text-blue-800 dark:text-blue-300">
                            {{ __('This is this location\'s share of a project-level income. It is managed on the project income page.') }}
                            <a href="{{ route('projects.income', $jobSite->project) }}" class="font-medium underline">{{ __('Go to project income') }}</a>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left: the money and the facts -->
                        <div class="space-y-6">
                            <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
                                <p class="text-sm font-medium text-white/80">
                                    {{ $viewingShare ? __('This location\'s share') : ($viewingIncome->isReceived() ? __('Amount Received') : __('Amount Expected')) }}
                                </p>
                                <p class="text-3xl font-bold mt-1">{{ Number::currency($shownAmount, config('app.currency'), config('app.locale')) }}</p>
                                <p class="mt-2 text-sm text-white/80">
                                    {{ $viewingIncome->isExpected() ? __('Due') : __('Received') }}
                                    {{ $viewingIncome->effectiveDate()?->format('M d, Y') }}
                                    @if($viewingIncome->isOverdue())
                                        &middot; {{ __('Overdue by :days days', ['days' => $viewingIncome->due_date->diffInDays(today())]) }}
                                    @endif
                                </p>
                                @if($viewingShare)
                                    <p class="mt-2 text-sm text-white/80">
                                        {{ __('of :total', ['total' => Number::currency($viewingIncome->amount, config('app.currency'), config('app.locale'))]) }}
                                    </p>
                                @endif
                            </div>

                            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-4">{{ __('Record') }}</h3>
                                <dl class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ __('Status') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->getStatusLabel() }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ $viewingIncome->isExpected() ? __('Reference Date') : __('Date') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->income_date->format('M d, Y') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ __('Due Date') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->due_date?->format('M d, Y') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ __('Project') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->project?->project_name ?? '—' }}</dd>
                                    </div>
                                    <div class="col-span-2">
                                        <dt class="{{ $factLabel }}">{{ __('Source') }}</dt>
                                        <dd class="{{ $factValue }}">
                                            {{ $viewingShare ? __('Share of a project-level income') : $jobSite->job_site_name }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ __('Added By') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->createdBy?->name ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ __('Attachments') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->attachments->count() }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ __('Created') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->created_at?->format('M d, Y H:i') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="{{ $factLabel }}">{{ __('Last Updated') }}</dt>
                                        <dd class="{{ $factValue }}">{{ $viewingIncome->updated_at?->format('M d, Y H:i') ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Right: description, the full split when it is a share, attachments -->
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Description') }}</h3>
                                @if($viewingIncome->description)
                                    <p class="text-sm text-slate-900 dark:text-white whitespace-pre-line">{{ $viewingIncome->description }}</p>
                                @else
                                    <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('No description was added to this record.') }}</p>
                                @endif
                            </div>

                            @if($viewingShare)
                                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Full distribution') }}</h3>
                                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                            <thead class="bg-slate-50 dark:bg-slate-900/50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Location') }}</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Amount') }}</th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('% of income') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                                @foreach($viewingIncome->distributions as $distribution)
                                                    <tr class="{{ $distribution->job_site_id === $jobSite->id ? 'bg-[#3F5189]/5 dark:bg-[#4A5A96]/10' : '' }}">
                                                        <td class="px-4 py-2 text-sm text-slate-900 dark:text-white">
                                                            {{ $distribution->jobSite?->job_site_name ?? '—' }}
                                                            @if($distribution->job_site_id === $jobSite->id)
                                                                <span class="ml-1 text-xs text-slate-500 dark:text-slate-400">({{ __('this location') }})</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-4 py-2 text-sm text-right font-medium text-slate-900 dark:text-white">
                                                            {{ Number::currency($distribution->amount, config('app.currency'), config('app.locale')) }}
                                                        </td>
                                                        <td class="px-4 py-2 text-sm text-right text-slate-600 dark:text-slate-300">
                                                            {{ $viewingIncome->amount > 0 ? number_format($distribution->amount / $viewingIncome->amount * 100, 2) : '0.00' }}%
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot class="bg-slate-50 dark:bg-slate-900/50">
                                                <tr>
                                                    <td class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400">{{ __('Undistributed (project level)') }}</td>
                                                    <td class="px-4 py-2 text-sm text-right font-semibold text-slate-900 dark:text-white">
                                                        {{ Number::currency($viewingIncome->undistributedAmount(), config('app.currency'), config('app.locale')) }}
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-right text-slate-600 dark:text-slate-300">
                                                        {{ $viewingIncome->amount > 0 ? number_format($viewingIncome->undistributedAmount() / $viewingIncome->amount * 100, 2) : '0.00' }}%
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
                                @if($viewingShare)
                                    <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">{{ __('Attachments') }}</h3>
                                    @forelse($viewingIncome->attachments as $attachment)
                                        <p class="text-sm text-slate-900 dark:text-white truncate">{{ $attachment->original_name }}</p>
                                    @empty
                                        <p class="text-sm text-slate-500 dark:text-slate-400 italic">{{ __('No attachments on this record.') }}</p>
                                    @endforelse
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Files are managed on the project income page.') }}</p>
                                @else
                                    <livewire:shared.attachments
                                        model-type="income"
                                        :model-id="$viewingIncome->id"
                                        :key="'income-attachments-'.$viewingIncome->id" />
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                    <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Added By') }}: {{ $viewingIncome->createdBy?->name ?? '—' }}
                            &middot;
                            {{ $viewingIncome->created_at?->format('M d, Y H:i') }}
                        </p>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            @unless($viewingShare)
                                @if($viewingIncome->isExpected())
                                    <x-ui.button
                                        type="button"
                                        variant="success"
                                        wire:click="markReceived({{ $viewingIncome->id }})"
                                        wire:confirm="{{ __('Mark this income as received today?') }}">
                                        {{ __('Mark as received') }}
                                    </x-ui.button>
                                @endif
                                @admin
                                    <x-ui.button
                                        type="button"
                                        variant="danger"
                                        icon="trash"
                                        wire:click="deleteIncome({{ $viewingIncome->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this income record?') }}">
                                        {{ __('Delete') }}
                                    </x-ui.button>
                                @endadmin
                                <x-ui.button type="button" variant="secondary" icon="edit" wire:click="openEditModal({{ $viewingIncome->id }})">{{ __('Edit') }}</x-ui.button>
                            @endunless
                            <x-ui.button type="button" variant="primary" wire:click="closeViewModal">{{ __('Close') }}</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </x-ui.modal>

</x-jobsite-layout>
