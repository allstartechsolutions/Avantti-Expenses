<x-project-layout :project="$project" active="tasks" title="{{ __('Tasks') }}">
    @include('livewire.task.partials.scoped-list', [
        'contextName' => $project->project_name,
        'showJobSiteFilter' => true,
    ])
</x-project-layout>
