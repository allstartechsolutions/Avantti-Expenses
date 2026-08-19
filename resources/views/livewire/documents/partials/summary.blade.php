{{-- Counts, sizes and the category spread of whatever the filters match. --}}
@php($stats = $this->stats)

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="bg-gradient-to-r from-[#3F5189] to-[#4A5A96] rounded-lg shadow-sm p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-white/80">{{ $showTrash ? __('In the trash') : __('Documents') }}</p>
                <p class="text-2xl font-bold mt-1">{{ number_format($stats['count']) }}</p>
            </div>
            <div class="bg-white/10 rounded-full p-3">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Size shown') }}</p>
        <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $stats['size'] }}</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('of :total in this project', ['total' => $stats['project_size']]) }}</p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Storage used') }}</p>
        @if($stats['quota'])
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $stats['quota_percent'] }}%</p>
            <div class="mt-2 h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                <div class="h-full rounded-full {{ $stats['quota_percent'] >= 90 ? 'bg-red-500' : ($stats['quota_percent'] >= 70 ? 'bg-amber-500' : 'bg-[#3F5189]') }}"
                     style="width: {{ $stats['quota_percent'] }}%"></div>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $stats['project_size'] }} / {{ $stats['quota_size'] }}</p>
        @else
            <p class="text-2xl font-bold mt-1 text-slate-900 dark:text-white">{{ $stats['project_size'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('No storage limit set') }}</p>
        @endif
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-2">{{ __('By category') }}</p>
        @if(count($stats['by_category']))
            <div class="space-y-1.5 max-h-24 overflow-y-auto pr-1">
                @foreach($stats['by_category'] as $category => $total)
                    @php($case = \App\Enums\DocumentCategory::tryFrom($category) ?? \App\Enums\DocumentCategory::OTHER)
                    <button type="button" wire:click="$set('categoryFilter', '{{ $category }}')"
                        class="w-full flex items-center justify-between text-xs text-slate-600 dark:text-slate-300 hover:text-[#3F5189] dark:hover:text-[#8B9DD6]">
                        <span class="truncate">{{ __($case->label()) }}</span>
                        <span class="font-semibold ml-2">{{ $total }}</span>
                    </button>
                @endforeach
            </div>
        @else
            <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('Nothing here yet.') }}</p>
        @endif
    </div>
</div>
