@php
    $th = 'px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap';
    $canMaintain = auth()->user()->can('equipment.maintain') && ! $equipment->isRetired();
    $sevChip = ['critical' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', 'high' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400', 'medium' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', 'low' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'];
    $open = $findings->where('status', 'open'); $resolved = $findings->where('status', 'resolved');
@endphp
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Findings') }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('What was encountered during a maintenance — a worn belt, a cracked hose — open until a later maintenance or a note resolves it.') }}</p>
        </div>
        @if($canMaintain)
            <x-ui.button variant="primary" icon="plus" wire:click="startFinding" :disabled="$findingTargets->isEmpty()">{{ __('Record finding') }}</x-ui.button>
        @endif
    </div>
    @if($canMaintain && $findingTargets->isEmpty())
        <p class="text-sm text-amber-700 dark:text-amber-300">{{ __('A finding is recorded on a maintenance that has started or been completed. Start one on the Maintenance tab first.') }}</p>
    @endif

    @foreach([['title' => __('Open'), 'rows' => $open, 'empty' => __('Nothing open — everything found so far has been dealt with.')], ['title' => __('Resolved'), 'rows' => $resolved, 'empty' => __('Nothing resolved yet.')]] as $section)
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700"><h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section['title'] }} <span class="text-slate-400 font-normal">({{ $section['rows']->count() }})</span></h4></div>
            @if($section['rows']->isEmpty())
                <p class="p-6 text-sm text-slate-500 dark:text-slate-400">{{ $section['empty'] }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th scope="col" class="{{ $th }}">{{ __('Severity') }}</th>
                                <th scope="col" class="{{ $th }}">{{ __('Finding') }}</th>
                                <th scope="col" class="{{ $th }}">{{ __('Found during') }}</th>
                                <th scope="col" class="{{ $th }}">{{ __('Reported by') }}</th>
                                @if($section['title'] === __('Resolved'))<th scope="col" class="{{ $th }}">{{ __('Resolved') }}</th>@else<th scope="col" class="{{ $th }} text-right">{{ __('Actions') }}</th>@endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            @foreach($section['rows'] as $f)
                                <tr wire:key="finding-{{ $f->id }}">
                                    <td class="px-6 py-3 whitespace-nowrap"><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sevChip[$f->severity] ?? $sevChip['low'] }}">{{ $f->getSeverityLabel() }}</span></td>
                                    <td class="px-6 py-3 text-sm text-slate-900 dark:text-white">
                                        {{ $f->description }}
                                        @if($f->photo_path)<div class="mt-1"><a href="{{ route('files.show', ['path' => $f->photo_path]) }}" target="_blank" class="text-xs text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('View photo') }}</a></div>@endif
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $f->maintenance?->title ?? '—' }}<div class="text-xs text-slate-500 dark:text-slate-400">{{ $f->created_at->appDate() }}</div></td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">{{ $f->reportedBy?->name ?? '—' }}</td>
                                    @if($f->status === 'resolved')
                                        <td class="px-6 py-3 text-sm text-slate-700 dark:text-slate-300">
                                            {{ $f->resolved_at?->appDate() }} &bull; {{ $f->resolvedBy?->name ?? '—' }}
                                            @if($f->resolvedIn)<div class="text-xs text-slate-500 dark:text-slate-400">{{ __('in') }} {{ $f->resolvedIn->title }}</div>@endif
                                            @if($f->resolution_notes)<div class="text-xs text-slate-500 dark:text-slate-400">{{ $f->resolution_notes }}</div>@endif
                                        </td>
                                    @else
                                        <td class="px-6 py-3 whitespace-nowrap text-right">
                                            @if($canMaintain)<x-ui.button variant="secondary" size="sm" icon="check" wire:click="startResolve({{ $f->id }})">{{ __('Resolve') }}</x-ui.button>@endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    @include('livewire.equipment.partials.finding-dialogs')
</div>
