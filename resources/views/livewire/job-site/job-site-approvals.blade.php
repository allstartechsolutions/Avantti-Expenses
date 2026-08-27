<x-jobsite-layout :jobSite="$jobSite" active="approvals" title="{{ __('Approvals') }}">
    <div class="space-y-6">
        @include('livewire.approval.partials.summary-cards', ['summary' => $summary])

        @include('livewire.approval.partials.filters', [
            'scope' => $jobSite,
            'typeOptions' => $typeOptions,
            'reviewerOptions' => $reviewerOptions,
            'createUrl' => route('jobsites.approvals.create', $jobSite),
        ])

        {{-- One site is one location, so the Location column would repeat. --}}
        @include('livewire.approval.partials.list', [
            'scope' => $jobSite,
            'approvals' => $approvals,
            'showLocationColumn' => false,
            'createUrl' => route('jobsites.approvals.create', $jobSite),
        ])
    </div>
</x-jobsite-layout>
