<x-jobsite-layout :jobSite="$jobSite" active="tasks" title="{{ __('Tasks') }}">
    @include('livewire.task.partials.scoped-list', [
        'contextName' => $jobSite->project?->project_name.' — '.$jobSite->job_site_name,
        'showJobSiteFilter' => false,
    ])
</x-jobsite-layout>
