<x-project-layout :project="$project" active="team" title="{{ __('Team') }}">
    <div class="space-y-6">
        @include('livewire.team.partials.flash')

        {{-- Who work here falls to when nobody says otherwise. Above the member
             list because it names one of the people in it. --}}
        @can('assignment-defaults.view', $project)
            <livewire:assignment.default-assignments-panel
                context-type="project"
                :context-id="$project->id"
                :key="'defaults-project-'.$project->id" />
        @endcan

        @include('livewire.team.partials.invitations')
        @include('livewire.team.partials.members', ['scopeLabel' => $project->project_name])
    </div>

    @include('livewire.team.partials.member-modal')
</x-project-layout>
