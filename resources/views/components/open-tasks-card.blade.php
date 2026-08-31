@props(['project' => null, 'jobSite' => null])

@php
    // One card, both levels — the parity rule applies to the overview pages as
    // much as to the list screens.
    $query = App\Models\Task::query()
        ->when($jobSite, fn ($q) => $q->where('job_site_id', $jobSite->id))
        ->unless($jobSite, fn ($q) => $q->where('project_id', $project?->id));

    $open = (clone $query)->open()->count();
    $overdue = (clone $query)->overdue()->count();
    $awaiting = (clone $query)->where('status', 'ready')->count();
    // Only dates still ahead: a past date under "Next due" reads as a bug, and
    // the overdue count beside it already covers those.
    $nextDue = (clone $query)->open()
        ->whereNotNull('due_date')
        ->whereDate('due_date', '>=', now()->toDateString())
        ->min('due_date');
    $oldest = (clone $query)->open()->orderBy('created_at')->first();

    $route = $jobSite
        ? route('jobsites.tasks', $jobSite)
        : route('projects.tasks', $project);

@endphp

@if(\App\Models\ModuleAccess::isEnabled('meetings'))
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Open Action Items') }}</h3>
            <a href="{{ $route }}" class="text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">{{ __('View all') }}</a>
        </div>

        <div class="p-6">
            @if($open === 0)
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Nothing open here. Tasks raised in a meeting against this location show up here.') }}
                </p>
                <a href="{{ $route }}" class="mt-3 inline-block text-sm text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                    {{ __('Raise a task') }}
                </a>
            @else
                <div class="flex items-baseline gap-6">
                    <div>
                        <p class="text-3xl font-bold text-slate-900 dark:text-white">{{ $open }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('open') }}</p>
                    </div>
                    @if($overdue > 0)
                        <div>
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $overdue }}</p>
                            <p class="text-xs text-red-500 dark:text-red-400">{{ __('overdue') }}</p>
                        </div>
                    @endif
                    @if($awaiting > 0)
                        <div>
                            <p class="text-3xl font-bold text-amber-600 dark:text-amber-400">{{ $awaiting }}</p>
                            <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('to confirm') }}</p>
                        </div>
                    @endif
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    @if($nextDue)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Next due') }}</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100">
                                {{ \Illuminate\Support\Carbon::parse($nextDue)->appDate() }}
                            </dd>
                        </div>
                    @endif
                    @if($oldest)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Oldest') }}</dt>
                            <dd class="min-w-0 text-right">
                                <a href="{{ $route }}" class="block truncate font-medium text-[#3F5189] dark:text-[#4A5A96] hover:underline">
                                    {{ $oldest->code() }} — {{ $oldest->title }}
                                </a>
                                <span class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ trans_choice('open :count day|open :count days', (int) $oldest->created_at->diffInDays(now()), ['count' => (int) $oldest->created_at->diffInDays(now())]) }}
                                </span>
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif
        </div>
    </div>
@endif
