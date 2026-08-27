@props([
    'jobSite',
    'active' => 'overview',
    'title' => null
])

<div>
    {{-- Breadcrumbs --}}
    <x-ui.breadcrumb :items="[
        ['label' => __('Projects'), 'url' => route('projects.index')],
        ['label' => $jobSite->project->project_name, 'url' => route('projects.overview', $jobSite->project)],
        ['label' => __('Job Sites'), 'url' => route('projects.jobsites', $jobSite->project)],
        ['label' => $jobSite->job_site_name, 'url' => route('jobsites.overview', $jobSite)],
        ['label' => app(\App\Services\Navigation::class)->tabLabel($active)]
    ]" />

    {{-- Page Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $title ?? __('Job Site Details') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $jobSite->job_site_name }}</p>
            </div>
            <div class="flex items-center space-x-3">
                <x-ui.button
                    variant="secondary"
                    href="{{ route('projects.jobsites', $jobSite->project->id) }}"
                    icon="arrow-left">
                    {{ __('Back to Job Sites') }}
                </x-ui.button>
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
    <x-jobsite-nav :jobSite="$jobSite" :active="$active" />

    {{-- Content --}}
    {{ $slot }}
</div>
