<x-project-layout :project="$project" active="rfis" title="{{ __('RFIs') }}">
    <div class="space-y-6">
        @include('livewire.rfi.partials.summary-cards', ['summary' => $summary])

        @include('livewire.rfi.partials.filters', [
            'scope' => $project,
            'jobSites' => $jobSites,
            'disciplineOptions' => $disciplineOptions,
            'ballInCourtOptions' => $ballInCourtOptions,
            'canSeeImpact' => $this->rfiCanSeeImpact(),
            'createUrl' => route('projects.rfis.create', $project),
        ])

        @include('livewire.rfi.partials.list', [
            'scope' => $project,
            'rfis' => $rfis,
            'showLocationColumn' => true,
            'canSeeImpact' => $this->rfiCanSeeImpact(),
            'createUrl' => route('projects.rfis.create', $project),
        ])
    </div>
</x-project-layout>
