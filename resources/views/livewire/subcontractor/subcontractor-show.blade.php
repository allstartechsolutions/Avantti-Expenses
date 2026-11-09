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
            <div class="flex flex-wrap items-center gap-3">
                <x-vendor.active-state :vendor="$subcontractor" :canEdit="auth()->user()->can('vendors.edit')" />
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
                    @if($linkedContracts + $linkedPaymentBatches + $linkedBackCharges > 0)
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

    <x-vendor.inactive-notice :vendor="$subcontractor" />

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
                    @if($employeeCounts['all'] > 0)
                        <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                            {{ $employeeCounts['all'] }}
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

                    @can('back-charges.view')
                        @php
                            $openBackCharges = $backCharges->reject->isFullyDeducted();
                            $leftToDeduct = round($openBackCharges->sum(fn ($bc) => $bc->getRemainingCents()) / 100, 2);
                        @endphp
                        <!-- Back charges (docs/back-charges.md) -->
                        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Back Charges') }}</h3>
                                @if($backCharges->isNotEmpty())
                                    <a href="{{ route('back-charges.index', ['subcontractorFilter' => $subcontractor->id]) }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('View all') }}</a>
                                @endif
                            </div>
                            <div class="p-6">
                                @if($backCharges->isEmpty())
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No back charges for this subcontractor.') }}</p>
                                @else
                                    <div class="flex items-baseline justify-between mb-3">
                                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ trans_choice(':count open|:count open', $openBackCharges->count(), ['count' => $openBackCharges->count()]) }}</span>
                                        <span class="text-lg font-semibold {{ $leftToDeduct > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}"><x-ui.money rollup :amount="$leftToDeduct" /></span>
                                    </div>
                                    <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                                        @foreach($openBackCharges->take(5) as $bc)
                                            <li wire:key="sub-bc-{{ $bc->id }}" class="py-2 flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <a href="{{ route('back-charges.show', $bc) }}" class="text-sm font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ $bc->number }}</a>
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $bc->reason }}@if($bc->subcontractorEmployee) · {{ $bc->subcontractorEmployee->name }}@endif</p>
                                                </div>
                                                <span class="text-sm font-medium text-amber-600 dark:text-amber-400 whitespace-nowrap"><x-ui.money :amount="$bc->getRemaining()" /></span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if($openBackCharges->count() > 5)
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ trans_choice('and :count more|and :count more', $openBackCharges->count() - 5, ['count' => $openBackCharges->count() - 5]) }}</p>
                                    @endif
                                    @if($openBackCharges->isEmpty())
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Everything has been deducted.') }}</p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endcan

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
                                <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $employeeCounts['all'] }}@if($employeeCounts['inactive'] > 0) <span class="text-xs font-normal text-slate-500 dark:text-slate-400">({{ trans_choice(':count inactive|:count inactive', $employeeCounts['inactive'], ['count' => $employeeCounts['inactive']]) }})</span>@endif</span>
                            </div>
                            @if($linkedEmployees > 0)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Also known at other companies') }}</span>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $linkedEmployees }}</span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Status') }}</span>
                                <x-vendor.active-badge :active="$subcontractor->is_active" />
                            </div>
                            @if(! $subcontractor->is_active && $subcontractor->deactivated_at)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Switched off') }}</span>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $subcontractor->deactivated_at->appDateTime() }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Switched off by') }}</span>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $subcontractor->deactivatedBy?->name ?? __('Unknown') }}</span>
                                </div>
                            @endif
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
            @include('livewire.vendor.partials.documents-tab', ['documentVendorId' => $subcontractor->id, 'documentVendorName' => $subcontractor->company_name])
        @endif

        <!-- Employees Tab -->
        @if($activeTab === 'employees')
            @include('livewire.subcontractor.partials.employees-tab')
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

                @if(!$subcontractor->is_supplier && ($documents->count() > 0 || $employeeCounts['all'] > 0))
                    <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                        <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">{{ __('The following data will be permanently deleted:') }}</p>
                        <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                            @if($documents->count() > 0)
                                <li>{{ $documents->count() }} document(s)</li>
                            @endif
                            @if($employeeCounts['all'] > 0)
                                <li>{{ trans_choice(':count employee|:count employees', $employeeCounts['all'], ['count' => $employeeCounts['all']]) }}</li>
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

    @include('livewire.vendor.partials.document-modals', ['documentVendorId' => $subcontractor->id, 'documentVendorName' => $subcontractor->company_name])
    @include('livewire.subcontractor.partials.link-employee-modal')
</div>
