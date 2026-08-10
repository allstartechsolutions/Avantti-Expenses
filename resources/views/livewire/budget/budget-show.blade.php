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
                    Edit Budget
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

    <!-- Summary Card -->
    <div class="mb-6 bg-gradient-to-r from-[#3F5189] to-[#5A6FA8] rounded-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-white/80">Total Budget</p>
                <p class="text-3xl font-bold mt-1">{{ Number::currency($budget->total_amount, config('app.currency'), config('app.locale')) }}</p>
                <p class="text-sm text-white/70 mt-1">{{ $budget->items_count }} cost codes</p>
            </div>
            @if($budget->sourceTemplate)
                <div class="text-right">
                    <p class="text-sm text-white/80">Template</p>
                    <p class="font-medium">{{ $budget->sourceTemplate->name }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Budget Items List -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Cost Codes</h3>
                    <x-ui.button
                        variant="primary"
                        size="sm"
                        wire:click="openAddForm()"
                        icon="plus">
                        Add Cost Code
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
                                                <span class="font-medium text-slate-900 dark:text-white">{{ $parentItem->name }}</span>
                                                @if($parentItem->is_default)
                                                    <span class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                                @endif
                                                @if($parentItem->description)
                                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $parentItem->description }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4 flex-shrink-0">
                                            <span class="font-semibold text-slate-900 dark:text-white">
                                                {{ Number::currency($parentItem->budgeted_amount, config('app.currency'), config('app.locale')) }}
                                            </span>
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
                                                    title="Add child code">
                                                </x-ui.button>
                                                <x-ui.button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="openEditForm({{ $parentItem->id }})"
                                                    icon="edit"
                                                    title="Edit">
                                                </x-ui.button>
                                                @if($parentItem->children->count() === 0)
                                                    <x-ui.button
                                                        variant="ghost"
                                                        size="sm"
                                                        wire:click="deleteItem({{ $parentItem->id }})"
                                                        wire:confirm="Are you sure you want to delete this budget item?"
                                                        icon="trash"
                                                        title="Delete"
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
                                                            <span class="text-sm text-slate-900 dark:text-white">{{ $childItem->name }}</span>
                                                            @if($childItem->is_default)
                                                                <span class="ml-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400">{{ __('Default') }}</span>
                                                            @endif
                                                            @if($childItem->description)
                                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $childItem->description }}</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-4 flex-shrink-0">
                                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                                            {{ Number::currency($childItem->budgeted_amount, config('app.currency'), config('app.locale')) }}
                                                        </span>
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
                                                                title="Edit">
                                                            </x-ui.button>
                                                            <x-ui.button
                                                                variant="ghost"
                                                                size="sm"
                                                                wire:click="deleteItem({{ $childItem->id }})"
                                                                wire:confirm="Are you sure you want to delete this budget item?"
                                                                icon="trash"
                                                                title="Delete"
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

                        <!-- Total Row -->
                        <div class="mt-6 pt-4 border-t-2 border-slate-300 dark:border-slate-600">
                            <div class="flex items-center justify-between px-4">
                                <span class="font-semibold text-slate-900 dark:text-white">Total</span>
                                <span class="text-xl font-bold text-slate-900 dark:text-white">
                                    {{ Number::currency($budget->total_amount, config('app.currency'), config('app.locale')) }}
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-slate-900 dark:text-white">No cost codes yet</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by adding cost codes to this budget.</p>
                            <div class="mt-6">
                                <x-ui.button
                                    variant="primary"
                                    wire:click="openAddForm()"
                                    icon="plus">
                                    Add Cost Code
                                </x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Add/Edit Form -->
            @if($showForm)
                <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ $editingItemId ? 'Edit Cost Code' : ($parentId ? 'Add Child Code' : 'Add Cost Code') }}
                        </h3>
                        <button wire:click="closeForm" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <form wire:submit="save" class="p-6 space-y-4">
                        @if($parentId)
                            @php
                                $parentItem = $budget->items->find($parentId);
                            @endphp
                            <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                                <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Parent Code</p>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $parentItem?->code }} - {{ $parentItem?->name }}
                                </p>
                            </div>
                        @endif

                        <!-- Code -->
                        <div>
                            <label for="code" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Code <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="code"
                                wire:model="code"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white font-mono"
                                placeholder="e.g., 01, 01.1, A100">
                            @error('code')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="name"
                                wire:model="name"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="e.g., General Requirements">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Budgeted Amount -->
                        <div>
                            <label for="budgeted_amount" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Budgeted Amount <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400">$</span>
                                <input
                                    type="number"
                                    id="budgeted_amount"
                                    wire:model="budgeted_amount"
                                    step="0.01"
                                    min="0"
                                    class="w-full pl-7 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                    placeholder="0.00">
                            </div>
                            @error('budgeted_amount')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Description
                            </label>
                            <textarea
                                id="description"
                                wire:model="description"
                                rows="2"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                                placeholder="Optional description"></textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Sort Order -->
                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Sort Order
                            </label>
                            <input
                                type="number"
                                id="sort_order"
                                wire:model="sort_order"
                                min="0"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                            @error('sort_order')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <x-ui.button
                                type="button"
                                variant="secondary"
                                wire:click="closeForm">
                                Cancel
                            </x-ui.button>
                            <x-ui.button
                                type="submit"
                                variant="primary"
                                icon="check">
                                {{ $editingItemId ? 'Update' : 'Add' }}
                            </x-ui.button>
                        </div>
                    </form>
                </div>
            @endif

            <!-- Budget Info -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Budget Information</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Location</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->location_name }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Project</span>
                        <a href="{{ route('projects.overview', $budget->project_id) }}" class="text-sm font-medium text-[#3F5189] hover:underline">
                            {{ $budget->project->project_name }}
                        </a>
                    </div>
                    @if($budget->jobSite)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Job Site</span>
                            <a href="{{ route('jobsites.overview', $budget->job_site_id) }}" class="text-sm font-medium text-[#3F5189] hover:underline">
                                {{ $budget->jobSite->job_site_name }}
                            </a>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Total Cost Codes</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->items_count }}</span>
                    </div>
                    @if($budget->sourceTemplate)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Template</span>
                            <a href="{{ route('cost-codes.templates.show', $budget->source_template_id) }}" class="text-sm font-medium text-[#3F5189] hover:underline">
                                {{ $budget->sourceTemplate->name }}
                            </a>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Created</span>
                        <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->created_at->diffForHumans() }}</span>
                    </div>
                    @if($budget->creator)
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">Created By</span>
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $budget->creator->name }}</span>
                        </div>
                    @endif
                </div>

                @if($budget->notes)
                    <div class="px-6 pb-6">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg">
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Notes</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $budget->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
