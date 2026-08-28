<x-jobsite-layout :jobSite="$jobSite" active="team" title="{{ __('Team') }}">
    <div class="space-y-6">
        @include('livewire.team.partials.flash')

        {{-- Who work here falls to when nobody says otherwise. Above the member
             list because it names one of the people in it. --}}
        @can('assignment-defaults.view', $jobSite)
            <livewire:assignment.default-assignments-panel
                context-type="job_site"
                :context-id="$jobSite->id"
                :key="'defaults-jobsite-'.$jobSite->id" />
        @endcan

        @include('livewire.team.partials.invitations')
        @include('livewire.team.partials.members', ['scopeLabel' => $jobSite->job_site_name])
        @include('livewire.team.partials.inherited')
    </div>

    @include('livewire.team.partials.member-modal')
</x-jobsite-layout>
