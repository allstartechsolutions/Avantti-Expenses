<div>
            @php
                $expiryChip = [
                    'expired' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                    'expiring_soon' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                    'valid' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                    'undated' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                    'missing' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                ];
                $expiryDot = [
                    'expired' => 'bg-red-500',
                    'expiring_soon' => 'bg-amber-500',
                    'valid' => 'bg-green-500',
                    'undated' => 'bg-slate-400',
                    'missing' => 'bg-slate-400',
                ];
                $expiryText = fn (string $state) => $state === 'missing'
                    ? __('Missing')
                    : \App\Models\SubcontractorDocument::expiryStatusLabel($state);
                $stageText = fn ($stage) => $stage === 'expired' ? __('the day after') : __(':days days before', ['days' => $stage]);
                $canRenew = auth()->user()->can('vendors.renew_documents');
                $canArchive = auth()->user()->can('vendors.archive_documents');
                $canDelete = auth()->user()->can('vendors.delete');
            @endphp

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Subcontractor Details') }}</h1>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $subcontractor->company_name }}</p>
                    <button type="button" wire:click="setActiveTab('documents')" class="inline-flex" title="{{ __('Open the documents tab') }}">
                        <x-vendor.document-health
                            :state="$documentHealth"
                            :expired="$documentCounts['expired']"
                            :expiring="$documentCounts['expiring']"
                            mode="full" />
                    </button>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('subcontractors.index') }}"
                    icon="arrow-left">
                    {{ __('Back to Subcontractors') }}
                </x-ui.button>
                <x-ui.button
                    variant="primary"
                    href="{{ route('subcontractors.edit', $subcontractor->id) }}"
                    icon="edit">
                    {{ __('Edit Subcontractor') }}
                </x-ui.button>
                @if(auth()->user()->can('vendors.delete'))
                    @if($linkedContracts + $linkedPaymentBatches > 0)
                        <span title="Cannot delete: linked to {{ $linkedContracts }} contract(s) and {{ $linkedPaymentBatches }} payment batch(es)">
                            <x-ui.button variant="danger" icon="trash" disabled>
                                {{ __('Delete') }}
                            </x-ui.button>
                        </span>
                    @else
                        <x-ui.button
                            variant="danger"
                            wire:click="confirmDeleteSubcontractor"
                            icon="trash">
                            {{ __('Delete') }}
                        </x-ui.button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    <!-- Error Message -->
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-8">
                <button
                    wire:click="setActiveTab('overview')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'overview' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    {{ __('Overview') }}
                </button>
                <button
                    wire:click="setActiveTab('documents')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'documents' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('Documents') }}
                    @if($documents->count() > 0)
                        <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ $documents->count() }}
                        </span>
                    @endif
                </button>
                <button
                    wire:click="setActiveTab('employees')"
                    class="group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'employees' ? 'border-[#3F5189] text-[#3F5189] dark:border-[#4A5A96] dark:text-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    {{ __('Employees') }}
                    @if($employees->count() > 0)
                        <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ $employees->count() }}
                        </span>
                    @endif
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    <div>
        <!-- Overview Tab -->
        @if($activeTab === 'overview')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Company Profile Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Company Information') }}</h3>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center space-x-6 mb-6">
                                <div class="flex-shrink-0">
                                    <div class="h-20 w-20 rounded-full bg-gradient-to-r from-[#3F5189] to-[#4A5A96] flex items-center justify-center text-white text-2xl font-medium">
                                        {{ $subcontractor->initials }}
                                    </div>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $subcontractor->company_name }}</h2>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @if($subcontractor->is_supplier)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ __('Supplier') }}</span>
                                        @endif
                                        @if($subcontractor->is_subcontractor)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">{{ __('Subcontractor') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-slate-500 dark:text-slate-400">{{ $subcontractor->contact_email }}</p>
                                    @if($subcontractor->website)
                                        <a href="{{ $subcontractor->website }}" target="_blank" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            {{ $subcontractor->website }}
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Company Name -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Company Name') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->company_name }}</p>
                                </div>

                                <!-- Website -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Website') }}
                                    </label>
                                    @if($subcontractor->website)
                                        <a href="{{ $subcontractor->website }}" target="_blank" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            {{ $subcontractor->website }}
                                        </a>
                                    @else
                                        <p class="text-slate-900 dark:text-white">{{ __('Not provided') }}</p>
                                    @endif
                                </div>

                                <!-- Created At -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Added On') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->created_at->appDateLong() }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Person Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Contact Person') }}</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Contact Name -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Full Name') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->contact_name }}</p>
                                </div>

                                <!-- Title -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Title/Position') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->title ?? __('Not provided') }}</p>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Email Address') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->contact_email }}</p>
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Phone Number') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->formatted_phone ?? __('Not provided') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information Card -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Address Information') }}</h3>
                        </div>
                        <div class="p-6">
                            @if($subcontractor->full_address)
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Full Address') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->full_address }}</p>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Street -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Street Address') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->street ?? __('Not provided') }}</p>
                                </div>

                                <!-- Address Line 2 -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Address Line 2') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->address_2 ?? __('Not provided') }}</p>
                                </div>

                                @if($subcontractor->country === 'BR')
                                <!-- Neighborhood (Brazil only) -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('Neighborhood (Bairro)') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->neighborhood ?? __('Not provided') }}</p>
                                </div>
                                @endif

                                <!-- City -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('City') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->city ?? __('Not provided') }}</p>
                                </div>

                                <!-- State -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ __('State') }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->state ?? __('Not provided') }}</p>
                                </div>

                                <!-- Postal Code -->
                                <div>
                                    <label class="block text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">
                                        {{ $subcontractor->country === 'BR' ? 'CEP' : 'Postal Code' }}
                                    </label>
                                    <p class="text-slate-900 dark:text-white">{{ $subcontractor->postal_code ?? __('Not provided') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Actions -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Quick Actions') }}</h3>
                        </div>
                        <div class="p-6 space-y-3">
                            <x-ui.button
                                variant="secondary"
                                class="w-full justify-center"
                                href="{{ route('subcontractors.edit', $subcontractor->id) }}"
                                icon="edit">
                                {{ __('Edit Subcontractor') }}
                            </x-ui.button>

                            @if($subcontractor->contact_email)
                                <a href="mailto:{{ $subcontractor->contact_email }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ __('Send Email') }}
                                </a>
                            @endif

                            @if($subcontractor->phone)
                                <a href="tel:{{ $subcontractor->phone }}" class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 bg-slate-100 text-slate-700 hover:bg-slate-200 focus:ring-slate-500/50 border border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-600">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    {{ __('Call Subcontractor') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Subcontractor Stats -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Information') }}</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Subcontractor ID') }}</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">#{{ $subcontractor->id }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Documents') }}</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $documents->count() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Employees') }}</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $employees->count() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Added') }}</span>
                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $subcontractor->created_at->diffForHumans() }}</span>
                            </div>
                            @if($subcontractor->created_at != $subcontractor->updated_at)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Last Updated') }}</span>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $subcontractor->updated_at->diffForHumans() }}</span>
                                </div>
                            @endif
                            @if($subcontractor->createdBy)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Added By') }}</span>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $subcontractor->createdBy->name }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Documents Tab -->
        @if($activeTab === 'documents')

            <div class="space-y-6">
                <!-- Required documents: every type that asks for a date, and where this vendor stands -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Required documents') }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Every type that requires an expiration date, and where this subcontractor stands on each. Only active documents count toward the badge and the reminders.') }}</p>
                    </div>
                    <div class="p-6">
                        @if($requiredTypes->isEmpty())
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No document type requires an expiration date, so there is nothing to track here.') }}</p>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($requiredTypes as $required)
                                    <div class="flex items-start justify-between gap-3 rounded-lg border {{ $required['status'] === 'missing' ? 'border-dashed' : '' }} border-slate-200 dark:border-slate-700 px-4 py-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block w-2 h-2 rounded-full shrink-0 {{ $expiryDot[$required['status']] }}"></span>
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ __($required['type']->name) }}</p>
                                            </div>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 pl-4">
                                                @if($required['document'] && $required['document']->expiration_date)
                                                    {{ $required['document']->expiration_date->appDate() }}
                                                    @php $days = $required['document']->days_until_expiry; @endphp
                                                    &middot;
                                                    @if($days === 0)
                                                        {{ __('Expires today') }}
                                                    @elseif($days > 0)
                                                        {{ trans_choice('Expires in :count day|Expires in :count days', $days, ['count' => $days]) }}
                                                    @else
                                                        {{ trans_choice('Expired :count day ago|Expired :count days ago', abs($days), ['count' => abs($days)]) }}
                                                    @endif
                                                @elseif($required['document'])
                                                    {{ __('On file without an expiration date — renew it with one.') }}
                                                @else
                                                    {{ __('Nothing on file for this type.') }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="shrink-0 flex flex-col items-end gap-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$required['status']] }}">{{ $expiryText($required['status']) }}</span>
                                            @if($canRenew)
                                                @if($required['status'] === 'missing')
                                                    <button type="button" wire:click="startUpload({{ $required['type']->id }})" class="text-xs font-medium text-[#3F5189] hover:underline dark:text-[#8b9ad1]">{{ __('Upload') }}</button>
                                                @elseif($required['status'] !== 'valid')
                                                    <button type="button" wire:click="startRenewal({{ $required['document']->id }})" class="text-xs font-medium text-[#3F5189] hover:underline dark:text-[#8b9ad1]">{{ __('Renew') }}</button>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Documents on file -->
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Documents') }}</h3>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                <span>{{ trans_choice(':count active document|:count active documents', $documentCounts['active'], ['count' => $documentCounts['active']]) }}</span>
                                @if($documentCounts['expiring'] > 0)
                                    <span class="text-amber-700 dark:text-amber-400">{{ trans_choice(':count expiring soon|:count expiring soon', $documentCounts['expiring'], ['count' => $documentCounts['expiring']]) }}</span>
                                @endif
                                @if($documentCounts['expired'] > 0)
                                    <span class="text-red-700 dark:text-red-400">{{ trans_choice(':count expired|:count expired', $documentCounts['expired'], ['count' => $documentCounts['expired']]) }}</span>
                                @endif
                                @if($documentCounts['history'] > 0)
                                    <span>{{ trans_choice(':count in history|:count in history', $documentCounts['history'], ['count' => $documentCounts['history']]) }}</span>
                                @endif
                            </div>
                        </div>
                        @if($canRenew)
                            <x-ui.button variant="primary" wire:click="startUpload" icon="plus">
                                {{ __('Upload Document') }}
                            </x-ui.button>
                        @endif
                    </div>

                    <div class="p-6">
                        @if($documentGroups->isEmpty())
                            <!-- Empty State -->
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No documents yet') }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Upload documents like W9, insurance certificates, and licenses.') }}
                                </p>
                                @if($canRenew)
                                    <div class="mt-6">
                                        <x-ui.button variant="primary" wire:click="startUpload" icon="plus">
                                            {{ __('Upload Document') }}
                                        </x-ui.button>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="space-y-6">
                                @foreach($documentGroups as $group)
                                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                                        <!-- Type heading -->
                                        <div class="px-4 py-3 bg-slate-50 dark:bg-slate-900/50 flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="inline-block w-2 h-2 rounded-full shrink-0 {{ $group['active']->isEmpty() ? $expiryDot['missing'] : $expiryDot[$group['expiry']] }}"></span>
                                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ __($group['type']->name) }}</h4>
                                                @if(! $group['type']->is_active)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300" title="{{ __('This type was retired in System Settings: its documents are kept but no longer watched.') }}">{{ __('Retired type') }}</span>
                                                @elseif($group['type']->requires_expiration && $group['active']->isNotEmpty())
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$group['expiry']] }}">{{ $expiryText($group['expiry']) }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if($group['active']->isEmpty())
                                            <div class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center justify-between gap-2">
                                                <span>{{ __('Nothing active for this type — everything filed under it was archived.') }}</span>
                                                @if($canRenew)
                                                    <button type="button" wire:click="startUpload({{ $group['type']->id }})" class="text-xs font-medium text-[#3F5189] hover:underline dark:text-[#8b9ad1]">{{ __('Upload') }}</button>
                                                @endif
                                            </div>
                                        @else
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                                    <thead>
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Document') }}</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Expiration') }}</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Status') }}</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Uploaded') }}</th>
                                                            <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                                        @foreach($group['active'] as $document)
                                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50" wire:key="doc-{{ $document->id }}">
                                                                <td class="px-4 py-3">
                                                                    <div class="flex items-start">
                                                                        <svg class="w-8 h-8 text-slate-400 mr-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                        </svg>
                                                                        <div class="min-w-0">
                                                                            <p class="text-sm font-medium text-slate-900 dark:text-white break-all">{{ $document->file_name }}</p>
                                                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $document->formatted_file_size }}</p>
                                                                            @if($document->notes)
                                                                                <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 whitespace-pre-line">{{ $document->notes }}</p>
                                                                            @endif
                                                                            @if($document->supersedes)
                                                                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('Replaced :file', ['file' => $document->supersedes->file_name]) }}</p>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap">
                                                                    @if($document->expiration_date)
                                                                        <div class="text-sm text-slate-900 dark:text-white">{{ $document->expiration_date->appDate() }}</div>
                                                                        @php $days = $document->days_until_expiry; @endphp
                                                                        <div class="text-xs {{ $days < 0 ? 'text-red-600 dark:text-red-400' : ($days <= \App\Models\SubcontractorDocument::EXPIRING_SOON_DAYS ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400') }}">
                                                                            @if($days === 0)
                                                                                {{ __('Expires today') }}
                                                                            @elseif($days > 0)
                                                                                {{ trans_choice('Expires in :count day|Expires in :count days', $days, ['count' => $days]) }}
                                                                            @else
                                                                                {{ trans_choice('Expired :count day ago|Expired :count days ago', abs($days), ['count' => abs($days)]) }}
                                                                            @endif
                                                                        </div>
                                                                    @else
                                                                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('No expiration') }}</span>
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap">
                                                                    @if($document->documentType->requires_expiration && $document->documentType->is_active)
                                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$document->expiry_status] }}">
                                                                            {{ $document->expiry_status_label }}
                                                                        </span>
                                                                    @else
                                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $expiryChip['valid'] }}">{{ __('Document active') }}</span>
                                                                    @endif
                                                                    @if($document->reminded_stages !== [])
                                                                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('Reminders sent: :list', ['list' => collect($document->reminded_stages)->map($stageText)->join(', ')]) }}</div>
                                                                    @endif
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap">
                                                                    <div class="text-sm text-slate-900 dark:text-white">{{ $document->created_at->appDate() }}</div>
                                                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ __('by :name', ['name' => $document->uploadedBy->name ?? __('Unknown')]) }}</div>
                                                                </td>
                                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                                    <div class="flex items-center justify-end gap-1">
                                                                        <x-ui.icon-button variant="ghost" size="sm" icon="download" href="{{ $document->downloadUrl() }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}" />
                                                                        @php $versions = $chainLength($document); @endphp
                                                                        <x-ui.button variant="ghost" size="sm" icon="clock" wire:click="showHistory({{ $document->id }})" title="{{ trans_choice('History: this document and :count earlier version|History: this document and :count earlier versions', $versions - 1, ['count' => $versions - 1]) }}" aria-label="{{ __('History') }}">
                                                                            ({{ $versions }})
                                                                        </x-ui.button>
                                                                        @if($canRenew)
                                                                            <x-ui.button variant="outline" size="sm" icon="undo" wire:click="startRenewal({{ $document->id }})">{{ __('Renew') }}</x-ui.button>
                                                                        @endif
                                                                        @if($canArchive)
                                                                            <x-ui.button variant="ghost" size="sm" wire:click="startArchive({{ $document->id }})">{{ __('Archive') }}</x-ui.button>
                                                                        @endif
                                                                        @if($canDelete)
                                                                            <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="deleteDocument({{ $document->id }})" wire:confirm="{{ __('Are you sure you want to delete this document?') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" class="hover:text-red-600 dark:hover:text-red-400" />
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif

                                        <!-- Archived documents of this type: out of the watch, kept, reactivatable -->
                                        @if($group['archived']->isNotEmpty())
                                            <div class="border-t border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/30">
                                                <p class="px-4 pt-3 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Archived') }}</p>
                                                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                                                    @foreach($group['archived'] as $old)
                                                        <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3" wire:key="arch-{{ $old->id }}">
                                                            <div class="min-w-0 flex-1">
                                                                <p class="text-sm text-slate-700 dark:text-slate-300 break-all">{{ $old->file_name }} <span class="text-xs text-slate-400 dark:text-slate-500">{{ $old->formatted_file_size }}</span></p>
                                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                                    {{ __('Archived') }} {{ __('by :name', ['name' => $old->archivedBy->name ?? __('Unknown')]) }} &middot; {{ $old->archived_at?->appDateTime() ?? '—' }}
                                                                    @if($old->archive_reason)
                                                                        &middot; <span class="italic">{{ $old->archive_reason }}</span>
                                                                    @endif
                                                                </p>
                                                            </div>
                                                            <div class="flex items-center gap-1 sm:justify-end">
                                                                <x-ui.icon-button variant="ghost" size="sm" icon="download" href="{{ $old->downloadUrl() }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}" />
                                                                @php $versions = $chainLength($old); @endphp
                                                                <x-ui.button variant="ghost" size="sm" icon="clock" wire:click="showHistory({{ $old->id }})" title="{{ trans_choice('History: this document and :count earlier version|History: this document and :count earlier versions', $versions - 1, ['count' => $versions - 1]) }}" aria-label="{{ __('History') }}">
                                                                    ({{ $versions }})
                                                                </x-ui.button>
                                                                @if($canArchive)
                                                                    <x-ui.button variant="ghost" size="sm" wire:click="reactivateDocument({{ $old->id }})">{{ __('Reactivate') }}</x-ui.button>
                                                                @endif
                                                                @if($canDelete)
                                                                    <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="deleteDocument({{ $old->id }})" wire:confirm="{{ __('Are you sure you want to delete this document?') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" class="hover:text-red-600 dark:hover:text-red-400" />
                                                                @endif
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Employees Tab -->
        @if($activeTab === 'employees')
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Employees') }}</h3>
                        <x-ui.button
                            variant="primary"
                            wire:click="toggleEmployeeForm"
                            icon="{{ $showEmployeeForm ? 'x' : 'plus' }}">
                            {{ $showEmployeeForm ? 'Cancel' : 'Add Employee' }}
                        </x-ui.button>
                    </div>

                    <!-- Add Employee Form -->
                    @if($showEmployeeForm)
                        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                            <form wire:submit="saveEmployee" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Name -->
                                    <div>
                                        <label for="employee_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            {{ __('Name') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="employee_name"
                                            wire:model="employee_name"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="{{ __('Employee full name') }}"
                                        >
                                        @error('employee_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Title -->
                                    <div>
                                        <label for="employee_title" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            {{ __('Title/Position') }}
                                        </label>
                                        <input
                                            type="text"
                                            id="employee_title"
                                            wire:model="employee_title"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="{{ __('e.g. Project Manager') }}"
                                        >
                                        @error('employee_title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Phone -->
                                    <div>
                                        <label for="employee_phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            {{ __('Phone') }}
                                        </label>
                                        <input
                                            type="text"
                                            id="employee_phone"
                                            wire:model="employee_phone" x-data x-phone-mask
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="{{ __('Phone number') }}"
                                        >
                                        @error('employee_phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <label for="employee_email" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                            {{ __('Email') }}
                                        </label>
                                        <input
                                            type="email"
                                            id="employee_email"
                                            wire:model="employee_email"
                                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                            placeholder="email@example.com"
                                        >
                                        @error('employee_email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label for="employee_notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        {{ __('Notes') }}
                                    </label>
                                    <textarea
                                        id="employee_notes"
                                        wire:model="employee_notes"
                                        rows="2"
                                        class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                        placeholder="{{ __('Optional notes about this employee...') }}"
                                    ></textarea>
                                    @error('employee_notes') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>

                                <!-- Submit Button -->
                                <div class="flex justify-end">
                                    <x-ui.button
                                        type="submit"
                                        variant="primary"
                                        icon="plus"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50"
                                        wire:target="saveEmployee">
                                        <span wire:loading.remove wire:target="saveEmployee">{{ __('Add Employee') }}</span>
                                        <span wire:loading wire:target="saveEmployee">{{ __('Saving...') }}</span>
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    @endif

                    <!-- Employees List -->
                    <div class="p-6">
                        @if($employees->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Name') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Title') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Phone') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Email') }}</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Notes') }}</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                        @foreach($employees as $employee)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                <td class="px-4 py-4">
                                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $employee->name }}</span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <span class="text-sm text-slate-900 dark:text-white">{{ $employee->title ?? '—' }}</span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    @if($employee->phone)
                                                        <a href="tel:{{ $employee->phone }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $employee->formatted_phone }}</a>
                                                    @else
                                                        <span class="text-sm text-slate-500 dark:text-slate-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4">
                                                    @if($employee->email)
                                                        <a href="mailto:{{ $employee->email }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $employee->email }}</a>
                                                    @else
                                                        <span class="text-sm text-slate-500 dark:text-slate-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4">
                                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $employee->notes ? \Illuminate\Support\Str::limit($employee->notes, 50) : '—' }}</span>
                                                </td>
                                                <td class="px-4 py-4 text-right">
                                                    @can('vendors.edit')
                                                    <button
                                                        wire:click="deleteEmployee({{ $employee->id }})"
                                                        wire:confirm="{{ __('Are you sure you want to delete this employee? Any contracts linked to them will be unlinked.') }}"
                                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        {{ __('Delete') }}
                                                    </button>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <!-- Empty State -->
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No employees yet') }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Add employees to keep track of this subcontractor\'s contacts.') }}
                                </p>
                                @if(!$showEmployeeForm)
                                    <div class="mt-6">
                                        <x-ui.button
                                            variant="primary"
                                            wire:click="toggleEmployeeForm"
                                            icon="plus">
                                            {{ __('Add Employee') }}
                                        </x-ui.button>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <x-ui.modal name="delete-subcontractor-modal" :show="true" maxWidth="lg">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100 dark:bg-red-900/20">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white text-center mb-2">
                    {{ __('Delete Subcontractor') }}
                </h3>

                <p class="text-sm text-slate-600 dark:text-slate-400 text-center mb-4">
                    @if($subcontractor->is_supplier)
                        {{ __('This company is also a supplier.') }}
                        {{ __('Only the subcontractor classification will be removed — the record, its documents and employees are kept.') }}
                    @else
                        Are you sure you want to delete <strong>{{ $subcontractor->company_name }}</strong>?
                        This action <strong>{{ __('cannot be undone') }}</strong>.
                    @endif
                </p>

                @if(!$subcontractor->is_supplier && ($documents->count() > 0 || $employees->count() > 0))
                    <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">{{ __('The following data will be permanently deleted:') }}</p>
                        <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                            @if($documents->count() > 0)
                                <li>{{ $documents->count() }} document(s)</li>
                            @endif
                            @if($employees->count() > 0)
                                <li>{{ $employees->count() }} employee(s)</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="flex justify-end space-x-3">
                    <x-ui.button
                        variant="secondary"
                        wire:click="cancelDeleteSubcontractor"
                        icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="danger"
                        wire:click="deleteSubcontractor"
                        icon="trash">
                        {{ __('Delete Subcontractor') }}
                    </x-ui.button>
                </div>
            </div>
        </x-ui.modal>
    @endif

    <!-- Upload / Renew Document -->
    @if($showUploadForm)
        @php $renewing = $this->renewingDocument; @endphp
        <x-ui.modal name="document-form-modal" :show="true" maxWidth="2xl">
            <form wire:submit="uploadDocument">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ $renewing ? __('Renew Document') : __('Upload Document') }}
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $subcontractor->company_name }}</p>
                    </div>
                    <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="cancelUploadForm" title="{{ __('Cancel') }}" aria-label="{{ __('Cancel') }}" />
                </div>

                <div class="p-6 space-y-4">
                    @if($renewing)
                        <div class="rounded-lg border border-[#3F5189]/30 bg-[#3F5189]/5 dark:bg-[#3F5189]/15 px-4 py-3 text-sm">
                            <p class="font-medium text-slate-900 dark:text-white">{{ __('Renewing :type', ['type' => __($renewing->documentType->name)]) }}</p>
                            <p class="text-slate-600 dark:text-slate-300 mt-0.5">
                                @if($renewing->expiration_date)
                                    {{ __('Replaces :file, expiring :date', ['file' => $renewing->file_name, 'date' => $renewing->expiration_date->appDate()]) }}
                                @else
                                    {{ __('Replaces :file', ['file' => $renewing->file_name]) }}
                                @endif
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('The replaced document stays in the history. The type is kept from the document being renewed.') }}</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Document Type -->
                        <div>
                            <label for="document_type_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Document Type') }} <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="document_type_id"
                                wire:model.live="document_type_id"
                                @if($renewing) disabled @endif
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white disabled:opacity-60">
                                <option value="">{{ __('Select document type...') }}</option>
                                @foreach($documentTypes as $type)
                                    <option value="{{ $type->id }}">
                                        {{ __($type->name) }}@if($type->requires_expiration) ({{ __('requires expiration') }})@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('document_type_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Expiration Date -->
                        <div>
                            <label for="expiration_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                {{ __('Expiration Date') }}
                                @if($this->selectedDocumentType && $this->selectedDocumentType->requires_expiration)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <x-ui.date-input id="expiration_date" wire:model="expiration_date" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white" />
                            @error('expiration_date') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- File -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Document File') }} <span class="text-red-500">*</span>
                        </label>

                        @if(\App\Services\DocumentSettings::isCloudConfigured())
                            {{-- Straight to storage, never through PHP: the file goes up now and the row is written on save. --}}
                            @php $pending = $this->pendingFile; @endphp
                            @if($pending)
                                <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-green-200 dark:border-green-900/50 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                    <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">{{ $pending->original_name }}</span>
                                    <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $pending->formattedSize() }} &middot; {{ __('stored') }}</span>
                                    <x-ui.icon-button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        type="button"
                                        wire:click="discardPendingFile"
                                        title="{{ __('Remove :file', ['file' => $pending->original_name]) }}"
                                        aria-label="{{ __('Remove :file', ['file' => $pending->original_name]) }}"
                                        class="hover:text-red-600 dark:hover:text-red-400" />
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Drop another file to replace it.') }}</p>
                            @endif
                            <div class="{{ $pending ? 'mt-2' : '' }}" wire:key="vendor-uploader-{{ $subcontractor->id }}-{{ $renewing_document_id ?? 'new' }}">
                                <x-ui.file-uploader targetType="vendor" :targetId="$subcontractor->id" completed="documentFileUploaded" :label="__('Drop the document here, or')" />
                            </div>
                        @else
                            {{-- No bucket on this install: through Livewire, stored on save. --}}
                            <x-ui.file-drop
                                wire:model="document_file"
                                :multiple="false"
                                :label="__('Drop the document here, or')">

                                @if($document_file)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                        <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                            {{ $document_file->getClientOriginalName() }}
                                        </span>
                                        <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                            {{ \App\Services\DocumentSettings::formatBytes($document_file->getSize()) }}
                                        </span>
                                        <x-ui.icon-button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            type="button"
                                            wire:click="clearDocumentFile"
                                            title="{{ __('Remove :file', ['file' => $document_file->getClientOriginalName()]) }}"
                                            aria-label="{{ __('Remove :file', ['file' => $document_file->getClientOriginalName()]) }}"
                                            class="hover:text-red-600 dark:hover:text-red-400" />
                                    </div>
                                @endif
                            </x-ui.file-drop>
                        @endif

                        @error('document_file') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="document_notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            {{ __('Notes') }}
                        </label>
                        <textarea
                            id="document_notes"
                            wire:model="document_notes"
                            rows="2"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ __('Optional notes about this document...') }}"
                        ></textarea>
                        @error('document_notes') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelUploadForm" icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        icon="upload"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                        wire:target="document_file,uploadDocument">
                        <span wire:loading.remove wire:target="document_file,uploadDocument">{{ $renewing ? __('Renew Document') : __('Upload Document') }}</span>
                        <span wire:loading wire:target="document_file">{{ __('Uploading file...') }}</span>
                        <span wire:loading wire:target="uploadDocument">{{ __('Saving...') }}</span>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <!-- Document history -->
    @if($historyDocument)
        <x-ui.modal name="document-history-modal" :show="true" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white break-all">{{ __('Document history') }} — {{ $historyDocument->file_name }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ __($historyDocument->documentType->name) }} &middot; {{ $subcontractor->company_name }} &middot;
                        {{ trans_choice('This document and :count earlier version, newest first.|This document and :count earlier versions, newest first.', $historyEntries->count() - 1, ['count' => $historyEntries->count() - 1]) }}
                    </p>
                </div>
                <x-ui.icon-button variant="ghost" size="sm" icon="x" type="button" wire:click="closeHistory" title="{{ __('Close') }}" aria-label="{{ __('Close') }}" />
            </div>

            <div class="max-h-[70vh] overflow-y-auto">
                <ol class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach($historyEntries as $entry)
                        @php
                            $lifecycleChip = match ($entry->status) {
                                'active' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                'archived' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
                                default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                            };
                        @endphp
                        <li class="px-6 py-4 {{ $entry->isActive() ? '' : 'bg-slate-50/60 dark:bg-slate-900/30' }}" wire:key="hist-{{ $entry->id }}">
                            <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $lifecycleChip }}">{{ $entry->status_label }}</span>
                                        @if($entry->isActive() && $entry->documentType->requires_expiration && $entry->documentType->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $expiryChip[$entry->expiry_status] }}">{{ $entry->expiry_status_label }}</span>
                                        @endif
                                        <p class="text-sm font-medium text-slate-900 dark:text-white break-all">{{ $entry->file_name }}</p>
                                        <span class="text-xs text-slate-400 dark:text-slate-500">{{ $entry->formatted_file_size }}</span>
                                    </div>

                                    <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-xs">
                                        <div class="flex gap-2">
                                            <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Expiration') }}</dt>
                                            <dd class="text-slate-900 dark:text-white">
                                                @if($entry->expiration_date)
                                                    {{ $entry->expiration_date->appDate() }}
                                                    @php $days = $entry->days_until_expiry; @endphp
                                                    <span class="text-slate-500 dark:text-slate-400">
                                                        &middot;
                                                        @if($days === 0) {{ __('Expires today') }}
                                                        @elseif($days > 0) {{ trans_choice('Expires in :count day|Expires in :count days', $days, ['count' => $days]) }}
                                                        @else {{ trans_choice('Expired :count day ago|Expired :count days ago', abs($days), ['count' => abs($days)]) }}
                                                        @endif
                                                    </span>
                                                @else
                                                    {{ __('No expiration') }}
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="flex gap-2">
                                            <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Uploaded') }}</dt>
                                            <dd class="text-slate-900 dark:text-white">{{ __('by :name', ['name' => $entry->uploadedBy->name ?? __('Unknown')]) }} &middot; {{ $entry->created_at->appDateTime() }}</dd>
                                        </div>
                                        @if($entry->supersedes)
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Replaced') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">{{ $entry->supersedes->file_name }}</dd>
                                            </div>
                                        @endif
                                        @if($entry->isSuperseded() && $entry->supersededBy)
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Replaced by') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">{{ $entry->supersededBy->file_name }} &middot; {{ $entry->supersededBy->created_at->appDateTime() }}</dd>
                                            </div>
                                        @endif
                                        @if($entry->isArchived())
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Archived') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">
                                                    {{ __('by :name', ['name' => $entry->archivedBy->name ?? __('Unknown')]) }} &middot; {{ $entry->archived_at?->appDateTime() ?? '—' }}
                                                    @if($entry->archive_reason)
                                                        &middot; <span class="italic">{{ $entry->archive_reason }}</span>
                                                    @endif
                                                </dd>
                                            </div>
                                        @endif
                                        @if($entry->reminded_stages !== [])
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Reminders') }}</dt>
                                                <dd class="text-slate-900 dark:text-white">{{ collect($entry->reminded_stages)->map($stageText)->join(', ') }}</dd>
                                            </div>
                                        @endif
                                        @if($entry->notes)
                                            <div class="flex gap-2 sm:col-span-2">
                                                <dt class="text-slate-500 dark:text-slate-400 shrink-0">{{ __('Notes') }}</dt>
                                                <dd class="text-slate-900 dark:text-white whitespace-pre-line">{{ $entry->notes }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>

                                <div class="flex items-center gap-1 sm:justify-end shrink-0">
                                    <x-ui.icon-button variant="ghost" size="sm" icon="download" href="{{ $entry->downloadUrl() }}" title="{{ __('Download') }}" aria-label="{{ __('Download') }}" />
                                    @if($entry->isActive() && $canRenew)
                                        <x-ui.button variant="ghost" size="sm" wire:click="startRenewal({{ $entry->id }})">{{ __('Renew') }}</x-ui.button>
                                    @endif
                                    @if($entry->isActive() && $canArchive)
                                        <x-ui.button variant="ghost" size="sm" wire:click="startArchive({{ $entry->id }})">{{ __('Archive') }}</x-ui.button>
                                    @endif
                                    @if($entry->isArchived() && $canArchive)
                                        <x-ui.button variant="ghost" size="sm" wire:click="reactivateDocument({{ $entry->id }})">{{ __('Reactivate') }}</x-ui.button>
                                    @endif
                                    @if($canDelete)
                                        <x-ui.icon-button variant="ghost" size="sm" icon="trash" wire:click="deleteDocument({{ $entry->id }})" wire:confirm="{{ __('Are you sure you want to delete this document?') }}" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}" class="hover:text-red-600 dark:hover:text-red-400" />
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                <x-ui.button type="button" variant="secondary" wire:click="closeHistory" icon="x">{{ __('Close') }}</x-ui.button>
            </div>
        </x-ui.modal>
    @endif

    <!-- Archive Document -->
    @if($archiving_document_id)
        <x-ui.modal name="archive-document-modal" :show="true" maxWidth="lg">
            <form wire:submit="archiveDocument" class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">{{ __('Archive Document') }}</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                    {{ __('Archiving takes this document out of the expiry watch: no badge, no reminder e-mails. It stays here in the history and can be reactivated.') }}
                </p>

                <label for="archive_reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Reason') }} <span class="text-red-500">*</span>
                </label>
                <textarea
                    id="archive_reason"
                    wire:model="archive_reason"
                    rows="3"
                    maxlength="255"
                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                    placeholder="{{ __('Why is this document no longer required?') }}"
                ></textarea>
                @error('archive_reason') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

                <div class="flex justify-end gap-3 mt-6">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelArchive" icon="x">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button type="submit" variant="warning">
                        {{ __('Archive Document') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
