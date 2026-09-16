@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
    $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2';
    $err = 'text-sm text-red-600 dark:text-red-400';
    $canMaintain = auth()->user()->can('equipment.maintain') && ! $equipment->isRetired();
    $ordered = $readings->sortBy(fn ($r) => $r->read_at->format('Y-m-d').'-'.str_pad($r->id, 10, '0', STR_PAD_LEFT))->values();
    $first = $ordered->first(); $last = $ordered->last();
    $span = ($first && $last && $first->id !== $last->id) ? max(1, (int) $first->read_at->diffInDays($last->read_at)) : null;
    $perDay = $span ? ((float) $last->reading - (float) $first->reading) / $span : null;
@endphp
<div class="space-y-6">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $equipment->meterLabel() }}</h3>
                @if($equipment->hasMeter())
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $equipment->formatMeter($equipment->current_meter !== null ? (float) $equipment->current_meter : null) }}</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        @if($equipment->current_meter_at){{ __('Read on :date', ['date' => $equipment->current_meter_at->appDate()]) }}@else{{ __('No reading yet') }}@endif
                        @if($perDay !== null) &bull; {{ __('about :amount per day, :month per month', ['amount' => $equipment->formatMeter(round($perDay, 1)), 'month' => $equipment->formatMeter(round($perDay * 30, 1))]) }}@endif
                    </p>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('This equipment has no meter. Give it one on the edit screen to schedule maintenance by distance or hours.') }}</p>
                @endif
            </div>
            @if($canMaintain && $equipment->hasMeter())
                <x-ui.button variant="primary" icon="plus" wire:click="startReading">{{ __('Log a reading') }}</x-ui.button>
            @endif
        </div>
    </div>

    @if($equipment->hasMeter())
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            @if($readings->isEmpty())
                <p class="p-6 text-sm text-slate-500 dark:text-slate-400">{{ __('No reading logged yet. Meter-based maintenance needs one to know where it stands.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="{{ $th }}">{{ __('Date') }}</th>
                                <th scope="col" class="{{ $th }} text-right">{{ __('Reading') }}</th>
                                <th scope="col" class="{{ $th }} text-right">{{ __('Since previous') }}</th>
                                <th scope="col" class="{{ $th }}">{{ __('Source') }}</th>
                                <th scope="col" class="{{ $th }}">{{ __('Logged by') }}</th>
                                <th scope="col" class="{{ $th }}">{{ __('Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($readings as $r)
                                @php $idx = $ordered->search(fn ($x) => $x->id === $r->id); $prev = $idx > 0 ? $ordered[$idx - 1] : null; @endphp
                                <tr wire:key="reading-{{ $r->id }}">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $r->read_at->appDate() }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-white">{{ $equipment->formatMeter((float) $r->reading) }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-500 dark:text-slate-400">{{ $prev ? '+'.$equipment->formatMeter(round((float) $r->reading - (float) $prev->reading, 1)) : '—' }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $r->getSourceLabel() }}@if($r->maintenance) &bull; {{ $r->maintenance->title }}@endif</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $r->recordedBy?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $r->notes ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if($showReadingModal)
        <x-ui.modal name="reading-modal" :show="true" maxWidth="lg">
            <form wire:submit="saveReading" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Log a reading') }}</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('A reading never goes backwards. Back-dating is fine as long as it sits between its neighbours.') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="reading-value" class="{{ $label }}">{{ $equipment->meterLabel() }} ({{ $equipment->meterUnit() }}) <span class="text-red-500">*</span></label>
                        <input id="reading-value" type="number" step="0.1" min="0" inputmode="decimal" wire:model="reading_value" class="{{ $field }}">
                        @error('reading_value') <span class="{{ $err }}">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="reading-date" class="{{ $label }}">{{ __('Date') }} <span class="text-red-500">*</span></label>
                        <x-ui.date-input id="reading-date" wire:model="reading_date" class="{{ $field }}" />
                        @error('reading_date') <span class="{{ $err }}">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div>
                    <label for="reading-notes" class="{{ $label }}">{{ __('Notes') }}</label>
                    <input id="reading-notes" type="text" wire:model="reading_notes" maxlength="255" class="{{ $field }}">
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelReading" icon="x">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="save">{{ __('Log reading') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
