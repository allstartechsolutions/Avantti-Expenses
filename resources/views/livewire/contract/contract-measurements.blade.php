<div>
    @php
        $badgeColors = [
            'gray' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            'amber' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'red' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
        ];
        $inputClasses = 'w-full px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-1 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white disabled:bg-slate-100 disabled:text-slate-400 dark:disabled:bg-slate-800 dark:disabled:text-slate-500';
    @endphp

    <!-- Measurements Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Measurements') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Work executed per cost code in a period (boletim de medição).') }}</p>
            </div>
            <x-ui.button variant="primary" size="sm" wire:click="createDraft" icon="plus">
                {{ __('New Measurement') }}
            </x-ui.button>
        </div>

        <div class="p-6">
            @if($measurements->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">#</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Period') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Gross') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Retention') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Net') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Paid') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Status') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($measurements as $measurement)
                                <tr wire:key="measurement-{{ $measurement->id }}">
                                    <td class="px-3 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $measurement->measurement_number }}</td>
                                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                        {{ $measurement->period_start->format('M d, Y') }} — {{ $measurement->period_end->format('M d, Y') }}
                                        @if($measurement->scheduleItem)
                                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400">
                                                {{ $measurement->scheduleItem->description }}
                                            </span>
                                        @endif
                                        @if($measurement->approvedBy)
                                            <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">
                                                {{ __('Approved') }} {{ $measurement->approved_at?->format('M d, Y') }} · {{ $measurement->approvedBy->name }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap text-slate-900 dark:text-white">
                                        {{ Number::currency($measurement->gross_amount, config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap text-orange-600 dark:text-orange-400">
                                        {{ Number::currency($measurement->retention_amount, config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap font-medium text-slate-900 dark:text-white">
                                        {{ Number::currency($measurement->net_amount, config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap text-green-600 dark:text-green-400">
                                        {{ Number::currency($measurement->getAmountPaid(), config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeColors[$measurement->getStatusColor()] ?? $badgeColors['gray'] }}">
                                            {{ $measurement->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end space-x-1">
                                            <x-ui.icon-button
                                                variant="secondary"
                                                size="sm"
                                                icon="printer"
                                                href="{{ route('measurements.pdf.view', $measurement->id) }}"
                                                target="_blank"
                                                title="{{ __('Print measurement report') }}" />
                                            <x-ui.icon-button
                                                variant="secondary"
                                                size="sm"
                                                icon="eye"
                                                wire:click="openEditor({{ $measurement->id }})"
                                                title="{{ $measurement->isDraft() ? __('Edit measurement') : __('View measurement') }}" />
                                            @if($measurement->isDraft())
                                                <x-ui.icon-button
                                                    variant="success"
                                                    size="sm"
                                                    icon="check"
                                                    wire:click="approve({{ $measurement->id }})"
                                                    wire:confirm="{{ __('Approve this measurement? Its values are locked once approved.') }}"
                                                    title="{{ __('Approve measurement') }}" />
                                                <x-ui.icon-button
                                                    variant="danger"
                                                    size="sm"
                                                    icon="trash"
                                                    wire:click="deleteDraft({{ $measurement->id }})"
                                                    wire:confirm="{{ __('Delete this draft measurement?') }}"
                                                    title="{{ __('Delete draft') }}" />
                                            @elseif($measurement->isApproved() && $measurement->getRemainingNet() > 0.009)
                                                <x-ui.icon-button
                                                    variant="primary"
                                                    size="sm"
                                                    icon="banknotes"
                                                    wire:click="payMeasurement({{ $measurement->id }})"
                                                    title="{{ __('Record payment for this measurement') }}" />
                                            @endif
                                            @if($measurement->isApproved() && $measurement->payments->isEmpty())
                                                <x-ui.icon-button
                                                    variant="warning"
                                                    size="sm"
                                                    icon="undo"
                                                    wire:click="cancelMeasurement({{ $measurement->id }})"
                                                    wire:confirm="{{ __('Cancel this measurement? The measured period stops counting toward the contract.') }}"
                                                    title="{{ __('Cancel measurement') }}" />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('No measurements yet. Use "New Measurement" to open a boletim for the current period.') }}
                </p>
            @endif
        </div>
    </div>

    <!-- Boletim editor -->
    @if($showEditor)
    <div class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80"></div>
        <div class="fixed inset-2 md:inset-6 flex flex-col bg-white dark:bg-slate-800 rounded-lg shadow-xl">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                    {{ __('Measurement') }} #{{ $editingNumber }} — {{ $contract->contract_number }}
                    @if($readOnly)
                        <span class="ml-2 text-xs font-normal text-slate-500 dark:text-slate-400">{{ __('(read-only)') }}</span>
                    @endif
                </h2>
                <button type="button" wire:click="closeEditor" class="text-slate-400 hover:text-slate-600">
                    <x-ui.icon name="x" class="w-6 h-6" />
                </button>
            </div>

            <!-- Period -->
            <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 grid grid-cols-1 md:grid-cols-{{ $scheduleItems->count() > 0 ? '4' : '3' }} gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Period Start') }} *</label>
                    <input type="date" wire:model="periodStart" @disabled($readOnly) class="{{ $inputClasses }}">
                    @error('periodStart') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Period End') }} *</label>
                    <input type="date" wire:model="periodEnd" @disabled($readOnly) class="{{ $inputClasses }}">
                    @error('periodEnd') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                @if($scheduleItems->count() > 0)
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Installment') }}</label>
                        <select wire:model.live="scheduleItemId" @disabled($readOnly) class="{{ $inputClasses }}">
                            <option value="">{{ __('Not tied to an installment') }}</option>
                            @foreach($scheduleItems as $scheduleItem)
                                <option value="{{ $scheduleItem->id }}">
                                    {{ $scheduleItem->description }}
                                    &middot; {{ __('Scheduled') }} {{ Number::currency($scheduleItem->getScheduledAmount(), config('app.currency'), config('app.locale')) }}
                                    &middot; {{ __('Balance') }} {{ Number::currency($scheduleItem->getBalance(), config('app.currency'), config('app.locale')) }}
                                    @if($scheduleItem->due_date)
                                        &middot; {{ $scheduleItem->due_date->format('d/m/Y') }}
                                    @endif
                                    &middot; {{ $scheduleItem->getStatusLabel() }}
                                </option>
                            @endforeach
                        </select>
                        @error('scheduleItemId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Notes') }}</label>
                    <input type="text" wire:model="notes" @disabled($readOnly) class="{{ $inputClasses }}" placeholder="{{ __('Optional notes...') }}">
                </div>
            </div>

            <!-- Payment schedule context -->
            @if($scheduleItems->count() > 0)
                @php
                    $selected = $this->selectedScheduleItem;
                    $summary = $this->scheduleSummary;
                    $overMeasured = $selected && $this->editorTotals['gross'] > $selected->getBalance() + 0.009;
                @endphp
                <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
                    @if($selected)
                        <div class="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Installment') }}</span>
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $selected->description }}</span>
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeColors[$selected->getStatusColor()] ?? $badgeColors['gray'] }}">
                                    {{ $selected->getStatusLabel() }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    {{ $selected->trigger_type === 'date' ? __('Due Date') : __('Expected Date') }}
                                </span>
                                <span class="font-medium text-slate-900 dark:text-white">
                                    {{ $selected->due_date?->format('d/m/Y') ?? '—' }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Scheduled') }}</span>
                                <span class="font-medium text-slate-900 dark:text-white">{{ Number::currency($selected->getScheduledAmount(), config('app.currency'), config('app.locale')) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Settled') }}</span>
                                <span class="font-medium text-green-600 dark:text-green-400">{{ Number::currency($selected->getSettledAmount(), config('app.currency'), config('app.locale')) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Balance') }}</span>
                                <span class="font-semibold text-amber-600 dark:text-amber-400">{{ Number::currency($selected->getBalance(), config('app.currency'), config('app.locale')) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('This measurement (gross)') }}</span>
                                <span class="font-semibold {{ $overMeasured ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white' }}">
                                    {{ Number::currency($this->editorTotals['gross'], config('app.currency'), config('app.locale')) }}
                                </span>
                            </div>
                        </div>
                        @if($overMeasured)
                            <p class="mt-2 text-xs text-red-600 dark:text-red-400">
                                {{ __('This measurement is larger than what the installment still owes — it will settle the installment and the excess stays on the contract.') }}
                            </p>
                        @endif
                    @else
                        <div class="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Payment Schedule') }}:</span>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Total Scheduled') }}</span>
                                <span class="font-medium text-slate-900 dark:text-white">{{ Number::currency($summary['scheduled'], config('app.currency'), config('app.locale')) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Settled') }}</span>
                                <span class="font-medium text-green-600 dark:text-green-400">{{ Number::currency($summary['settled'], config('app.currency'), config('app.locale')) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Balance') }}</span>
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ Number::currency($summary['balance'], config('app.currency'), config('app.locale')) }}</span>
                            </div>
                            @if(abs($summary['unscheduled']) >= 0.01)
                                <div>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Unscheduled Balance') }}</span>
                                    <span class="font-medium text-yellow-700 dark:text-yellow-400">{{ Number::currency($summary['unscheduled'], config('app.currency'), config('app.locale')) }}</span>
                                </div>
                            @endif
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Pick an installment above to measure against it.') }}</span>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Boletim body -->
            <div class="flex-1 overflow-auto px-4 py-3">
                <table class="min-w-full">
                    <thead class="sticky top-0 bg-white dark:bg-slate-800 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase min-w-[220px]">{{ __('Cost Code') }}</th>
                            <th class="px-2 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-40">{{ __('Scheduled') }}</th>
                            <th class="px-2 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-32">{{ __('Previous %') }}</th>
                            <th class="px-2 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-32">{{ __('Current %') }} *</th>
                            <th class="px-2 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-44">
                                {{ __('Period Amount') }}
                                <span class="block normal-case font-normal text-[10px] text-slate-400">{{ __('type either one') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($rows as $i => $row)
                            <tr wire:key="boletim-row-{{ $row['id'] }}">
                                <td class="px-2 py-2 text-sm text-slate-900 dark:text-white">{{ $row['code_display'] }}</td>
                                <td class="px-2 py-2 text-sm text-right whitespace-nowrap text-slate-600 dark:text-slate-300">
                                    {{ Number::currency($row['scheduled'], config('app.currency'), config('app.locale')) }}
                                </td>
                                <td class="px-2 py-2 text-sm text-right whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ number_format($row['previous_percent'], 2) }}%
                                </td>
                                <td class="px-2 py-2">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        wire:model.live.debounce.500ms="rows.{{ $i }}.current_percent"
                                        @disabled($readOnly)
                                        class="{{ $inputClasses }} text-right">
                                    @error("rows.{$i}.current_percent") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <div class="relative">
                                        <span class="absolute left-2 top-1.5 text-slate-500 text-sm">$</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            wire:model.live.debounce.600ms="rows.{{ $i }}.period_amount"
                                            @disabled($readOnly)
                                            placeholder="0.00"
                                            class="{{ $inputClasses }} pl-6 text-right">
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals + actions -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="flex items-center gap-6 text-sm">
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Gross') }}</span>
                        <span class="font-semibold text-slate-900 dark:text-white">
                            {{ Number::currency($this->editorTotals['gross'], config('app.currency'), config('app.locale')) }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Retention') }} ({{ rtrim(rtrim(number_format($this->editorTotals['retention_percent'], 2, '.', ''), '0'), '.') }}%)
                        </span>
                        <span class="font-semibold text-orange-600 dark:text-orange-400">
                            {{ Number::currency($this->editorTotals['retention'], config('app.currency'), config('app.locale')) }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('Net') }}</span>
                        <span class="font-semibold text-green-600 dark:text-green-400">
                            {{ Number::currency($this->editorTotals['net'], config('app.currency'), config('app.locale')) }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <x-ui.button variant="secondary" wire:click="closeEditor">
                        {{ __('Close') }}
                    </x-ui.button>
                    @unless($readOnly)
                        <x-ui.button variant="primary" wire:click="saveDraft" icon="save">
                            {{ __('Save') }}
                        </x-ui.button>
                        <x-ui.button
                            variant="success"
                            wire:click="saveAndApprove"
                            wire:confirm="{{ __('Approve this measurement? Its values are locked once approved.') }}"
                            icon="check">
                            {{ __('Approve') }}
                        </x-ui.button>
                    @endunless
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
