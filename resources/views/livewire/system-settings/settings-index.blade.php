<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('System Settings') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Manage system-wide configuration') }}</p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
        <div class="border-b border-slate-200 dark:border-slate-700">
            <nav class="-mb-px flex space-x-8">
                <button
                    wire:click="switchTab('tax-rates')"
                    class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'tax-rates' ? 'border-[#3F5189] text-[#3F5189] dark:text-[#4A5A96] dark:border-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Tax Rates') }}
                </button>
                <button
                    wire:click="switchTab('messages')"
                    class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'messages' ? 'border-[#3F5189] text-[#3F5189] dark:text-[#4A5A96] dark:border-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Messages') }}
                </button>
                <button
                    wire:click="switchTab('modules')"
                    class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'modules' ? 'border-[#3F5189] text-[#3F5189] dark:text-[#4A5A96] dark:border-[#4A5A96]' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300' }}">
                    {{ __('Modules') }}
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    @if($activeTab === 'tax-rates')
        <livewire:system-settings.tax-rate-settings />
    @elseif($activeTab === 'messages')
        <livewire:system-settings.document-message-settings />
    @elseif($activeTab === 'modules')
        <livewire:system-settings.module-access-settings />
    @endif
</div>
