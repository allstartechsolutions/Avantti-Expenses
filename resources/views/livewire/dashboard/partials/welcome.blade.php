@php
    /*
     |--------------------------------------------------------------------------
     | The welcome panel
     |--------------------------------------------------------------------------
     |
     | What somebody without `dashboard.overview` lands on. Until M18 this was a
     | white box reading "Your dashboard is coming soon", which is every user
     | who is not an administrator seeing a dead end at the front door of the
     | application.
     |
     | Nothing here is a new permission. The shortcuts are the sidebar's own
     | entries — so a tile can never offer a screen its owner would be refused
     | on — and the task list is M13's `visibleTo`.
     */

    $shortcuts = $this->shortcuts;
    $myTasks = $this->myTasks;
@endphp

<div>
    {{-- Greeting --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
            {{ __('Welcome') }}, {{ auth()->user()->name }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {{ now()->translatedFormat('l, j F Y') }}
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- What is on their plate --}}
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('My Open Tasks') }}</h2>
                @can('tasks.view')
                    <a href="{{ route('tasks.mine') }}" class="text-xs text-[#3F5189] dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                @endcan
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-700 flex-1">
                @forelse ($myTasks as $task)
                    @php
                        $overdue = $task->due_date && $task->due_date->isPast();
                        $where = $task->jobSite?->job_site_name ?? $task->project?->project_name;
                    @endphp
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-white truncate">
                                {{ $task->title }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                {{ $where ?? __('Personal') }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            @if ($task->due_date)
                                <p class="text-xs {{ $overdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-500 dark:text-slate-400' }}">
                                    {{ $task->due_date->translatedFormat('j M') }}
                                </p>
                            @else
                                <p class="text-xs text-slate-400 dark:text-slate-500">{{ __('No due date') }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <svg class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            @can('tasks.view')
                                {{ __('Nothing assigned to you right now.') }}
                            @else
                                {{ __('Tasks are not part of your access.') }}
                            @endcan
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Where they can go --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Go to') }}</h2>
            </div>

            @if (count($shortcuts) > 0)
                <div class="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-2">
                    @foreach ($shortcuts as $shortcut)
                        <a href="{{ $shortcut['url'] }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 transition">
                            @if ($shortcut['icon'])
                                <svg class="h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $shortcut['icon'] }}" />
                                </svg>
                            @endif
                            <span class="truncate">{{ __($shortcut['name']) }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-10 text-center flex-1">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Your account has no areas open to it yet. Ask an administrator to give you access.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
