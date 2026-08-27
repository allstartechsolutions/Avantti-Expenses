<x-jobsite-layout :jobSite="$jobSite" active="team" title="{{ __('Team') }}">
    <div class="space-y-6">
        @include('livewire.team.partials.flash')
        @include('livewire.team.partials.invitations')
        @include('livewire.team.partials.members', ['scopeLabel' => $jobSite->job_site_name])
        @include('livewire.team.partials.inherited')
    </div>

    @include('livewire.team.partials.member-modal')
</x-jobsite-layout>
