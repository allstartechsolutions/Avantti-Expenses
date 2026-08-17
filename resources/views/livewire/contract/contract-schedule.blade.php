<div>
    @php
        $badgeColors = [
            'gray' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            'amber' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'red' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
        ];
        $inputClasses = 'w-full px-2 py-1.5 text-sm border border-slate-300 dark:border-slate-600 rounded-md focus:outline-none focus:ring-1 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white disabled:bg-slate-100 disabled:text-slate-400 dark:disabled:bg-slate-800 dark:disabled:text-slate-500';
    @endphp

    <!-- Payment Schedule Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Payment Schedule') }}</h3>
            <div class="flex items-center space-x-2">
                <x-ui.button variant="secondary" size="sm" wire:click="openHistoryModal" icon="clock">
                    {{ __('History') }}
                </x-ui.button>
                <x-ui.button variant="primary" size="sm" wire:click="openGrid" icon="edit">
                    {{ __('Edit Schedule') }}
                </x-ui.button>
            </div>
        </div>

        <div class="p-6">
            @if($items->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">#</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Description') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Trigger') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Scheduled') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Settled') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Balance') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Status') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($items as $index => $item)
                                <tr wire:key="schedule-item-{{ $item->id }}">
                                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $index + 1 }}</td>
                                    <td class="px-3 py-3 text-sm text-slate-900 dark:text-white">
                                        {{ $item->description }}
                                        @if($item->budgetItem)
                                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-mono font-medium rounded bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $item->budgetItem->code }}</span>
                                        @endif
                                        @if($item->notes)
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-xs">{{ $item->notes }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                        @if($item->trigger_type === 'date')
                                            {{ __('Fixed date') }}: {{ $item->due_date?->format('M d, Y') }}
                                        @else
                                            {{ __('Milestone') }}
                                            @if($item->due_date)
                                                <span class="text-xs text-slate-400">({{ __('expected') }} {{ $item->due_date->format('M d, Y') }})</span>
                                            @endif
                                        @endif
                                        @if($item->isReleased())
                                            <p class="text-xs text-green-600 dark:text-green-400 mt-0.5">
                                                {{ __('Released') }} {{ $item->released_at->format('M d, Y') }}
                                                @if($item->releasedBy) · {{ $item->releasedBy->name }} @endif
                                            </p>
                                        @endif
                                        @if($item->isDelayed())
                                            <p class="text-xs font-medium text-red-600 dark:text-red-400 mt-0.5">
                                                {{ __('Late by :days day(s)', ['days' => $item->getDelayDays()]) }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm font-medium text-right whitespace-nowrap text-slate-900 dark:text-white">
                                        {{ Number::currency($item->getScheduledAmount(), config('app.currency'), config('app.locale')) }}
                                        @if($item->isPercentBased())
                                            <p class="text-xs font-normal text-slate-400">{{ number_format((float) $item->percent, 2) }}%</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap text-green-600 dark:text-green-400">
                                        {{ Number::currency($item->getSettledAmount(), config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ Number::currency($item->getBalance(), config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td class="px-3 py-3 text-sm text-center whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeColors[$item->getStatusColor()] ?? $badgeColors['gray'] }}">
                                            {{ $item->getStatusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-right whitespace-nowrap">
                                        @if(!$item->isReleased())
                                            <x-ui.icon-button
                                                variant="success"
                                                size="sm"
                                                icon="check"
                                                wire:click="openReleaseModal({{ $item->id }})"
                                                title="{{ $item->trigger_type === 'milestone' ? __('Confirm stage completed') : __('Approve installment for payment') }}" />
                                        @elseif($item->payments->isEmpty() && $item->measurements->isEmpty())
                                            {{-- A mistaken approval can be undone while nothing is settled against it. --}}
                                            <x-ui.icon-button
                                                variant="warning"
                                                size="sm"
                                                icon="undo"
                                                wire:click="revertRelease({{ $item->id }})"
                                                wire:confirm="{{ __('Revert the approval of this installment? It goes back to pending and can no longer be paid.') }}"
                                                title="{{ __('Revert approval') }}" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-300 dark:border-slate-600">
                                <td colspan="3" class="px-3 py-3 text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ __('Total Scheduled') }}
                                </td>
                                <td class="px-3 py-3 text-sm font-bold text-right whitespace-nowrap text-slate-900 dark:text-white">
                                    {{ Number::currency($scheduledTotal, config('app.currency'), config('app.locale')) }}
                                </td>
                                <td colspan="4"></td>
                            </tr>
                            @if(abs($unscheduledAmount) >= 0.01)
                                <tr>
                                    <td colspan="3" class="px-3 py-2 text-sm font-medium text-yellow-700 dark:text-yellow-400">
                                        {{ __('Unscheduled Balance') }}
                                        <span class="text-xs font-normal text-slate-400">({{ __('Contract total') }}: {{ Number::currency($adjustedAmount, config('app.currency'), config('app.locale')) }})</span>
                                    </td>
                                    <td class="px-3 py-2 text-sm font-semibold text-right whitespace-nowrap text-yellow-700 dark:text-yellow-400">
                                        {{ Number::currency($unscheduledAmount, config('app.currency'), config('app.locale')) }}
                                    </td>
                                    <td colspan="4"></td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">{{ __('No installments scheduled.') }}</p>
            @endif
        </div>
    </div>

    <!-- Full-screen Grid Editor -->
    @if($showGrid)
    <div class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80"></div>
        <div class="fixed inset-2 md:inset-6 flex flex-col bg-white dark:bg-slate-800 rounded-lg shadow-xl">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-xl font-semibold text-slate-900 dark:text-white">
                    {{ __('Payment Schedule') }} — {{ $contract->contract_number }}
                </h2>
                <button type="button" wire:click="closeGrid" class="text-slate-400 hover:text-slate-600">
                    <x-ui.icon name="x" class="w-6 h-6" />
                </button>
            </div>

            <!-- Grid body -->
            <div class="flex-1 overflow-auto px-4 py-3">
                <table class="min-w-full">
                    <thead class="sticky top-0 bg-white dark:bg-slate-800 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-16">#</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase min-w-[220px]">{{ __('Description') }} *</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-40">{{ __('Trigger') }} *</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-44">{{ __('Due / Expected Date') }}</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase min-w-[180px]">{{ __('Cost Code') }}</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-36">{{ __('Value Type') }}</th>
                            <th class="px-2 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-32">{{ __('Value') }} *</th>
                            <th class="px-2 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase w-32">{{ __('Scheduled') }}</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase min-w-[160px]">{{ __('Notes') }}</th>
                            <th class="px-2 py-2 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($rows as $i => $row)
                            <tr wire:key="grid-row-{{ $i }}-{{ $row['id'] ?? 'new' }}" class="align-top {{ $row['locked'] ? 'bg-slate-50 dark:bg-slate-800/60' : '' }}">
                                <td class="px-2 py-2 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    <div class="flex items-center space-x-0.5">
                                        <span class="w-5">{{ $i + 1 }}</span>
                                        <div class="flex flex-col">
                                            @if($i > 0)
                                                <x-ui.icon-button variant="ghost" size="sm" icon="chevron-up" wire:click="moveRow({{ $i }}, 'up')" class="!p-0.5" title="{{ __('Move up') }}" />
                                            @endif
                                            @if($i < count($rows) - 1)
                                                <x-ui.icon-button variant="ghost" size="sm" icon="chevron-down" wire:click="moveRow({{ $i }}, 'down')" class="!p-0.5" title="{{ __('Move down') }}" />
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="rows.{{ $i }}.description" class="{{ $inputClasses }}" placeholder="{{ __('e.g. Down payment, Foundation completed') }}">
                                    @error("rows.{$i}.description") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model.live="rows.{{ $i }}.trigger_type" class="{{ $inputClasses }}" @disabled($row['locked'])>
                                        <option value="milestone">{{ __('Milestone') }}</option>
                                        <option value="date">{{ __('Fixed date') }}</option>
                                    </select>
                                    @error("rows.{$i}.trigger_type") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <input type="date" wire:model="rows.{{ $i }}.due_date" class="{{ $inputClasses }}">
                                    <p class="mt-0.5 text-[10px] text-slate-400">{{ $row['trigger_type'] === 'date' ? __('Due Date') : __('Expected Date') }}</p>
                                    @error("rows.{$i}.due_date") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model="rows.{{ $i }}.budget_item_id" class="{{ $inputClasses }}">
                                        <option value="">{{ __('No specific cost code') }}</option>
                                        @foreach($budgetItems as $budgetItem)
                                            <option value="{{ $budgetItem->id }}">{{ $budgetItem->parent_id ? '— ' : '' }}{{ $budgetItem->code }} - {{ $budgetItem->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("rows.{$i}.budget_item_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model.live="rows.{{ $i }}.value_type" class="{{ $inputClasses }}" @disabled($row['locked'])>
                                        <option value="amount">{{ __('Fixed amount') }}</option>
                                        <option value="percent">{{ __('% of contract') }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-2">
                                    @if($row['value_type'] === 'percent')
                                        <div class="relative">
                                            <input type="number" step="0.01" min="0.01" max="100" wire:model.live.debounce.400ms="rows.{{ $i }}.percent" class="{{ $inputClasses }} pr-6 text-right" placeholder="0.00" @disabled($row['locked'])>
                                            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-slate-400">%</span>
                                        </div>
                                        @error("rows.{$i}.percent") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @else
                                        <input type="number" step="0.01" min="0.01" wire:model.live.debounce.400ms="rows.{{ $i }}.amount" class="{{ $inputClasses }} text-right" placeholder="0.00" @disabled($row['locked'])>
                                        @error("rows.{$i}.amount") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-sm text-right whitespace-nowrap text-slate-600 dark:text-slate-300 pt-3.5">
                                    {{ Number::currency($this->rowScheduledAmount($row, $adjustedAmount), config('app.currency'), config('app.locale')) }}
                                </td>
                                <td class="px-2 py-2">
                                    <input type="text" wire:model="rows.{{ $i }}.notes" class="{{ $inputClasses }}" placeholder="{{ __('Optional notes...') }}">
                                </td>
                                <td class="px-2 py-2 text-right">
                                    @if($row['locked'])
                                        <span class="text-[10px] text-slate-400 whitespace-nowrap" title="{{ __('This installment has payments or measurements linked to it and cannot be deleted.') }}">{{ __('locked') }}</span>
                                    @else
                                        <x-ui.icon-button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            wire:click="removeRow({{ $i }})"
                                            wire:confirm="{{ __('Are you sure you want to delete this installment?') }}"
                                            title="{{ __('Remove row') }}" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-3">
                    <x-ui.button variant="secondary" size="sm" wire:click="addRow" icon="plus">
                        {{ __('Add Row') }}
                    </x-ui.button>
                </div>
            </div>

            <!-- Footer: live totals + actions -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-sm text-slate-700 dark:text-slate-300">
                    <span class="font-semibold">{{ __('Total Scheduled') }}:</span>
                    {{ Number::currency($this->gridTotals['scheduled'], config('app.currency'), config('app.locale')) }}
                    ({{ number_format($this->gridTotals['percent'], 2) }}%)
                    <span class="mx-2 text-slate-300">|</span>
                    <span class="font-semibold {{ abs($this->gridTotals['unscheduled']) >= 0.01 ? 'text-yellow-700 dark:text-yellow-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ __('Unscheduled Balance') }}: {{ Number::currency($this->gridTotals['unscheduled'], config('app.currency'), config('app.locale')) }}
                    </span>
                    <span class="mx-2 text-slate-300">|</span>
                    <span class="text-xs text-slate-400">{{ __('Contract total') }}: {{ Number::currency($this->gridTotals['adjusted'], config('app.currency'), config('app.locale')) }}</span>
                </div>
                <div class="flex items-center justify-end space-x-3">
                    <x-ui.button type="button" variant="secondary" wire:click="closeGrid">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button type="button" variant="primary" wire:click="saveGrid" icon="save">
                        {{ __('Save Schedule') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Approval Modal (vistoria for eventos, liberação for date parcelas) -->
    @if($showReleaseModal)
    @php
        $isMilestoneRelease = $releasingTrigger === 'milestone';
    @endphp
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeReleaseModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-lg shadow-xl">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white mb-2">
                        {{ $isMilestoneRelease ? __('Confirm stage completed') : __('Approve installment for payment') }}
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        {{ $isMilestoneRelease
                            ? __('This confirms the stage was inspected and completed, releasing the installment for payment. This action is recorded in the history.')
                            : __('This approves the installment and releases it for payment on the contract. This action is recorded in the history.') }}
                    </p>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">{{ __('Notes') }}</label>
                        <textarea
                            wire:model="releaseNotes"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white"
                            placeholder="{{ $isMilestoneRelease ? __('Inspection notes (optional)...') : __('Approval notes (optional)...') }}"></textarea>
                        @error('releaseNotes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-end space-x-4 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button type="button" variant="secondary" wire:click="closeReleaseModal">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="success" wire:click="release" icon="check">
                            {{ $isMilestoneRelease ? __('Confirm stage completed') : __('Approve installment for payment') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Change History Modal -->
    @if($showHistoryModal)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-900/80" wire:click="closeHistoryModal"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-lg shadow-xl max-h-[85vh] flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ __('Change History') }}</h2>
                    <button type="button" wire:click="closeHistoryModal" class="text-slate-400 hover:text-slate-600">
                        <x-ui.icon name="x" class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 overflow-y-auto">
                    @php
                        $fieldLabels = [
                            'description' => __('Description'),
                            'trigger_type' => __('Trigger'),
                            'due_date' => __('Due / Expected Date'),
                            'budget_item_id' => __('Cost Code'),
                            'percent' => '%',
                            'amount' => __('Amount'),
                            'notes' => __('Notes'),
                            'released_at' => __('Released'),
                            'release_notes' => __('Inspection notes'),
                        ];
                    @endphp
                    @forelse($history as $change)
                        <div class="pb-4 mb-4 border-b border-slate-100 dark:border-slate-700/50 last:border-0 last:mb-0 last:pb-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeColors[$change->getActionColor()] ?? $badgeColors['gray'] }}">
                                        {{ $change->getActionLabel() }}
                                    </span>
                                    <span class="text-sm font-medium text-slate-900 dark:text-white">{{ $change->item_description }}</span>
                                </div>
                                <span class="text-xs text-slate-400 whitespace-nowrap">
                                    {{ $change->created_at->format('M d, Y H:i') }} · {{ $change->changedBy?->name ?? __('Unknown') }}
                                </span>
                            </div>
                            @if($change->changes)
                                <div class="mt-2 space-y-0.5">
                                    @foreach($change->changes as $field => $value)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            <span class="font-medium">{{ $fieldLabels[$field] ?? $field }}:</span>
                                            @if(is_array($value) && array_key_exists('old', $value))
                                                <span class="line-through text-slate-400">{{ $value['old'] ?? '—' }}</span>
                                                <span class="mx-1">→</span>
                                                <span class="text-slate-700 dark:text-slate-300">{{ $value['new'] ?? '—' }}</span>
                                            @else
                                                <span class="text-slate-700 dark:text-slate-300">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                            @endif
                                        </p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">{{ __('No changes recorded yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
