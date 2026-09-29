{{--
    Contract change history: every edit of the contract's terms, every change
    order added, altered or removed, and every payment taken back out — with
    who, when, the old and new values, and the status move it caused.
--}}
@php
    $historyBadges = [
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'gray' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
    ];
    $historyScope = $contract->jobSite ?? $contract->project;
    $historyEntries = $contract->changeHistories;
@endphp

<div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700" x-data="{ showAll: false }">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Change History') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('Edits to the contract, its change orders and deleted payments.') }}</p>
        </div>
        @if($historyEntries->isNotEmpty())
            <span class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                {{ trans_choice(':count change|:count changes', $historyEntries->count(), ['count' => $historyEntries->count()]) }}
            </span>
        @endif
    </div>

    <div class="p-6">
        @forelse($historyEntries as $entry)
            @php $changes = $entry->changes ?? []; @endphp
            <div wire:key="contract-change-{{ $entry->id }}"
                 class="pb-4 mb-4 border-b border-slate-100 dark:border-slate-700/50 last:border-0 last:mb-0 last:pb-0"
                 @if($loop->index >= 10) x-show="showAll" x-cloak @endif>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $historyBadges[$entry->getActionColor()] ?? $historyBadges['gray'] }}">
                            {{ $entry->getActionLabel() }}
                        </span>
                        @if(isset($changes['status']['new']))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                {{ __('Status changed') }}
                            </span>
                        @endif
                    </div>
                    <span class="text-xs text-slate-500 dark:text-slate-400 sm:whitespace-nowrap">
                        {{ $entry->created_at->appDateTime() }} · {{ $entry->changedBy?->name ?? __('System') }}
                    </span>
                </div>

                <dl class="mt-2 space-y-1">
                    @foreach($changes as $field => $value)
                        <div class="text-sm flex flex-wrap gap-x-1">
                            <dt class="font-medium text-slate-600 dark:text-slate-300">{{ \App\Models\ContractChangeHistory::fieldLabel($field) }}:</dt>
                            <dd class="text-slate-700 dark:text-slate-300 break-words min-w-0">
                                @if($field === 'allocations' && is_array($value) && array_key_exists('old', $value))
                                    @foreach(['old', 'new'] as $side)
                                        <div class="{{ $side === 'old' ? 'line-through text-slate-400' : '' }}">
                                            @forelse($value[$side] ?? [] as $row)
                                                <span class="block">{{ $row['code'] }} — <x-ui.money :amount="$row['amount']" :scope="$historyScope" /></span>
                                            @empty
                                                <span class="block">{{ __('None') }}</span>
                                            @endforelse
                                        </div>
                                    @endforeach
                                @elseif(is_array($value) && array_key_exists('old', $value))
                                    <span class="line-through text-slate-400">
                                        @if(\App\Models\ContractChangeHistory::isMoneyField($field) && $value['old'] !== null)
                                            <x-ui.money :amount="$value['old']" :scope="$historyScope" />
                                        @else
                                            {{ \App\Models\ContractChangeHistory::formatValue($field, $value['old']) }}
                                        @endif
                                    </span>
                                    <span class="mx-1 text-slate-400">&rarr;</span>
                                    <span class="font-medium text-slate-900 dark:text-white">
                                        @if(\App\Models\ContractChangeHistory::isMoneyField($field) && $value['new'] !== null)
                                            <x-ui.money :amount="$value['new']" :scope="$historyScope" />
                                        @else
                                            {{ \App\Models\ContractChangeHistory::formatValue($field, $value['new']) }}
                                        @endif
                                    </span>
                                    @if(\App\Models\ContractChangeHistory::isMoneyField($field) && is_numeric($value['old']) && is_numeric($value['new']))
                                        @php $delta = round($value['new'] - $value['old'], 2); @endphp
                                        <span class="ml-1 text-xs {{ $delta >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            ({{ $delta >= 0 ? '+' : '−' }}<x-ui.money :amount="abs($delta)" :scope="$historyScope" />)
                                        </span>
                                    @endif
                                @elseif(\App\Models\ContractChangeHistory::isMoneyField($field))
                                    <x-ui.money :amount="$value" :scope="$historyScope" />
                                @else
                                    {{ \App\Models\ContractChangeHistory::formatValue($field, $value) }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @empty
            <div class="text-center py-6">
                <x-ui.icon name="clock" class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600" />
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('No changes since the contract was created.') }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('Edits to the amount, dates, change orders and deleted payments will be listed here.') }}</p>
            </div>
        @endforelse

        @if($historyEntries->count() > 10)
            <div class="pt-4 text-center">
                <x-ui.button variant="ghost" size="sm" x-on:click="showAll = !showAll">
                    <span x-show="!showAll">{{ __('Show all :count changes', ['count' => $historyEntries->count()]) }}</span>
                    <span x-show="showAll" x-cloak>{{ __('Show fewer') }}</span>
                </x-ui.button>
            </div>
        @endif
    </div>
</div>
