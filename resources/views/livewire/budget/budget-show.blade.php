<div>
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $budget->name }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $budget->location_name }} &bull;
                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ $budget->project->project_name }}</span>
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ $this->backUrl }}"
                    icon="arrow-left">
                    {{ $this->backLabel }}
                </x-ui.button>
                <x-ui.button
                    variant="secondary"
                    href="{{ route('budgets.cost-grid', $budget->id) }}"
                    icon="eye">
                    {{ __('Cost Grid') }}
                </x-ui.button>
                <x-ui.button
                    variant="primary"
                    href="{{ route('budgets.edit', $budget->id) }}"
                    icon="edit">
                    {{ __('Edit Budget') }}
                </x-ui.button>
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

    @php
        $fmt = fn ($v) => Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $signedAmount = fn ($v) => ((float) $v > 0 ? '+' : '') . Number::currency((float) $v, config('app.currency'), config('app.locale'));
        $usedPct = $ledgerTotals['percent_committed'];
        $spentPct = $ledgerTotals['percent_spent'];
    @endphp

    <!-- Where this budget stands -->
    <div class="mb-6 bg-gradient-to-r from-[#3F5189] to-[#5A6FA8] rounded-lg p-6 text-white">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <p class="text-sm text-white/80">{{ __('Revised Budget') }}</p>
                <p class="text-3xl font-bold mt-1">{{ $fmt($ledgerTotals['revised']) }}</p>
                <p class="text-sm text-white/70 mt-1">
                    {{ __('Original') }} {{ $fmt($ledgerTotals['original']) }}
                    @if((float) $ledgerTotals['changes'] != 0.0)
                        &bull; {{ __('Approved changes') }} {{ $signedAmount($ledgerTotals['changes']) }}
                    @endif
                    &bull; {{ trans_choice(':count cost code|:count cost codes', $budget->items_count, ['count' => $budget->items_count]) }}
                </p>
            </div>

            <div class="lg:text-right">
                <p class="text-sm text-white/80">{{ (float) $ledgerTotals['remaining'] < 0 ? __('Over budget') : __('Remaining') }}</p>
                <p class="text-3xl font-bold mt-1 {{ (float) $ledgerTotals['remaining'] < 0 ? 'text-red-200' : '' }}">
                    {{ $fmt(abs($ledgerTotals['remaining'])) }}
                </p>
                @if($usedPct !== null)
                    <div class="mt-2 flex items-center gap-2 lg:justify-end">
                        <div class="relative w-40 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="absolute inset-y-0 left-0 bg-white/50" style="width: {{ min(100, max(0, $usedPct)) }}%"></div>
                            <div class="absolute inset-y-0 left-0 bg-white" style="width: {{ min(100, max(0, $spentPct ?? 0)) }}%"></div>
                        </div>
                        <span class="text-sm text-white/80">{{ number_format($usedPct, 0) }}% {{ __('used') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-white/20 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-white/70">{{ __('Committed') }}</p>
                <p class="font-semibold mt-0.5">{{ $fmt($ledgerTotals['committed']) }}</p>
            </div>
            <div>
                <p class="text-white/70">{{ __('Actual') }}</p>
                <p class="font-semibold mt-0.5">{{ $fmt($ledgerTotals['actual']) }}</p>
            </div>
            <div>
                <p class="text-white/70">{{ __('Projected') }}</p>
                <p class="font-semibold mt-0.5">{{ $fmt($ledgerTotals['projected']) }}</p>
            </div>
            <div>
                <p class="text-white/70">{{ __('Template') }}</p>
                <p class="font-semibold mt-0.5">{{ $budget->sourceTemplate?->name ?? __('None') }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Budget Items List -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Cost Codes') }}</h3>
                    <x-ui.button
                        variant="primary"
                        size="sm"
                        wire:click="openAddForm()"
                        icon="plus">
                        {{ __('Add Cost Code') }}
                    </x-ui.button>
                </div>

                <div class="p-6">
                    @if($budget->parentItems->count() > 0)
                        <div class="space-y-3">
                            @foreach($budget->parentItems as $parentItem)
                                <!-- Parent Item -->
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg">
                                    <div class="flex items-center justify-between px-4 py-3 bg-slate-50 dark:bg-slate-900/50 rounded-t-lg">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <span class="px-2 py-1 text-xs font-mono font-semibold rounded bg-[#3F5189] text-white flex-shrink-0">
                                                {{ $parentItem->code }}
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <a href="{{ route('budgets.cost-code', [$budget->id, $parentItem->id]) }}" class="font-medium text-slate-900 dark:text-white hover:text-[#3F5189] dark:hover:text-[#4A5A96] hover:underline">{{ $parentItem->name }}</a>
                                                @if($parentItem->is_default)
                                                    <span class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                                @endif
                                                @if($parentItem->description)
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $parentItem->description }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 flex-shrink-0">
                                            @include('livewire.budget.partials.code-figures', [
                                                'row' => $ledgerRows[$parentItem->id] ?? null,
                                                'item' => $parentItem,
                                                'size' => 'parent',
                                            ])
                                            <div class="flex items-center gap-1">
                                                <x-ui.button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="toggleDefaultItem({{ $parentItem->id }})"
                                                    icon="star"
                                                    title="{{ $parentItem->is_default ? 'Clear default cost code' : 'Set as default cost code' }}"
                                                    class="{{ $parentItem->is_default ? 'text-amber-500 hover:text-amber-600' : '' }}">
                                                </x-ui.button>
                                                <x-ui.button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="openAddForm({{ $parentItem->id }})"
                                                    icon="plus"
                                                    title="{{ __('Add child code') }}">
                                                </x-ui.button>
                                                <x-ui.button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="openEditForm({{ $parentItem->id }})"
                                                    icon="edit"
                                                    title="{{ __('Edit') }}">
                                                </x-ui.button>
                                                @if($parentItem->children->count() === 0)
                                                    <x-ui.button
                                                        variant="ghost"
                                                        size="sm"
                                                        wire:click="deleteItem({{ $parentItem->id }})"
                                                        wire:confirm="{{ __('Are you sure you want to delete this budget item?') }}"
                                                        icon="trash"
                                                        title="{{ __('Delete') }}"
                                                        class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                                    </x-ui.button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Child Items -->
                                    @if($parentItem->children->count() > 0)
                                        <div class="divide-y divide-slate-200 dark:divide-slate-700">
                                            @foreach($parentItem->children as $childItem)
                                                <div class="flex items-center justify-between px-4 py-3 pl-10 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                                    <div class="flex items-center gap-3 flex-1 min-w-0">
                                                        <span class="px-2 py-1 text-xs font-mono font-medium rounded bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 flex-shrink-0">
                                                            {{ $childItem->code }}
                                                        </span>
                                                        <div class="flex-1 min-w-0">
                                                            <a href="{{ route('budgets.cost-code', [$budget->id, $childItem->id]) }}" class="text-sm text-slate-900 dark:text-white hover:text-[#3F5189] dark:hover:text-[#4A5A96] hover:underline">{{ $childItem->name }}</a>
                                                            @if($childItem->is_default)
                                                                <span class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                                            @endif
                                                            @if($childItem->description)
                                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $childItem->description }}</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-4 flex-shrink-0">
                                                        @include('livewire.budget.partials.code-figures', [
                                                            'row' => $ledgerRows[$childItem->id] ?? null,
                                                            'item' => $childItem,
                                                            'size' => 'child',
                                                        ])
                                                        <div class="flex items-center gap-1">
                                                            <x-ui.button
                                                                variant="ghost"
                                                                size="sm"
                                                                wire:click="toggleDefaultItem({{ $childItem->id }})"
                                                                icon="star"
                                                                title="{{ $childItem->is_default ? 'Clear default cost code' : 'Set as default cost code' }}"
                                                                class="{{ $childItem->is_default ? 'text-amber-500 hover:text-amber-600' : '' }}">
                                                            </x-ui.button>
                                                            <x-ui.button
                                                                variant="ghost"
                                                                size="sm"
                                                                wire:click="openEditForm({{ $childItem->id }})"
                                                                icon="edit"
                                                                title="{{ __('Edit') }}">
                                                            </x-ui.button>
                                                            <x-ui.button
                                                                variant="ghost"
                                                                size="sm"
                                                                wire:click="deleteItem({{ $childItem->id }})"
                                                                wire:confirm="{{ __('Are you sure you want to delete this budget item?') }}"
                                                                icon="trash"
                                                                title="{{ __('Delete') }}"
                                                                class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                                                            </x-ui.button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if(isset($ledgerRows[0]))
                            <div class="mt-4 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <a href="{{ route('budgets.unassigned', $budget->id) }}" class="text-sm font-medium text-amber-900 dark:text-amber-200 hover:underline">{{ __('Unassigned') }}</a>
                                    <p class="text-xs text-amber-700 dark:text-amber-300">
                                        {{ __('Costs with no cost code. Star a code above to make it the default and they will land there instead.') }}
                                    </p>
                                </div>
                                <span class="text-sm font-semibold text-amber-900 dark:text-amber-200 shrink-0">
                                    {{ $fmt($ledgerRows[0]['projected']) }}
                                </span>
                            </div>
                        @endif

                        <!-- Total Row -->
                        <div class="mt-6 pt-4 border-t-2 border-slate-300 dark:border-slate-600">
                            <div class="flex items-center justify-between px-4">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ __('Total') }}</span>
                                <div class="text-right">
                                    <span class="text-xl font-bold text-slate-900 dark:text-white">{{ $fmt($ledgerTotals['revised']) }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">
                                        {{ $fmt($ledgerTotals['actual']) }} {{ __('spent') }} &bull;
                                        {{ (float) $ledgerTotals['remaining'] < 0
                                            ? __(':amount over', ['amount' => $fmt(abs($ledgerTotals['remaining']))])
                                            : __(':amount left', ['amount' => $fmt($ledgerTotals['remaining'])]) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ __('No cost codes yet') }}</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Get started by adding cost codes to this budget.') }}</p>
                            <div class="mt-6">
                                <x-ui.button
                                    variant="primary"
                                    wire:click="openAddForm()"
                                    icon="plus">
                                    {{ __('Add Cost Code') }}
                                </x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Budget Info -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Budget Information') }}</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Location') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->location_name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Project') }}</span>
                        <a href="{{ route('projects.overview', $budget->project_id) }}" class="text-sm font-medium text-[#3F5189] hover:underline">
                            {{ $budget->project->project_name }}
                        </a>
                    </div>
                    @if($budget->jobSite)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Job Site') }}</span>
                            <a href="{{ route('jobsites.overview', $budget->job_site_id) }}" class="text-sm font-medium text-[#3F5189] hover:underline">
                                {{ $budget->jobSite->job_site_name }}
                            </a>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Total Cost Codes') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->items_count }}</span>
                    </div>
                    @if($budget->sourceTemplate)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Template') }}</span>
                            <a href="{{ route('cost-codes.templates.show', $budget->source_template_id) }}" class="text-sm font-medium text-[#3F5189] hover:underline">
                                {{ $budget->sourceTemplate->name }}
                            </a>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Created') }}</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->created_at->diffForHumans() }}</span>
                    </div>
                    @if($budget->creator)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ __('Created By') }}</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->creator->name }}</span>
                        </div>
                    @endif
                </div>

                @if($budget->notes)
                    <div class="px-6 pb-6">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">{{ __('Notes') }}</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $budget->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('livewire.budget.partials.item-modal')
</div>
