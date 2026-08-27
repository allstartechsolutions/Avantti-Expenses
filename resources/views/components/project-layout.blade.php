@props([
    'project',
    'active' => 'overview',
    'title' => __('Project Details')
])

<div>
    {{-- Breadcrumbs --}}
    <x-ui.breadcrumb :items="[
        ['label' => __('Projects'), 'url' => route('projects.index')],
        ['label' => $project->project_name, 'url' => route('projects.overview', $project)],
        ['label' => app(\App\Services\Navigation::class)->tabLabel($active)]
    ]" />

    {{-- Page Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $project->project_name }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('projects.index') }}"
                    icon="arrow-left">
                    {{ __('Back to Projects') }}
                </x-ui.button>
                @can('project.edit', $project)
                    <x-ui.button
                        variant="primary"
                        href="{{ route('projects.edit', $project->id) }}"
                        icon="edit">
                        {{ __('Edit Project') }}
                    </x-ui.button>
                @endcan
                @if(isset($actions))
                    {{ $actions }}
                @endif
            </div>
        </div>
    </div>

    {{-- Success Message --}}
    @if (session()->has('message'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
            {{ session('message') }}
        </div>
    @endif

    {{-- Navigation Menu --}}
    <x-project-nav :project="$project" :active="$active" />

    {{-- Content --}}
    {{ $slot }}
</div>
