<div>
    @php
        $field = 'w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3F5189] focus:border-[#3F5189] bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500';
        $label = 'block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1';
        $card = 'bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700';
        $dateFormat = config('app.country') === 'BR' ? 'd/m/Y' : 'm/d/Y';
        $counts = $this->counts;
    @endphp

    <!-- Page header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span class="font-mono text-sm text-slate-400 dark:text-slate-500">{{ $meeting->number }}</span>
                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                    {{ $meeting->getStatusLabel() }}
                </span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Agenda') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $meeting->title }} · {{ $meeting->meeting_date->format($dateFormat) }}
                @if($meeting->series) · {{ $meeting->series->name }} @endif
            </p>
        </div>
        <div class="flex items-center gap-3">
            <x-ui.button variant="secondary" icon="edit" href="{{ route('meetings.edit', $meeting) }}">{{ __('Meeting Details') }}</x-ui.button>
            <x-ui.button variant="primary" href="{{ route('meetings.show', $meeting) }}">{{ __('Run the Meeting') }}</x-ui.button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    {{--
        An earlier minute of this series has not gone out yet. Its figures are
        not frozen until it is published, so anything moved on from this agenda
        changes what that one shows — and what it will keep once published.
    --}}
    @if($this->unpublishedEarlier->isNotEmpty())
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 004.99 19z"/>
                </svg>
                <div class="min-w-0 text-sm text-amber-800 dark:text-amber-300">
                    <p class="font-medium">
                        {{ trans_choice(
                            'An earlier minute of this series has not been published.|:count earlier minutes of this series have not been published.',
                            $this->unpublishedEarlier->count(),
                            ['count' => $this->unpublishedEarlier->count()]
                        ) }}
                    </p>
                    <p class="mt-1">
                        {{ __('Its owners, dates and progress follow the live tasks until it goes out, so anything changed here changes what it shows — and what it keeps once published. Publish it first.') }}
                    </p>
                    <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
                        @foreach($this->unpublishedEarlier as $earlier)
                            <a href="{{ route('meetings.show', $earlier) }}"
                               class="font-mono text-xs underline hover:no-underline">
                                {{ $earlier->number }} · {{ $earlier->meeting_date->format($dateFormat) }}
                            </a>
                        @endforeach
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Where the agenda stands -->
    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        @foreach([
            ['label' => __('On the Agenda'), 'value' => $counts['items'], 'class' => 'text-slate-900 dark:text-white'],
            ['label' => __('Action Items'), 'value' => $counts['actions'], 'class' => 'text-[#3F5189] dark:text-[#4A5A96]'],
            ['label' => __('Carried Forward'), 'value' => $counts['carried'], 'class' => 'text-slate-900 dark:text-white'],
            ['label' => __('Overdue'), 'value' => $counts['overdue'], 'class' => 'text-red-600 dark:text-red-400'],
            ['label' => __('Still Proposed'), 'value' => $counts['proposed'], 'class' => 'text-amber-600 dark:text-amber-400'],
        ] as $stat)
            <div class="{{ $card }} p-4">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold mt-1 {{ $stat['class'] }}">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- The agenda itself -->
        <div class="lg:col-span-2 space-y-6">
            <div class="{{ $card }}">
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('The Agenda') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('In the order it will be taken. Numbering follows the order.') }}
                        </p>
                    </div>
                    <x-ui.button variant="primary" size="sm" icon="plus" wire:click="openItemForm">{{ __('Raise an Item') }}</x-ui.button>
                </div>

                @if($this->items->isNotEmpty())
                    {{--
                        The order is stored, not applied when the page renders,
                        so what is on screen is what the minute and the ata will
                        say. These put it back when dragging has wandered.
                    --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-6 py-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-700/20">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Order') }}</span>

                        <button type="button" wire:click="sortAgenda('last_meeting')"
                                class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                            {{ __("Last meeting's order") }}
                        </button>
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <button type="button" wire:click="sortAgenda('overdue_first')"
                                class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                            {{ __('Past due first') }}
                        </button>

                        @if($this->isInterleaved)
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            <button type="button" wire:click="tidyAgenda"
                                    class="text-xs font-medium text-amber-700 dark:text-amber-400 hover:underline">
                                {{ __('Group by location') }}
                            </button>
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                {{ __('A location is split across the agenda.') }}
                            </span>
                        @elseif($meeting->series)
                            <span class="ml-auto text-xs text-slate-400 dark:text-slate-500">
                                {{ __('This series: :order', ['order' => $meeting->series->getAgendaOrderLabel()]) }}
                            </span>
                        @endif
                    </div>
                @endif

                @if($showItemForm && ! $item_parent_id && ! $editingItemId)
                    @include('livewire.meeting.partials.item-form')
                @endif

                @if($this->items->isEmpty())
                    <div class="px-6 py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <h3 class="mt-4 text-sm font-medium text-slate-900 dark:text-white">{{ __('The agenda is empty') }}</h3>
                        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                            {{ $counts['proposed'] > 0
                                ? trans_choice('There is :count open item waiting to be carried forward — it is proposed on the right.|There are :count open items waiting to be carried forward — they are proposed on the right.', $counts['proposed'], ['count' => $counts['proposed']])
                                : __('Add a project or job site to bring its open items in, or raise something new.') }}
                        </p>
                    </div>
                @else
                    {{--
                        Drag to reorder, with the arrows kept as the way that
                        works with a keyboard and on a phone, where dragging
                        inside a scrolling list fights the scroll.

                        Plain HTML5 drag events — no library. Each location has
                        its own x-data, so a drag only ever collects the rows of
                        its own block: a line cannot change project by being
                        dragged, because its location comes from its task.
                    --}}
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($this->itemBlocks as $blockIndex => $block)
                            <div wire:key="block-{{ $blockIndex }}-{{ $block['key'] }}">
                                <!-- Which project or job site the lines below belong to -->
                                <div class="flex items-center justify-between gap-3 bg-slate-50 px-6 py-2 dark:bg-slate-700/40">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="truncate text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                            {{ $block['label'] }}
                                        </span>
                                        <span class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-600 dark:text-slate-300">
                                            {{ $block['items']->count() }}
                                        </span>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button"
                                                wire:click="moveGroup({{ $block['project_id'] ?? 'null' }}, {{ $block['job_site_id'] ?? 'null' }}, 'up')"
                                                @disabled($blockIndex === 0)
                                                class="rounded p-1 text-slate-400 enabled:hover:bg-slate-200 enabled:hover:text-slate-700 disabled:opacity-30 dark:enabled:hover:bg-slate-600"
                                                title="{{ __('Move this location up') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                            </svg>
                                        </button>
                                        <button type="button"
                                                wire:click="moveGroup({{ $block['project_id'] ?? 'null' }}, {{ $block['job_site_id'] ?? 'null' }}, 'down')"
                                                @disabled($blockIndex === $this->itemBlocks->count() - 1)
                                                class="rounded p-1 text-slate-400 enabled:hover:bg-slate-200 enabled:hover:text-slate-700 disabled:opacity-30 dark:enabled:hover:bg-slate-600"
                                                title="{{ __('Move this location down') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="divide-y divide-slate-200 dark:divide-slate-700"
                                     x-data="{
                                         dragging: null,
                                         over: null,
                                         start(id) { this.dragging = id },
                                         enter(id) { if (this.dragging && id !== this.dragging) this.over = id },
                                         end() { this.dragging = null; this.over = null },
                                         drop(targetId) {
                                             if (! this.dragging || this.dragging === targetId) { this.end(); return }

                                             const rows = [...$el.querySelectorAll('[data-agenda-row]')]
                                                 .map(el => parseInt(el.dataset.agendaRow, 10));

                                             const from = rows.indexOf(this.dragging);
                                             const to = rows.indexOf(targetId);
                                             if (from === -1 || to === -1) { this.end(); return }

                                             rows.splice(to, 0, ...rows.splice(from, 1));
                                             $wire.reorderItems(rows, null);
                                             this.end();
                                         },
                                     }">
                                    @foreach($block['items'] as $index => $item)
                                        @include('livewire.meeting.partials.agenda-item', [
                                            'item' => $item,
                                            'depth' => 0,
                                            'canUp' => $index > 0,
                                            'canDown' => $index < $block['items']->count() - 1,
                                        ])

                                        @if($showItemForm && $editingItemId === $item->id)
                                            @include('livewire.meeting.partials.item-form')
                                        @endif

                                        @foreach($item->children as $childIndex => $child)
                                            @include('livewire.meeting.partials.agenda-item', [
                                                'item' => $child,
                                                'depth' => 1,
                                                'canUp' => $childIndex > 0,
                                                'canDown' => $childIndex < $item->children->count() - 1,
                                            ])

                                            @if($showItemForm && $editingItemId === $child->id)
                                                @include('livewire.meeting.partials.item-form')
                                            @endif
                                        @endforeach

                                        @if($showItemForm && ! $editingItemId && $item_parent_id === $item->id)
                                            @include('livewire.meeting.partials.item-form')
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Open elsewhere, not on this agenda -->
            @if($this->scopeCandidates->isNotEmpty())
                <div class="{{ $card }}">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Open at These Locations') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('What is still open where this meeting is looking, and not yet on the agenda.') }}
                        </p>
                    </div>

                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($this->scopeCandidates as $key => $scope)
                            <div class="px-6 py-4" wire:key="scope-{{ $key }}">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="font-medium text-slate-900 dark:text-white">{{ $scope['label'] }}</p>
                                    @if($scope['tracked']->isNotEmpty())
                                        <x-ui.button variant="secondary" size="sm" wire:click="addAllTracked('{{ $key }}')">
                                            {{ trans_choice('Add :count tracked item|Add all :count tracked items', $scope['tracked']->count(), ['count' => $scope['tracked']->count()]) }}
                                        </x-ui.button>
                                    @endif
                                </div>

                                @foreach($scope['tracked'] as $task)
                                    <div class="mt-2 flex items-center gap-3 rounded-lg bg-slate-50 dark:bg-slate-700/40 px-3 py-2" wire:key="tracked-{{ $task->id }}">
                                        <span class="font-mono text-xs text-slate-400">{{ $task->code() }}</span>
                                        <span class="min-w-0 flex-1 truncate text-sm text-slate-700 dark:text-slate-200">{{ $task->title }}</span>
                                        <x-task-status-badge :task="$task" />
                                        <button type="button" wire:click="addTaskToAgenda({{ $task->id }})"
                                                class="shrink-0 text-xs font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                            {{ __('Add') }}
                                        </button>
                                    </div>
                                @endforeach

                                @if($scope['direct']->isNotEmpty())
                                    <button type="button" wire:click="toggleDrawer('{{ $key }}')"
                                            class="mt-3 inline-flex items-center gap-1 text-xs text-slate-500 hover:text-[#3F5189] dark:text-slate-400 dark:hover:text-[#4A5A96]">
                                        <svg class="w-3.5 h-3.5 transition-transform {{ in_array($key, $openDrawers, true) ? 'rotate-90' : '' }}"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                        {{ trans_choice(
                                            ':count other open task here is not on the agenda|:count other open tasks here are not on the agenda',
                                            $scope['direct']->count(), ['count' => $scope['direct']->count()]) }}
                                    </button>

                                    @if(in_array($key, $openDrawers, true))
                                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                            {{ __('No meeting has ever discussed these. They stay off the minute unless you put one on it.') }}
                                        </p>
                                        <div class="mt-2 space-y-1">
                                            @foreach($scope['direct'] as $task)
                                                <div class="flex items-center gap-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 px-3 py-2" wire:key="direct-{{ $task->id }}">
                                                    <span class="font-mono text-xs text-slate-400">{{ $task->code() }}</span>
                                                    <span class="min-w-0 flex-1 truncate text-sm text-slate-600 dark:text-slate-300">{{ $task->title }}</span>
                                                    <span class="shrink-0 text-xs text-slate-400">{{ $task->owner?->name }}</span>
                                                    @if($task->due_date)
                                                        <span class="shrink-0 text-xs {{ $task->isOverdue() ? 'text-red-600 dark:text-red-400 font-medium' : 'text-slate-400' }}">
                                                            {{ $task->due_date->format($dateFormat) }}
                                                        </span>
                                                    @endif
                                                    <button type="button" wire:click="addTaskToAgenda({{ $task->id }})"
                                                            class="shrink-0 text-xs font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                                        {{ __('Add to agenda') }}
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- What is proposed -->
        <div class="space-y-6">
            <div class="{{ $card }}">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Carried Forward') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        @if($meeting->series)
                            {{ __('Still open from earlier meetings of :series.', ['series' => $meeting->series->name]) }}
                        @else
                            {{ __('Still open from the meeting this one follows.') }}
                        @endif
                    </p>
                </div>

                @if($this->carryForward->isEmpty())
                    <div class="px-6 py-8 text-center">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ $meeting->series
                                ? __('Nothing open from earlier meetings of this series.')
                                : __('A one-off meeting carries nothing forward on its own.') }}
                        </p>
                    </div>
                @else
                    <div class="px-6 py-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="selectAllCarry" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('All') }}</button>
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <button type="button" wire:click="selectOverdueCarry" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('Only overdue') }}</button>
                        <span class="text-slate-300 dark:text-slate-600">·</span>
                        <button type="button" wire:click="selectNoCarry" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('None') }}</button>
                    </div>

                    <div class="max-h-[32rem] overflow-y-auto divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach($this->carryForwardByScope as $scopeLabel => $tasks)
                            <div class="px-6 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $scopeLabel }}</p>

                                @foreach($tasks as $task)
                                    @php $history = app(App\Services\MeetingAgendaService::class)->history($task); @endphp
                                    <label class="mt-2 flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-slate-50 dark:hover:bg-slate-700/40" wire:key="carry-{{ $task->id }}">
                                        <input type="checkbox"
                                               wire:click="toggleCarry({{ $task->id }})"
                                               @checked(in_array($task->id, $carrySelected, true))
                                               class="mt-1 rounded border-slate-300 dark:border-slate-600 text-[#3F5189] focus:ring-[#3F5189]">

                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="font-mono text-xs text-slate-400">{{ $task->code() }}</span>
                                                <span class="text-sm text-slate-800 dark:text-slate-100">{{ $task->title }}</span>
                                                <x-task-status-badge :task="$task" />
                                            </span>

                                            <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                                <span>{{ $task->owner?->name }}</span>
                                                @if($task->due_date)
                                                    <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">
                                                        {{ $task->due_date->format($dateFormat) }}
                                                        @if($task->isOverdue())
                                                            · {{ trans_choice(':count day late|:count days late', $task->daysOverdue(), ['count' => $task->daysOverdue()]) }}
                                                        @endif
                                                    </span>
                                                @endif
                                                <span>{{ $task->progress }}%</span>
                                            </span>

                                            @if($history['first_meeting'])
                                                <span class="mt-1 block text-xs text-slate-400 dark:text-slate-500">
                                                    {{ __('open since :number', ['number' => $history['first_meeting']]) }}
                                                    · {{ trans_choice(':count meeting|:count meetings', $history['meetings'], ['count' => $history['meetings']]) }}
                                                </span>
                                            @endif

                                            @if($task->notes->isNotEmpty())
                                                <span class="mt-1 block truncate text-xs italic text-slate-400 dark:text-slate-500">
                                                    “{{ \Illuminate\Support\Str::limit($task->notes->first()->body, 70) }}”
                                                </span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                        <x-ui.button variant="primary" class="w-full justify-center" wire:click="addSelectedCarry"
                                     :disabled="count($carrySelected) === 0">
                            {{ trans_choice('Carry :count item forward|Carry :count items forward', count($carrySelected), ['count' => count($carrySelected)]) }}
                        </x-ui.button>
                        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            {{ __('Anything left unticked stays open and is proposed again next time.') }}
                        </p>
                    </div>
                @endif
            </div>

            <!-- Add a location -->
            <div class="{{ $card }} p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Add a Location') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Its open items already tracked in meetings come with it.') }}
                    </p>
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Project') }}</label>
                    <select wire:model.live="addProjectId" class="{{ $field }}">
                        <option value="">{{ __('Choose a project') }}</option>
                        @foreach($this->projects as $project)
                            <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                        @endforeach
                    </select>
                    @error('addProjectId') <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="{{ $label }}">{{ __('Job Site') }}</label>
                    <select wire:model="addJobSiteId" class="{{ $field }}" @disabled(! $addProjectId)>
                        <option value="">{{ __('The project as a whole') }}</option>
                        @foreach($this->addJobSites as $site)
                            <option value="{{ $site->id }}">{{ $site->job_site_name }}</option>
                        @endforeach
                    </select>
                </div>

                <x-ui.button variant="secondary" class="w-full justify-center" wire:click="addScope" :disabled="! $addProjectId">
                    {{ __('Add to the Agenda') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    @include('livewire.task.partials.detail-modal')
    @include('livewire.task.partials.form-modal')
    @include('livewire.task.partials.reason-modal')
</div>
