@php
    $input = 'px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white';
@endphp

<div>
    <x-ui.breadcrumb :items="[
        ['label' => __('Projects'), 'url' => route('projects.index')],
        ['label' => $project->project_name, 'url' => route('projects.overview', $project)],
        ['label' => __('Approvals'), 'url' => route('projects.approvals', $project)],
        ['label' => __('collaboration.label.generate_budget')],
    ]" />

    <div class="max-w-6xl space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-5">
            <h1 class="font-semibold text-slate-900 dark:text-white">{{ __('Generate approvals from the budget') }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('collaboration.help.lines_ticked_where_signal_suggests') }}
            </p>

            <div class="mt-4 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('collaboration.label.value_threshold') }}</label>
                    <div class="mt-1 flex gap-2">
                        <input type="number" step="0.01" min="0" wire:model="threshold" class="{{ $input }}"
                            placeholder="{{ __('collaboration.label.threshold') }}">
                        <x-ui.button variant="secondary" type="button" wire:click="applyThreshold">{{ __('Apply') }}</x-ui.button>
                    </div>
                    @error('threshold') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('collaboration.help.value_alone_crude_filter_biggest') }}
                    </p>
                </div>
            </div>
        </div>

        @if($candidates->isEmpty())
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-10 text-center">
                <p class="font-medium text-slate-900 dark:text-white">{{ __('collaboration.message.project_budget_lines') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('collaboration.help.build_budget_first_screen_works') }}
                </p>
                <div class="mt-4">
                    <x-ui.button variant="secondary" size="sm" :href="route('projects.budget', $project)">{{ __('collaboration.label.go_budget') }}</x-ui.button>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        {{ trans_choice('collaboration.count.line_ticked_lines_ticked', $selectedCount, ['count' => $selectedCount]) }}
                        <span class="text-slate-400 dark:text-slate-500">
                            · {{ trans_choice('collaboration.count.suggested_suggested', $suggestedCount, ['count' => $suggestedCount]) }}
                            @if($coveredCount > 0)
                                · {{ trans_choice('collaboration.count.already_covered_already_covered', $coveredCount, ['count' => $coveredCount]) }}
                            @endif
                        </span>
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="selectAll">{{ __('collaboration.label.select_all') }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="selectNone">{{ __('collaboration.label.select_none') }}</x-ui.button>
                        <x-ui.button variant="ghost" size="sm" type="button" wire:click="resetToSuggested">{{ __('collaboration.label.back_suggested') }}</x-ui.button>
                    </div>
                </div>

                @error('selected') <p class="px-5 pt-3 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-4 py-3 w-10"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.label.budget_line') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.label.budgeted') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('collaboration.label.why') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Type') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($candidates as $row)
                                @php $item = $row['item']; @endphp
                                <tr wire:key="cand-{{ $item->id }}" class="{{ $row['existing'] ? 'opacity-60' : '' }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" wire:model="selected.{{ $item->id }}"
                                            @disabled($row['existing'])
                                            class="rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189] dark:bg-slate-700 disabled:opacity-40">
                                    </td>

                                    <td class="px-4 py-3">
                                        <p class="text-sm text-slate-900 dark:text-white">
                                            <span class="font-mono text-slate-500 dark:text-slate-400">{{ $item->code }}</span>
                                            {{ $item->name }}
                                        </p>
                                        @if($row['existing'])
                                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                                {{ __('collaboration.label.already_covered', ['number' => $row['existing']->number]) }}
                                            </p>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <x-ui.money :amount="$item->budgeted_amount" :scope="$project" rollup />
                                    </td>

                                    <td class="px-4 py-3 text-xs">
                                        @if(in_array('flagged', $row['reasons'], true))
                                            <span class="inline-flex px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">{{ __('collaboration.label.marked_needing_approval') }}</span>
                                        @endif
                                        @if(in_array('threshold', $row['reasons'], true))
                                            <span class="inline-flex px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ __('collaboration.label.above_threshold') }}</span>
                                        @endif
                                        @if($row['reasons'] === [])
                                            <span class="text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <select wire:model="types.{{ $item->id }}" @disabled($row['existing'])
                                            class="{{ $input }} text-sm disabled:opacity-40">
                                            @foreach($typeOptions as $value => $text)
                                                <option value="{{ $value }}">{{ $text }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 pb-8">
                <x-ui.button variant="secondary" :href="route('projects.approvals', $project)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="primary" type="button" wire:click="generate" icon="plus">
                    {{ trans_choice('collaboration.count.create_draft_create_drafts', $selectedCount, ['count' => $selectedCount]) }}
                </x-ui.button>
            </div>
        @endif
    </div>
</div>
