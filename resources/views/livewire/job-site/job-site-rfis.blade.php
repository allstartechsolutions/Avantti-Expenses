<x-jobsite-layout :jobSite="$jobSite" active="rfis" title="{{ __('RFIs') }}">
    <div class="space-y-6">
        @include('livewire.rfi.partials.summary-cards', ['summary' => $summary])

        @include('livewire.rfi.partials.filters', [
            'scope' => $jobSite,
            'disciplineOptions' => $disciplineOptions,
            'ballInCourtOptions' => $ballInCourtOptions,
            'canSeeImpact' => $this->rfiCanSeeImpact(),
            'createUrl' => route('jobsites.rfis.create', $jobSite),
        ])

        {{-- One site is one location, so the Location column would repeat. --}}
        @include('livewire.rfi.partials.list', [
            'scope' => $jobSite,
            'rfis' => $rfis,
            'showLocationColumn' => false,
            'canSeeImpact' => $this->rfiCanSeeImpact(),
            'createUrl' => route('jobsites.rfis.create', $jobSite),
        ])
    </div>
</x-jobsite-layout>
