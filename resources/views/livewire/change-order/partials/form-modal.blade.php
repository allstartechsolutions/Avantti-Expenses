{{--
    Change order — full page, shared by the project and job-site levels.
    Expects: $contextName, $showJobSitePicker, $jobSites, $coBudget,
             $coLineSuggestions, $changeOrderRecord
--}}
@php
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
    $card = 'bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5';
    $viewing = $changeOrderModalMode === 'view';
    $money = fn ($value) => Number::currency((float) $value, config('app.currency'), config('app.locale'));
    $signed = fn ($value) => ((float) $value >= 0 ? '+' : '') . Number::currency((float) $value, config('app.currency'), config('app.locale'));
    $statusStyles = [
        'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'approved' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    ];
    $statusLabels = [
        'draft' => __('Draft'),
        'pending' => __('Pending'),
        'approved' => __('Approved'),
        'rejected' => __('Rejected'),
    ];
    $costTotal = $this->changeOrderCostTotal();
    $margin = $this->changeOrderMargin();
    $marginPercent = $this->changeOrderMarginPercent();
@endphp

<x-ui.modal name="change-order-modal" maxWidth="full">
    <form wire:submit="saveChangeOrder" class="flex min-h-screen flex-col">
        <!-- Header -->
        <div class="sticky top-0 z-20 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                            @if($viewing)
                                {{ __('Change Order') }}
                            @elseif($changeOrderModalMode === 'edit')
                                {{ __('Edit Change Order') }}
                            @else
                                {{ __('New Change Order') }}
                            @endif
                        </h2>
                        @if($co_number)
                            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $co_number }}</span>
                        @endif
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusStyles[$co_status] ?? $statusStyles['draft'] }}">
                            {{ $statusLabels[$co_status] ?? $co_status }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $contextName }}</p>
                </div>
                <button
                    type="button"
                    wire:click="closeChangeOrderModal"
                    class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    title="{{ __('Close') }}">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="flex-1 mx-auto w-full max-w-7xl px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- The change itself -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="{{ $card }} space-y-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('The Change') }}</h3>

                        @if($viewing)
                            <div>
                                <p class="{{ $label }}">{{ __('Title') }}</p>
                                <p class="text-slate-900 dark:text-white">{{ $co_title }}</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="{{ $label }}">{{ __('Number') }}</label>
                                    <input type="text" wire:model="co_number" placeholder="CO-0001" class="{{ $field }}">
                                    @error('co_number') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="{{ $label }}">{{ __('Title') }} <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="co_title" placeholder="{{ __('e.g. Add 40m of retaining wall') }}" class="{{ $field }}">
                                    @error('co_title') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="{{ $label }}">{{ __('Requested Date') }} @unless($viewing)<span class="text-red-500">*</span>@endunless</label>
                                @if($viewing)
                                    <p class="text-slate-900 dark:text-white">{{ $co_requested_date ? \Carbon\Carbon::parse($co_requested_date)->translatedFormat('d M Y') : '—' }}</p>
                                @else
                                    <x-ui.date-input wire:model="co_requested_date" class="{{ $field }}" />
                                    @error('co_requested_date') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                @endif
                            </div>

                            <div>
                                <label class="{{ $label }}">{{ __('Status') }}</label>
                                @if($viewing)
                                    <p class="text-slate-900 dark:text-white">{{ $statusLabels[$co_status] ?? $co_status }}</p>
                                @else
                                    <select wire:model.live="co_status" class="{{ $field }}">
                                        <option value="draft">{{ __('Draft') }}</option>
                                        <option value="pending">{{ __('Pending') }}</option>
                                        <option value="approved">{{ __('Approved') }}</option>
                                        <option value="rejected">{{ __('Rejected') }}</option>
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $co_status === 'approved'
                                            ? __('Approved: the cost lines below revise the budget of each cost code.')
                                            : __('Only an approved change order revises the budget. The client contract value counts it either way.') }}
                                    </p>
                                    @error('co_status') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                @endif
                            </div>
                        </div>

                        @if($showJobSitePicker)
                            <div>
                                <label class="{{ $label }}">{{ __('Location') }}</label>
                                @if($viewing)
                                    <p class="text-slate-900 dark:text-white">
                                        {{ $co_job_site_id ? ($jobSites->find($co_job_site_id)?->job_site_name ?? __('Unknown Job Site')) : __('Project (General)') }}
                                    </p>
                                @else
                                    <select wire:model.live="co_job_site_id" class="{{ $field }}">
                                        <option value="">{{ __('Project (General)') }}</option>
                                        @foreach($jobSites as $js)
                                            <option value="{{ $js->id }}">{{ $js->job_site_name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('The cost codes below come from this location\'s budget.') }}</p>
                                    @error('co_job_site_id') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                @endif
                            </div>
                        @endif

                        <div>
                            <label class="{{ $label }}">{{ __('Description') }}</label>
                            @if($viewing)
                                <p class="text-slate-900 dark:text-white whitespace-pre-line">{{ $co_description ?: '—' }}</p>
                            @else
                                <textarea wire:model="co_description" rows="4" placeholder="{{ __('What changed, and why') }}" class="{{ $field }}"></textarea>
                                @error('co_description') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            @endif
                        </div>

                        <div>
                            <label class="{{ $label }}">{{ __('Attached File') }}</label>
                            @if($existingFilePath)
                                <a href="{{ route('files.download', ['path' => $existingFilePath]) }}" class="inline-flex items-center gap-1 text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ __('Download') }}
                                </a>
                            @elseif($viewing)
                                <p class="text-slate-500 dark:text-slate-400">{{ __('No file attached.') }}</p>
                            @endif

                            @unless($viewing)
                                <x-ui.file-drop
                                    wire:model="co_file"
                                    :multiple="false"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    :label="__('Drop the file here, or')"
                                    :hint="__('PDF, JPG or PNG, up to 10MB.')"
                                    class="mt-2 space-y-2">

                                    @error('co_file') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                                    @if($co_file)
                                        <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg">
                                            <span class="min-w-0 flex-1 truncate text-slate-900 dark:text-white">
                                                {{ $co_file->getClientOriginalName() }}
                                            </span>
                                            <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">
                                                {{ \App\Services\DocumentSettings::formatBytes($co_file->getSize()) }}
                                            </span>
                                            <x-ui.icon-button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                type="button"
                                                wire:click="clearChangeOrderFile"
                                                title="{{ __('Remove :file', ['file' => $co_file->getClientOriginalName()]) }}"
                                                aria-label="{{ __('Remove :file', ['file' => $co_file->getClientOriginalName()]) }}"
                                                class="hover:text-red-600 dark:hover:text-red-400" />
                                        </div>

                                        @if($existingFilePath)
                                            <p class="text-xs text-amber-600 dark:text-amber-400">
                                                {{ __('This replaces the file already on the change order when you save.') }}
                                            </p>
                                        @endif
                                    @endif
                                </x-ui.file-drop>
                            @endunless
                        </div>
                    </div>

                    @if($viewing && $changeOrderRecord)
                        <div class="{{ $card }} space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Record') }}</h3>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Created By') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">{{ $changeOrderRecord->createdBy?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Created On') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">{{ $changeOrderRecord->created_at->translatedFormat('d M Y H:i') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Last Updated') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">{{ $changeOrderRecord->updated_at->translatedFormat('d M Y H:i') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Approved By') }}</dt>
                                    <dd class="text-slate-900 dark:text-white">
                                        @if($changeOrderRecord->approved_by)
                                            {{ $changeOrderRecord->approvedBy?->name ?? '—' }}
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $changeOrderRecord->approved_at?->translatedFormat('d M Y H:i') }}</span>
                                        @elseif($changeOrderRecord->isApproved())
                                            <span class="text-slate-500 dark:text-slate-400">{{ __('Approved before approvals were recorded') }}</span>
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    @endif
                </div>

                <!-- The money -->
                <div class="lg:col-span-3 space-y-4">
                    <div class="{{ $card }} space-y-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Billed to the Client') }}</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Adds to the contract value. Use a negative amount for a deductive change.') }}</p>
                            </div>
                        </div>

                        @if($viewing)
                            <p class="text-3xl font-bold {{ (float) ($co_amount ?: 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                {{ $signed($co_amount ?: 0) }}
                            </p>
                        @else
                            <div class="max-w-xs">
                                <label class="{{ $label }}">{{ __('Amount') }} <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" wire:model.live.debounce.500ms="co_amount" placeholder="0.00" class="{{ $field }}">
                                @error('co_amount') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="{{ $card }} space-y-4">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Cost Impact by Cost Code') }}</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    {{ __('What the change does to each cost code\'s budget. It does not have to match the amount billed — the difference is the margin.') }}
                                </p>
                            </div>
                            @unless($viewing)
                                @if(count($coLines) > 1)
                                    <div class="flex items-center gap-3 text-sm">
                                        <button type="button" wire:click="splitChangeOrderEvenly" class="text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('Split evenly') }}</button>
                                        <button type="button" wire:click="clearChangeOrderLines" class="text-slate-500 dark:text-slate-400 hover:underline">{{ __('Clear all') }}</button>
                                    </div>
                                @endif
                            @endunless
                        </div>

                        @error('coLines')
                            <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                                {{ $message }}
                            </div>
                        @enderror

                        @if(count($coLines) > 0)
                            <div class="space-y-2">
                                @foreach($coLines as $index => $line)
                                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3" wire:key="co-line-{{ $index }}-{{ $line['budget_item_id'] }}">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ $line['code_display'] }}</p>
                                                @if($viewing)
                                                    @if($line['description'])
                                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $line['description'] }}</p>
                                                    @endif
                                                @else
                                                    <input
                                                        type="text"
                                                        wire:model="coLines.{{ $index }}.description"
                                                        placeholder="{{ __('What changes on this code (optional)') }}"
                                                        class="mt-1 w-full px-2 py-1 text-xs border border-slate-200 dark:border-slate-700 rounded focus:outline-none focus:ring-1 focus:ring-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400">
                                                    @error('coLines.' . $index . '.description') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                                                @endif
                                            </div>

                                            @if($viewing)
                                                <p class="w-40 shrink-0 text-right text-sm font-semibold {{ (float) $line['amount'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                                    {{ $signed($line['amount']) }}
                                                </p>
                                            @else
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <div class="w-36">
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            wire:model.live.debounce.500ms="coLines.{{ $index }}.amount"
                                                            placeholder="0.00"
                                                            class="w-full px-3 py-2 text-sm text-right border border-slate-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
                                                    </div>
                                                    <button
                                                        type="button"
                                                        wire:click="takeChangeOrderRemainder({{ $index }})"
                                                        title="{{ __('Take the remainder') }}"
                                                        class="px-2 py-2 text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline whitespace-nowrap">
                                                        {{ __('Remainder') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click="removeChangeOrderLine({{ $index }})"
                                                        title="{{ __('Remove line') }}"
                                                        class="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                        @error('coLines.' . $index . '.amount')
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        @elseif($viewing)
                            <div class="rounded-lg border border-dashed border-slate-300 dark:border-slate-600 p-6 text-center">
                                <p class="text-sm font-medium text-slate-900 dark:text-white">{{ __('No cost impact recorded') }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('This change order bills the client but has never been broken down by cost code, so it does not appear in any cost code budget.') }}
                                </p>
                            </div>
                        @endif

                        @unless($viewing)
                            @if($coBudget)
                                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="coLineSearch"
                                        @focus="open = true"
                                        @input="open = true"
                                        placeholder="{{ __('Search a cost code to add...') }}"
                                        class="w-full px-3 py-2 border border-dashed border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400">

                                    @if($coLineSuggestions->count() > 0)
                                        <div x-show="open" x-cloak class="absolute z-20 mt-1 w-full bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 max-h-56 overflow-auto">
                                            @foreach($coLineSuggestions as $suggestion)
                                                <button type="button" wire:click="addChangeOrderLine({{ $suggestion->id }})" @click="open = false" class="w-full px-4 py-2 text-left hover:bg-slate-100 dark:hover:bg-slate-700">
                                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $suggestion->code }} - {{ $suggestion->name }}</div>
                                                    @if($suggestion->parent)
                                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $suggestion->parent->code }} - {{ $suggestion->parent->name }}</div>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif(strlen($coLineSearch) > 0)
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('No cost code matches ":term".', ['term' => $coLineSearch]) }}</p>
                                    @endif
                                </div>
                            @else
                                <div class="rounded-lg border border-dashed border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4">
                                    <p class="text-sm font-medium text-amber-900 dark:text-amber-200">{{ __('This location has no budget yet') }}</p>
                                    <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                                        {{ __('Create a budget for it and the change order can be split across its cost codes. The change order can still be saved — it will bill the client without touching any cost code.') }}
                                    </p>
                                </div>
                            @endif
                        @endunless

                        <!-- Running totals -->
                        <div class="pt-3 border-t border-slate-200 dark:border-slate-700 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                            <div>
                                <p class="text-slate-500 dark:text-slate-400">{{ __('Billed') }}</p>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $signed($co_amount ?: 0) }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500 dark:text-slate-400">{{ __('Cost') }}</p>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ $signed($costTotal) }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500 dark:text-slate-400">{{ __('Margin') }}</p>
                                <p class="font-semibold {{ $margin < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ $money($margin) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-500 dark:text-slate-400">{{ __('Margin %') }}</p>
                                <p class="font-semibold {{ $margin < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ $marginPercent === null ? '—' : number_format($marginPercent, 1) . '%' }}
                                </p>
                            </div>
                        </div>

                        @if(! $viewing && count($coLines) > 0 && abs($margin) >= 0.01)
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $margin > 0
                                    ? __('The cost lines are :amount short of the amount billed — that difference is the margin on this change.', ['amount' => $money(abs($margin))])
                                    : __('The cost lines exceed the amount billed by :amount — this change is being done at a loss.', ['amount' => $money(abs($margin))]) }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 z-20 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    @if($co_status === 'approved')
                        {{ __('Approved — the cost lines revise the budget of each cost code.') }}
                    @else
                        {{ __('Not approved yet — the cost lines are recorded but do not revise any budget.') }}
                    @endif
                </p>
                <div class="flex items-center gap-3 flex-wrap">
                    @if($viewing)
                        @if($changeOrderRecord && ! $changeOrderRecord->isApproved())
                            <x-ui.button type="button" variant="success" wire:click="approveChangeOrder({{ $editingChangeOrder }})">{{ __('Approve') }}</x-ui.button>
                        @endif
                        @if($changeOrderRecord && $changeOrderRecord->isApproved())
                            <x-ui.button type="button" variant="warning" wire:click="returnChangeOrderToPending({{ $editingChangeOrder }})"
                                wire:confirm="{{ __('This takes the cost lines back out of the budget. Continue?') }}">{{ __('Return to Pending') }}</x-ui.button>
                        @endif
                        @if($changeOrderRecord && ! $changeOrderRecord->isRejected())
                            <x-ui.button type="button" variant="danger" wire:click="rejectChangeOrder({{ $editingChangeOrder }})"
                                wire:confirm="{{ __('Reject this change order?') }}">{{ __('Reject') }}</x-ui.button>
                        @endif
                        <x-ui.button type="button" variant="outline" wire:click="openChangeOrderEditModal({{ $editingChangeOrder }})" icon="edit">{{ __('Edit') }}</x-ui.button>
                        <x-ui.button type="button" variant="secondary" wire:click="closeChangeOrderModal">{{ __('Close') }}</x-ui.button>
                    @else
                        <x-ui.button type="button" variant="secondary" wire:click="closeChangeOrderModal">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon="save">
                            {{ $changeOrderModalMode === 'edit' ? __('Save Changes') : __('Create Change Order') }}
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </div>
    </form>
</x-ui.modal>
