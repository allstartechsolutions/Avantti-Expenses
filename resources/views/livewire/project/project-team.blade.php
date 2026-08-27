<x-project-layout :project="$project" active="team" title="{{ __('Team') }}">
    <div class="space-y-6">
        @include('livewire.team.partials.flash')
        @include('livewire.team.partials.invitations')
        @include('livewire.team.partials.members', ['scopeLabel' => $project->project_name])
    </div>

    @include('livewire.team.partials.member-modal')
</x-project-layout>
